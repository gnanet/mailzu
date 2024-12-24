<?php
/**
 * MailEngine class
 * @author Brian Wong <bwsource@users.sourceforge.net>
 * @version 2021-11-08
 * @package MailEngine
 *
 * Copyright (C) 2021 mailzu-ng
 * License: GPL, see LICENSE
 */
/**
 * Base directory of application
 */
if ( ! defined('BASE_DIR') ) {
    @define('BASE_DIR', __DIR__ . '/../..');
}
/**
 * Provide all mail access/manipulation functionality
 */
class MailEngine
{
    var $raw;        // Raw mail contents
    var $struct;        // The top-level MIME structure
    var $recipient;        // The recipient of the email
    var $msg_found;        // Msg found in database
    var $msg_error;        // Msg has MIME error
    var $last_error;    // PEAR Error Messages

    /**
     * MailEngine object constructor
     * $param string The unique mail_id
     * $param string The mail addr of the reader
     * $return object MailEngine object
     */
    function __construct($mail_id, $recip)
    {
        $this->recipient = $recip;
        $this->getRawContent($mail_id);
        $this->msg_error = false;
        if ($this->raw) {
            $this->msg_found = true;
            $this->struct = $this->getDecodedStruct($this->raw);
            if (PEAR::isError($this->struct)) {
                $this->msg_error = true;
                $this->last_error = $this->struct->getMessage();
            }
        } else {
            $this->msg_found = false;
        }

        return $this->struct;
    }

    /**
     * Decode the raw contents to get the MIME structure
     * $param string The complete raw message returned by get_raw_mail
     * $return object Mail_mimeDecode::decode object
     */
    function getDecodedStruct($contents)
    {
        $message = new Mail_mimeDecode($contents);
        $msg_struct = $message->decode(array('include_bodies' => true,
                'decode_bodies' => true,
                'decode_headers' => 'UTF8//IGNORE')
        );
        return $msg_struct;
    }

    /**
     * Get the raw content through a DB call
     * $param string The unique mail_id
     * $return string The complete raw email
     */
    function getRawContent($mail_id)
    {
        $db = new DBEngine();
        $this->raw = $db->get_raw_mail($mail_id, $this->recipient);

        // Mark read
        if (in_array($this->recipient, $_SESSION['sessionMail']) && $this->raw) {
            $db->update_msgrcpt_rs($mail_id, $this->recipient, 'v');
        }
    }
}

?>
