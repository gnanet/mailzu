<?php
/**
 * LDAPEngine class
 * @author Samuel Tran
 * @version 2021-11-08
 * @package LDAPEngine
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
class LDAPEngine
{
    // The directory server, tested with OpenLDAP and Active Directory
    var $serverType;

    // An array of server IP address(es) or hostname(s)
    var $hosts;
    // SSL support
    var $ssl;
    // TLS support
    var $tls;
    // The base DN (e.g. "dc=example,dc=org")
    var $basedn;
    // The user identifier (e.g. "uid")
    var $userIdentifier;

    /**
     * /* The user to authenticate with when searching
     * /* if anonymous binding is not supported
     * /* (Active Directory doesn't support anonymous access by default)
     */
    var $searchUser;
    /**
     * /* The password to authenticate with when searching
     * /* if anonymous binding is not supported
     * /* (Active Directory doesn't support anonymous access by default)
     */
    var $searchPassword;

    /**
     * Variable specific to Active Directory:
     * Active Directory authenticates using user@domain
     */
    var $domain;

    /**
     * Variables specific to LDAP:
     * Container where the user records are kept
     */
    var $userContainer;

    // The login attribute to authenticate with
    var $login;
    // The password attribute to authenticate with
    var $password;
    // The name attribute of the user
    var $name;
    /**
     * The mail attribute used as the recipient final address
     * Could be the actual mail attribute or another attribute
     * (in the latter case look for the "%m" token in the ldap query filter in amavisd.conf)
     */
    var $mailAttr;

    // The last error code returned by the LDAP server
    var $ldapErrorCode;
    // Text of the error message
    var $ldapErrorText;


    // The internal LDAP connection handle
    var $connection;
    // Result of any connection
    var $bind;
    var $connected;

    // The user's logon name
    var $logonName;
    // The user's first name
    var $firstName;
    // The user's mail address ($mailAttr value)
    var $emailAddress;

    /**
     * LDAPEngine constructor to initialize object
     */
    function __construct()
    {
        global $conf;

        $this->serverType = strtolower($conf['auth']['serverType']);

        switch ($this->serverType) {
            case "ldap":
                $this->hosts = $conf['auth']['ldap_hosts'];
                $this->ssl = $conf['auth']['ldap_ssl'];
                $this->tls = $conf['auth']['ldap_tls'];
                $this->basedn = $conf['auth']['ldap_basedn'];
                $this->userIdentifier = $conf['auth']['ldap_user_identifier'];
                $this->userContainer = $conf['auth']['ldap_user_container'];
                $this->login = $conf['auth']['ldap_login'];
                $this->name = $conf['auth']['ldap_name'];
                $this->mailAttr = $conf['auth']['ldap_mailAttr'];
                $this->searchUser = $conf['auth']['ldap_searchUser'];
                $this->searchPassword = $conf['auth']['ldap_searchPassword'];
                break;
            case "ad":
                $this->hosts = $conf['auth']['ad_hosts'];
                $this->ssl = $conf['auth']['ad_ssl'];
                $this->tls = $conf['auth']['ad_tls'];
                $this->basedn = $conf['auth']['ad_basedn'];
                $this->userIdentifier = $conf['auth']['ad_user_identifier'];
                $this->domain = $conf['auth']['ad_domain'];
                $this->login = $conf['auth']['ad_login'];
                $this->name = $conf['auth']['ad_name'];
                $this->mailAttr = $conf['auth']['ad_mailAttr'];
                $this->searchUser = $conf['auth']['ad_searchUser'];
                $this->searchPassword = $conf['auth']['ad_searchPassword'];
                break;
            default:
                CmnFns::do_error_box(translate('Unknown server type'), '', false);
        }

    }

    // Connection handling methods -------------------------------------------

    /**
     * Makes a connection to the LDAP server.
     * Just creates a connection which is used in all later access.
     * If it can't connect and bind anonymously, it creates an error code of -1.
     * Returns true if connected, false if failed.
     * Takes an array of possible servers - if one doesn't work, it tries the next and so on.
     * @param none
     */
    function connect()
    {
        foreach ($this->hosts as $host) {
            $ldap_url = ($this->ssl ? "ldaps://" . $host : $host);
            $this->connection = ldap_connect($ldap_url);
            if ($this->connection) {
                ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
                switch ($this->serverType) {
                    case "ad":
                        // Active Directory needs:
                        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
                        break;
                    default:
                        break;
                }
                $this->connected = true;
                return true;
            } else {
                CmnFns::write_log('LDAP connection failed', '');
            }

        }

        $this->ldapErrorCode = -1;
        $this->ldapErrorText = "Unable to connect to any server";
        $this->connected = false;
        return false;
    }

    /**
     * Disconnects from the LDAP server
     * @param none
     */
    function disconnect()
    {
        if ($this->connected) {
            if (!@ldap_close($this->connection)) {
                $this->ldapErrorCode = ldap_errno($this->connection);
                $this->ldapErrorText = ldap_error($this->connection);
                return false;
            } else {
                $this->connected = false;
                return true;
            }
        }
        return true;
    }

    /**
     * Anonymously binds to the connection. After this is done,
     * queries and searches can be done - but read-only.
     */
    function anonBind()
    {
        if (  $this->tls ) {
            if ( ! @ldap_start_tls($this->connection) ) {
                $this->ldapErrorCode = ldap_errno( $this->connection);
                $this->ldapErrorText = ldap_error( $this->connection);
                CmnFns::write_log($this->ldapErrorCode . ': ' . $this->ldapErrorText, '');
                return false;	
            }
        }

        if (!$this->bind = ldap_bind($this->connection)) {
            $this->ldapErrorCode = ldap_errno($this->connection);
            $this->ldapErrorText = ldap_error($this->connection);
            CmnFns::write_log($this->ldapErrorCode . ': ' . $this->ldapErrorText, '');
            return false;
        } else {
            return true;
        }
    }

    /**
     * Binds to the directory with a specific username and password:
     * @param string $dn full LDAP dn or AD type 'user@domain'
     * @param string $password password
     * @return bool
     */
    function authBind($username, $password)
    {
        if (  $this->tls ) {
            if ( ! @ldap_start_tls($this->connection) ) {
                $this->ldapErrorCode = ldap_errno( $this->connection);
                $this->ldapErrorText = ldap_error( $this->connection);
                CmnFns::write_log($this->ldapErrorCode . ': ' . $this->ldapErrorText, '');
                return false;	
            }
        }

        if (!$this->bind = @ldap_bind($this->connection, $username, $password)) {
            $this->ldapErrorCode = ldap_errno($this->connection);
            $this->ldapErrorText = ldap_error($this->connection);
            CmnFns::write_log($this->ldapErrorCode . ': ' . $this->ldapErrorText, '');
            return false;
        } else {
            return true;
        }
    }

    // User methods -------------------------------------------

    /**
     * Returns the full user DN to use, based on the ldap server type
     * @param string $userlogin
     * @return string
     */
    function getUserDN($userlogin)
    {
        switch ($this->serverType) {
            case "ldap":
                if ($this->userContainer != '' && $this->userIdentifier == $this->login) {
                    // If a user container is specified and the login attribute is the same
                    // as the user identifier attribute, build the user dn
                    $dn = "$this->userIdentifier=$userlogin," . "$this->userContainer," . $this->basedn;
                } else {
                    // Search for user dn
                    $searchFilter = $this->login . "=" . $userlogin;
                    $dn = $this->searchUserDN($searchFilter);
                }
                break;
            case "ad":
                if (strtolower($this->login) == 'samaccountname') {
                    // If the user login attribute is 'samaccountname', build the user AD login
                    $dn = $userlogin . "@" . $this->domain;
                } else {
                    // Search for user dn
                    $searchFilter = $this->login . "=" . $userlogin;
                    $dn = $this->searchUserDN($searchFilter);
                }
                break;
            default:
                CmnFns::do_error_box(translate('Unknown server type'), '', false);
        }
        return $dn;
    }

    /**
     * Returns the correct search base, based on the ldap server type
     * @param none
     * @return string
     */
    function getSearchBase()
    {
        switch ($this->serverType) {
            case "ldap":
                $searchBase = ($this->userContainer != '' ? "$this->userContainer," . $this->basedn
                    : $this->basedn);
                break;
            case "ad":
                $searchBase = $this->basedn;
                break;
            default:
                CmnFns::do_error_box(translate('Unknown server type'), '', false);
        }
        return $searchBase;
    }

    /**
     * Returns the correct user username that matches the search filter (array with single username)
     * If several usernames are found, return the array of usernames.
     * @param string $searchFilter search filter in a standard LDAP query
     * @return array
     */
    function searchUserDN($searchFilter)
    {
        switch ($this->serverType) {
            case "ldap":
                if ($this->searchUser != '') {
                    // If a search user is defined bind with this user
                    $this->authBind($this->searchUser, $this->searchPassword);
                } else {
                    // Otherwise bind anonymously
                    $this->anonBind();
                }
                break;
            case "ad":
                // if the directory is AD, then bind first with the search user
                $this->authBind($this->searchUser, $this->searchPassword);
                break;
            default:
                CmnFns::do_error_box(translate('Unknown server type'), '', false);
        }

        $sr = ldap_search($this->connection, $this->getSearchBase(), $searchFilter, array('dn'));
        $entries = ldap_get_entries($this->connection, $sr);

        if ($entries["count"] < 1) {
            // If no results returned
            $this->ldapErrorCode = -1;
            $this->ldapErrorText = "No users found matching search criteria";
            CmnFns::write_log($this->ldapErrorCode . ': ' . $this->ldapErrorText, '');
        } else {
            // The search should give an unique dn
            // If several results are found get the first one
            $dn = $entries[0]['dn'];
        }

        return $dn;
    }

    /**
     * Queries LDAP for user information
     * @param string $dn
     * @return boolean indicating success or failure
     */
    function loadUserData($dn)
    {
        $this->emailAddress = array();

        // We are instered in getting just the user's first name and his/her mail attribute(s)
        $attributes = $this->mailAttr;
        array_push($attributes, strtolower($this->name));

        switch ($this->serverType) {
            case "ldap":
                $result = ldap_search($this->connection, $dn, "objectclass=*", $attributes);
                break;
            case "ad":
                if (strtolower($this->login) == 'samaccountname') {
                    // dn is of the form 'user@domain'
                    list($samaccountname, $domain) = explode("@", $dn);
                    $result = ldap_search($this->connection, $this->getSearchBase(),
                        $this->login . "=" . $samaccountname, $attributes);
                } else {
                    // dn is standard LDAP dn
                    $result = ldap_search($this->connection, $dn, "objectclass=*", $attributes);
                }
                break;
        }

        $entries = ldap_get_entries($this->connection, $result);

        if ($result and ($entries["count"] > 0)) {
            // The search should give a single entry
            // If several results are found get the first entry
            $this->firstName = $entries[0][strtolower($this->name)][0];
            foreach ($this->mailAttr as $value) {
                // For single value or multiple value attribute
                for ($i = 0; $i < $entries[0][strtolower($value)]["count"]; $i++) {
                    # AD proxyAddresses attribute values have 'smtp:' string before the actual email address
                    if (preg_match("/^smtp:/i", strtolower($entries[0][strtolower($value)][$i])) == 1) {
                        array_push($this->emailAddress, preg_replace("/^\w+:/", '', strtolower($entries[0][strtolower($value)][$i])));
                    } else {
                        array_push($this->emailAddress, strtolower($entries[0][strtolower($value)][$i]));
                    }
                }
            }
        } else {
            // If no results returned
            $this->ldapErrorCode = -1;
            $this->ldapErrorText = "No entry found matching search criteria";
            CmnFns::write_log($this->ldapErrorCode . ': ' . $this->ldapErrorText, '');
            return false;
        }

        return true;
    }


    // Helper methods -------------------------------------------

    /**
     * Returns user information
     * @return array containing user information
     */
    function getUserData()
    {
        $rval = array(
            'logonName' => $this->logonName,
            'firstName' => $this->firstName,
            'emailAddress' => $this->emailAddress
        );
        return $rval;
    }
}

?>
