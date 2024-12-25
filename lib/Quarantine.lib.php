<?php
/**
 * Quarantine lib
 * @author Samuel Tran <stran2005@users.sourceforge.net>
 * @author Brian Wong <bwsource@users.sourceforge.net>
 * @author Nicolas Peyrussie <peyrouz@users.sourceforge.net>
 * @version 2021-11-08
 *
 * Copyright (C) 2005 - 2017 MailZu
 * License: GPL, see LICENSE
 */
/**
 * Base directory of application
 */
if ( ! defined('BASE_DIR') ) {
    @define('BASE_DIR', realpath(__DIR__ . '/..'));
}


/**
 * Provide quarantine related functions
 */

/**
 * Release messages function
 * @param array $emailaddresses recipient email address(es)
 * @param array $mail_id_array containing mail_id of messages to be released
 * @result return array of messages whose release failed
 */
function releaseMessages($emailaddresses, $mail_id_array)
{

    /*** Array pertaining to the release of messages ***/
    // This is an array of array, the key being the $mail_id
    // and the value being an array containing all the messages info (time, subject, ...) and also the release status.
    // The reason for this is that we want to keep the ordering of the messages selected for release.
    $release_messages = array();
    // This is an array of array, the key being the host
    // and the value being an array containing all the release info such as secret_id (one row per message)
    $hosts = array();

    /*** Variables pertaining to the request of release ***/
    // This array contains the messages that the logged in user wants the Admins to release
    $release_req_messages = array();
    // Counter for the number of release requests
    $j = 0;

    $nb_failure = 0;

    $db = new DBEngine();

    // Use beginTransaction to improve speed of 'RS' flag set up
    $result = $db->db->beginTransaction();
    $db->check_for_error($result, $db->db);

    // Fill the arrays
    foreach ($mail_id_array as $mail_id_recip) {

        // Get mail_id and recipient email address
        $temp = preg_split('/_/', $mail_id_recip);
        $mail_id = str_replace("_" . $temp[(sizeof($temp) - 1)], "", $mail_id_recip);
        $recip_email = $temp[(sizeof($temp) - 1)];

        // Check if logged in user is admin or logged in user is trying to release his own messages
        if (Auth::isMailAdmin() || in_array($recip_email, $emailaddresses))
            $result = $db->get_message($recip_email, $mail_id);
        else
            continue;

        $rs = $result[0];

        // if content type is 'B' or 'V' and the logged in user is not admin
        // add message to array of release request
        if (in_array($rs['content'], array('B', 'V')) && !Auth::isMailAdmin()) {
            $release_req_messages[$j] = array(
                "mail_id" => $mail_id,
                "from_addr" => $rs['from_addr'],
                "subject" => $rs['subject'],
                "time_num" => $rs['time_num'],
                "spam_level" => $rs['spam_level'],
                "content" => $rs['content']
            );

            // Try to update the RS flag to 'p' for pending
            if (!$db->update_msgrcpt_rs($mail_id, $recip_email, 'p')) {
                $release_req_messages[$j]["status"] = "Error: " . $db->get_err();
            } else {
                $release_req_messages[$j]["status"] = "Pending";
            }

            $j++;

            // Other cases where:
            //	- content type is 'B' or 'V' but the logged in user is admin, therefore allowed to release message
            //	- content type is 'S' or 'H'
        } else {
            // add message to be released to $hosts array
            $release_messages[$mail_id_recip] = array(
                "mail_id" => $mail_id,
                "time" => $rs['time_num'],
                "subject" => $rs['subject'],
                "from_addr" => $rs['from_addr'],
                "spam_level" => $rs['spam_level'],
                "content" => $rs['content'],
            );
            $hosts[$rs['host']][$mail_id_recip] = array(
                "secret_id" => $rs['secret_id'],
                "quar_type" => $rs['quar_type'],
                "quar_loc" => $rs['quar_loc'],
                "recip_email" => $rs['email']
            );
        }
    }

    global $conf;

    // If release request needs to be sent to Admins
    if (is_array($release_req_messages) && !empty($release_req_messages) && $conf['app']['notifyAdmin'])
        sendMailToAdmin(translate('Request release'), $release_req_messages);

    // If release needs to be done
    if (is_array($hosts) && !empty($hosts)) {

        // For each host create socket, connect and release all messages pertaining to that host
        foreach ($hosts as $host => $message_info) {

            // Create new TCP/IP socket and try to connect to $host using this socket
            $am = new AmavisdEngine($host);

            if (!$am->connected)
                foreach ($message_info as $mail_id_recip => $release_info) {
                    $release_messages[$mail_id_recip]['error_code'] = 1;
                    $release_messages[$mail_id_recip]['status'] = $am->last_error;
                    $nb_failure++;
                }
            else {
                foreach ($message_info as $mail_id_recip => $release_info) {
                    $socket_binding_result = $am->release_message(
                        $release_messages[$mail_id_recip]['mail_id'],
                        $release_info['secret_id'],
                        $release_info['recip_email'],
                        $release_info['quar_type'],
                        $release_info['quar_loc']
                    );

                    if (preg_match('/^setreply=250/', $socket_binding_result)) {
                        if ($db->update_msgrcpt_rs($release_messages[$mail_id_recip]['mail_id'], $release_info['recip_email'], 'R')) {
                            $release_messages[$mail_id_recip]['error_code'] = "0";
                            CmnFns::write_log('Message Released [' . $release_messages[$mail_id_recip]['content'] . ']: '
                                . $release_messages[$mail_id_recip]['mail_id'], $_SESSION['sessionID']);
                        } else {
                            $release_messages[$mail_id_recip]['error_code'] = 2;
                            $release_messages[$mail_id_recip]['status'] = "Error: " . $db->get_err();
                            $nb_failure++;
                        }
                    } else {
                        $release_messages[$mail_id_recip]['error_code'] = 3;
                        $release_messages[$mail_id_recip]['status'] = $am->last_error;
                        $nb_failure++;
                    }
                }

                // Shuting down and closing socket
                $am->disconnect();
            }
        }

    }

    // Commit
    $result = $db->db->commit();
    $db->check_for_error($result, $db->db);

    // Build array of messages whose release failed
    $failed_array = array();
    $i = 0;

    if ($nb_failure > 0) {

        foreach ($mail_id_array as $mail_id_recip) {

            if ($release_messages[$mail_id_recip]['error_code'] != 0) {
                $failed_array[$i] = array(
                    "mail_id" => $release_messages[$mail_id_recip]['mail_id'],
                    "from_addr" => $release_messages[$mail_id_recip]['from_addr'],
                    "subject" => $release_messages[$mail_id_recip]['subject'],
                    "time_num" => $release_messages[$mail_id_recip]['time'],
                    "spam_level" => $release_messages[$mail_id_recip]['spam_level'],
                    "content" => $release_messages[$mail_id_recip]['content'],
                    "status" => $release_messages[$mail_id_recip]['status']
                );
                CmnFns::write_log($release_messages[$mail_id_recip]['status'], $_SESSION['sessionID']);
                $i++;
            }

        }
    }

    // Return array of messages whose release failed
    return $failed_array;

}

