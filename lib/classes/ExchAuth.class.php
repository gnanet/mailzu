<?php
/**
 * ExchAuth class
 * @version 2021-11-08
 * @Author Bogdan Baliuc <b.baliuc@rogers.com>
 * @package ExchAuth
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
 * Provide all database access/manipulation functionality for Exchange Auth
 */
class ExchAuth
{
    // The exchange hostname with port (hostname[:port])
    var $exchHost;
    // The exchange LDAP URI (ldap://hostname[:port])
    var $exchLDAP;
    // The user's logon name
    var $logonName;
    // The user's first name
    var $firstName;
    // The user's mail address(es)
    var $emailAddress;

    var $err_msg = '';

    /**
     * Constructor to initialize object
     * @param none
     */
    function __construct()
    {
        global $conf;

        $this->exchHost = $conf['auth']['exch_host'];
        $this->exchLDAP = $conf['auth']['exch_ldap'];
    }

    // User methods -------------------------------------------

    /**
     * Authenticates user
     * @param string $username
     * @param string $password
     * @param string $domain
     * @return boolean
     */
    function authUser($username, $password, $domain)
    {
        $fulluser = $domain . '/' . $username;
        $mbox = imap_open('{' . $this->exchHost . '/imap}Inbox', $fulluser, $password);
        if ($mbox === false) {
            $this->err_msg = translate('Invalid Username/Password');
            return false;
        } else {
            $ignore = imap_errors();
            imap_close($mbox);
        }
        $ldapconn = ldap_connect($this->exchLDAP);
        if ($ldapconn === false) {
            $this->err_msg = translate('Can not connect to LDAP server');
            return false;
        }
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
        $ldapbind = ldap_bind($ldapconn);
        if ($ldapbind === false) {
            $this->err_msg = translate('Can not bind to LDAP server');
            return false;
        }
        $ldapattr = array('cn', 'rfc822Mailbox', 'otherMailbox');
        $read = ldap_search($ldapconn, '', '(uid=' . $username . ')', $ldapattr);
        if ($read === false) {
            $this->err_msg = translate('Unable to search LDAP server');
            return false;
        }
        $info = ldap_get_entries($ldapconn, $read);
        $this->logonName = strtolower($username);
        $this->firstName = $info[0]['cn'][0];
        $this->emailAddress[] = strtolower($info[0]['rfc822mailbox'][0]);
        for ($i = 0; $i < $info[0]['othermailbox']['count']; $i++) {
            $data = $info[0]['othermailbox'][$i];
            if (strncasecmp($data, 'smtp$', 5) == 0) {
                $this->emailAddress[] = strtolower(substr($data, 5));
            }
        }
        ldap_close($ldapconn);
        return true;
    }

    /**
     * Returns the last error message
     * @param none
     * @return last error message generated
     */
    function get_err()
    {
        return $this->err_msg;
    }

    // Helper methods -------------------------------------------

    /**
     * Returns user information
     * @return array containing user information
     */
    function getUserData()
    {
        $return = array(
            'logonName' => $this->logonName,
            'firstName' => $this->firstName,
            'emailAddress' => $this->emailAddress
        );
        return $return;
    }
}

?>