/**
 * Update messages function
 * @param string $content_type 'B', 'S', ...
 * @param array $emailaddresses recipient email address(es)
 * @param array $mail_id_array containing mail_id of messages to be deleted
 * @param bool $all false (default) or true, if true all messages will be deleted
 * @result return array of messages whose release failed
 */
function updateMessages($flag, $content_type, $emailaddresses, $mail_id_array, $all = false)
{

    $result_array = array();
    $db = new DBEngine();

    // Use beginTransaction to improve speed of $flag set
    $result = $db->db->beginTransaction();
    $db->check_for_error($result, $db->db);

    if ($all) {
        $res = $db->get_user_messages($content_type, $emailaddresses, 'msgs.time_num', 'DESC', '', false, 0, 0, true);
        for ($i = 0; is_array($res) && $i < count($res); $i++) {
            $rs = $res[$i];

            if (Auth::isMailAdmin() || in_array($rs['email'], $emailaddresses)) {
                if (!$db->update_msgrcpt_rs($rs['mail_id'], $rs['email'], $flag)) {
                    $rs = $result[0];
                    $result_array[$i] = array(
                        "mail_id" => $mail_id,
                        "from_addr" => $rs['from_addr'],
                        "subject" => $rs['subject'],
                        "time_num" => $rs['time_num'],
                        "spam_level" => $rs['spam_level'],
                        "status" => "Error: " . $db->get_err()
                    );
                }
            } else {
                continue;
            }
        }

    } else {
        $i = 0;
        foreach ($mail_id_array as $mail_id_recip) {
            // Get mail_id and recipient email address
            $temp = preg_split('/_/', $mail_id_recip);
            $mail_id = str_replace("_" . $temp[(sizeof($temp) - 1)], "", $mail_id_recip);
            $recip_email = $temp[(sizeof($temp) - 1)];

            // Check if logged in user is admin or logged in user is trying to delete his own messages
            if (Auth::isMailAdmin() || in_array($recip_email, $emailaddresses)) {
                $result = $db->get_message($recip_email, $mail_id);
            } else {
                continue;
            }

            if (!$db->update_msgrcpt_rs($mail_id, $recip_email, $flag)) {
                $rs = $result[0];
                $result_array[$i] = array(
                    "mail_id" => $mail_id,
                    "from_addr" => $rs['from_addr'],
                    "subject" => $rs['subject'],
                    "time_num" => $rs['time_num'],
                    "spam_level" => $rs['spam_level'],
                    "status" => "Error: " . $db->get_err()
                );
                $i++;
            }
        }
    }

    // Commit
    $result = $db->db->commit();
    $db->check_for_error($result, $db->db);

    // Return array of messages whose release failed
    return $result_array;
}


/**
 * Function that sends:
 *    - an error report if $action = 'Release', 'Delete' or 'Delete All'
 *    - an request if $action = 'Request release'
 * to $conf['app']['adminEmail']
 * @param string $action 'Release', 'Delete', 'Delete All', 'Request release'
 * @param $messages_array array containing messages info
 */
function sendMailToAdmin($myaction, $messages_array)
{

    global $conf;
    $title = $conf['app']['title'];
    $adminEmail = $conf['app']['adminEmail'];

    $sub = "[" . $title . "] Notification from '" . $_SESSION['sessionID'] . "'";
    $msg = "Mail notification sent by '" . $_SESSION['sessionID'] . "' <" . $_SESSION['sessionMail'][0] . ">.\r\n\r\n";

    switch ($myaction) {
        case translate('Release'):
        case translate('Release/Request release'):
            $msg .= translate('A problem occured when trying to release the following messages') . ":\r\n\r\n";
            break;
        case translate('Request release'):
            $msg .= translate('Please release the following messages') . ":\r\n\r\n";
            break;
        case translate('Delete'):
        case translate('Delete All'):
            $msg .= translate('A problem occured when trying to delete the following messages') . ":\r\n\r\n";
            break;
        default:
            CmnFns::do_error_box(translate('Unknown action type'), '');
    }

    for ($i = 0; is_array($messages_array) && $i < count($messages_array); $i++) {
        $rs = $messages_array[$i];
        $msg .= "Message #" . ($i + 1) . ":\r\n";
        $msg .= "\tmail_id = " . $rs['mail_id'] . "\r\n";
        $msg .= "\t" . translate('From') . " = " . $rs['from_addr'] . "\r\n";
        $msg .= "\t" . translate('Subject') . " = " . $rs['subject'] . "\r\n";
        $msg .= "\t" . translate('Date') . " = " . CmnFns::formatDateTime($rs['time_num']) . "\r\n";
        $msg .= "\t" . translate('Score') . " = " . $rs['spam_level'] . "\r\n";
        $msg .= "\t" . translate('Status') . " = " . $rs['status'] . "\r\n";
        $msg .= "\t" . translate('Content Type') . " = " . $rs['content'] . "\r\n\r\n";

    }

    // Send email
    $mailer = new mailzuMailer();
    if (is_array($adminEmail)) {
        foreach ($adminEmail as $email) {
            $mailer->AddAddress($email, '');
        }
    } else {
        $mailer->AddAddress($adminEmail, '');
    }
    $mailer->FromName = $_SESSION['sessionID'];
    $mailer->From = $_SESSION['sessionMail'][0];
    $mailer->Subject = $sub;
    $mailer->Body = $msg;
    $mailer->Send();

    return true;

}

?>
