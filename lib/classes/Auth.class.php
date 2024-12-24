<?php
/**
 * Auth class
 *
 * Authorization and login functionality
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2021-11-08
 * @package Auth
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
 * Include Auth template functions
 */
include_once(BASE_DIR . '/templates/auth.template.php');

/**
 * This class provides all authoritiative and verification
 *  functionality, including login/logout, registration,
 *  and user verification
 */
class Auth
{
    var $is_loggedin = false;
    var $login_msg = '';
    var $is_attempt = false;
    var $success;

    /**
     * Create a reference to the database class
     *  and start the session
     * @param none
     */
    //function Auth() {
    //	$this->db = new AuthDB();
    //}

    /**
     * Check if user is a super administrator
     * This function checks to see if the currently
     *  logged in user is the administrator, granting
     *  them special permissions
     * @param none
     * @return boolean whether the user is a s_admin
     */
    public static function isAdmin()
    {
        return isset($_SESSION['sessionAdmin']);
    }

    /**
     * Check if user is a mail administrator
     * This function checks to see if the currently
     *  logged in user is the administrator, granting
     *  them special permissions
     * @param none
     * @return boolean whether the user is a m_admin
     */
    public static function isMailAdmin()
    {
        return (isset($_SESSION['sessionMailAdmin']) || isset($_SESSION['sessionAdmin']));
    }

    /**
     * Check user login
     * This function checks to see if the user has
     * a valid session set (if they are logged in)
     * @param none
     * @return boolean whether the user is logged in
     */
    public static function is_logged_in()
    {
        global $conf;
        if (isset($_COOKIE['ID'])) {
            if (self::isAllowedToLogin($_COOKIE['ID'])) {
                $_SESSION['sessionID'] = $_COOKIE['ID'];
                $_SESSION['sessionName'] = $_COOKIE['ID'];
                $_SESSION['sessionMail'] = array($_COOKIE['ID']);
                // If it is the super admin, set session variable
                foreach ($conf['auth']['s_admins'] as $s_admin) {
                    if (strtolower($s_admin) == strtolower($_SESSION['sessionID'])) {
                        $_SESSION['sessionAdmin'] = true;
                    }
                }

                // If it is the mail admin, set session variable
                foreach ($conf['auth']['m_admins'] as $m_admin) {
                    if (strtolower($m_admin) == strtolower($_SESSION['sessionID'])) {
                        $_SESSION['sessionMailAdmin'] = true;
                    }
                }

            }
        }


        return isset($_SESSION['sessionID']);
    }

    /**
     * Returns the currently logged in user's userid
     * @param none
     * @return the userid, or null if the user is not logged in
     */
    function getCurrentID()
    {
        return $_SESSION['sessionID'];//isset($_SESSION['sessionID']) ? $_SESSION['sessionID'] : null;
    }

    /**
     * Logs the user in
     * @param string $login login
     * @param string $pass password
     * @param string $cookieVal y or n if we are using cookie
     * @param string $isCookie id value of user stored in the cookie
     * @param string $resume page to forward the user to after a login
     * @param string $lang language code to set
     * @return any error message that occured during login
     */
    function doLogin($login, $pass, $cookieVal = null, $isCookie = false, $resume = '', $lang = '', $domain = '')
    {
        global $conf;
        $msg = '';
        $allowedToLogin = true;

        if (empty($resume)) $resume = 'summary.php';        // Go to control panel by default

        $_SESSION['sessionID'] = null;
        $_SESSION['sessionName'] = null;
        $_SESSION['sessionMail'] = null;
        $_SESSION['sessionAdmin'] = null;
        $_SESSION['sessionMailAdmin'] = null;
        $_SESSION['sessionNav'] = null;

        $login = stripslashes($login);
        $pass = stripslashes($pass);
        $ok_user = $ok_pass = false;
        $authMethod = $conf['auth']['serverType'];

        if ($isCookie != false) {        // Cookie is set
            $id = $isCookie;
            CmnFns::write_log('Cookie value detected ' . $id, $login);
            if ($this->isAllowedToLogin($id)) {
                $ok_user = $ok_pass = true;
                $data['logonName'] = $id;
            } else {
                $ok_user = $ok_pass = false;
                setcookie('ID', '', time() - 3600, '/');    // Clear out all cookies
                $msg .= translate('That cookie seems to be invalid') . '<br/>';
            }
        } else {

            switch (strtolower($authMethod)) {

                case "ad":
                case "ldap":
                    // Added this check for LDAP servers that switch to anonymous bind whenever
                    // provided password is left blank
                    if ($pass == '') return (translate('Invalid User Name/Password.'));

                    // Include LDAPEngine class
                    include_once('LDAPEngine.class.php');

                    $ldap = new LDAPEngine();

                    if ($ldap->connect()) {
                        // Get user DN
                        // For AD it could be of the form of 'user@domain' or standard LDAP dn
                        $dn = $ldap->getUserDN($login);

                        // Check if user is allowed to log in
                        if (!$this->isAllowedToLogin($login)) {
                            $allowedToLogin = false;
                            $msg .= 'User is not allowed to login';
                            // If user is allowed to log in try a bind
                        } elseif (($dn != '') && $ldap->authBind($dn, $pass)) {
                            $ldap->logonName = $login;
                            $ldap->loadUserData($dn);
                            $data = $ldap->getUserData();
                            $ok_user = true;
                            $ok_pass = true;
                        } else {
                            $msg .= 'Invalid User Name/Password.';
                        }

                        $ldap->disconnect();
                    }
                    break;

                case "sql":
                    // Include DBAuth class
                    include_once('DBAuth.class.php');

                    $db = new DBAuth();

                    // Check if user is allowed to log in
                    if (!$this->isAllowedToLogin($login)) {
                        $allowedToLogin = false;
                        $msg .= 'User is not allowed to login';
                        // If user is allowed to log in try to authenticate
                    } elseif ($db->authUser($login, $pass)) {
                        $data = $db->getUserData();
                        $ok_user = true;
                        $ok_pass = true;
                    } else {
                        $msg .= 'Invalid User Name/Password.';
                    }

                    break;
                case "exchange":
                    // Include ExchAuth class
                    include_once('ExchAuth.class.php');
                    $exch = new ExchAuth();
                    // Check if user is allowed to log in
                    if (!$this->isAllowedToLogin($login)) {
                        $allowedToLogin = false;
                        $msg .= 'User is not allowed to login';
                        // If user is allowed to log in try to authenticate
                    } elseif ($exch->authUser($login, $pass, $domain)) {
                        $data = $exch->getUserData();
                        $ok_user = true;
                        $ok_pass = true;
                    } else {
                        $msg .= 'Invalid User Name/Password.';
                    }

                    break;

                case "imap":
                    // Include IMAPAuth class
                    include_once('IMAPAuth.class.php');

                    $imap = new IMAPAuth();
                    // Check if user is allowed to log in
                    if (!$this->isAllowedToLogin($login)) {
                        $allowedToLogin = false;
                        $msg .= 'User is not allowed to login';
                        // If user is allowed to log in try to authenticate
                    } elseif ($imap->authUser($login, $pass)) {
                        $data = $imap->getUserData();
                        $ok_user = true;
                        $ok_pass = true;
                    } else {
                        $msg .= 'Invalid User Name/Password.';
                    }
                    break;

                default:
                    CmnFns::do_error_box(translate('Unknown server type'), '', false);
                    break;
            }
        }

        // If the login failed, notify the user and quit the app
        if (!$ok_user || !$ok_pass || !$allowedToLogin) {
            CmnFns::write_log('Authentication failed' . ', ' . $msg, $login);
            return translate($msg);
        } else {
            $this->is_loggedin = true;
            CmnFns::write_log('Authentication successful', $login);


            //$user = new User($id);	// Get user info

            // If the user wants to set a cookie, set it
            // for their ID and fname.  Expires in 30 days (2592000 seconds)
            if (!empty($cookieVal)) {
                //die ('Setting cookie');
                setcookie('ID', $data['logonName'], time() + 2592000, '/');
                if ( ! ( array_key_exists('emailAddress', $data) && is_array($data['emailAddress']) ) ) {
                    $data['emailAddress'] = array($data['logonName']);
                }
                $data['firstName'] = $data['logonName'];
            }


            // Set other session variables
            $_SESSION['sessionID'] = $data['logonName'];
            $_SESSION['sessionName'] = $data['firstName'];
            $_SESSION['sessionMail'] = $data['emailAddress'];


            // If it is the super admin, set session variable
            foreach ($conf['auth']['s_admins'] as $s_admin) {
                if (strtolower($s_admin) == strtolower($_SESSION['sessionID'])) {
                    $_SESSION['sessionAdmin'] = true;
                    if (empty($resume) || $resume == 'summary.php') $resume = 'messagesSummary.php';        // Go to sitewide control panel by default
                }
            }

            // If it is the mail admin, set session variable
            foreach ($conf['auth']['m_admins'] as $m_admin) {
                if (strtolower($m_admin) == strtolower($_SESSION['sessionID'])) {
                    $_SESSION['sessionMailAdmin'] = true;
                    if (empty($resume) || $resume == 'summary.php') $resume = 'messagesSummary.php';        // Go to sitewide control panel by default
                }
            }

            if ($lang != '') {
                set_language($lang);
            }

            // Send them to the control panel
            CmnFns::redirect(urldecode($resume));
        }
    }

    function isAllowedToLogin($username)
    {
        global $conf;

        // If not defined or set to false, $username is allowed to log in
        if (!isset($conf['auth']['login_restriction']) || !$conf['auth']['login_restriction']) return true;
        // merge the allowed users together and match case-insensitive
        $allowed = array_merge($conf['auth']['s_admins'], $conf['auth']['m_admins'], $conf['auth']['restricted_users']);
        foreach ($allowed as $allow) {
            if (strtolower($username) == strtolower($allow)) {
                return (true);
            }
        }
    }

    /**
     * Log the user out of the system
     * @param none
     */
    function doLogout()
    {
        // Check for valid session
        if (!$this->is_logged_in()) {
            $this->print_login_msg();
            die;
        } else {
            $login = $_SESSION['sessionID'];
            // Destroy all session variables
            unset($_SESSION['sessionID']);
            unset($_SESSION['sessionName']);
            unset($_SESSION['sessionMail']);
            unset($_SESSION['sessionNav']);
            if (isset($_SESSION['sessionAdmin'])) unset($_SESSION['sessionAdmin']);
            session_destroy();

            // Clear out all cookies
            setcookie('ID', '', time() - 3600, '/');

            // Log in logfile
            CmnFns::write_log('Logout successful', $login);

            // Refresh page
            CmnFns::redirect($_SERVER['PHP_SELF']);
        }
    }

    /**
     * Returns whether the user is attempting to log in
     * @param none
     * @return whether the user is attempting to log in
     */
    function isAttempting()
    {
        return $this->is_attempt;
    }

    /**
     * Kills app
     * @param none
     */
    function kill()
    {
        die;
    }

    /**
     * Destroy any lingering sessions
     * @param none
     */
    function clean()
    {
        // Destroy all session variables
        unset($_SESSION['sessionID']);
        unset($_SESSION['sessionName']);
        unset($_SESSION['sessionMail']);
        if (isset($_SESSION['sessionAdmin'])) unset($_SESSION['sessionAdmin']);
        session_destroy();
    }

    /**
     * Wrapper function to call template 'printLoginForm' function
     * @param string $msg error messages to display for user
     * @param string $resume page to resume after a login
     */
    function printLoginForm($msg = '', $resume = '')
    {
        printLoginForm($msg, $resume);
    }

    /**
     * Prints a message telling the user to log in
     * @param boolean $kill whether to end the program or not
     */
    function print_login_msg($kill = true)
    {
        CmnFns::redirect(CmnFns::getScriptURL() . '/index.php?auth=no&resume=' . urlencode($_SERVER['PHP_SELF']) . '?' . urlencode($_SERVER['QUERY_STRING']));
    }

    /**
     * Prints out the latest success box
     * @param none
     */
    function print_success_box()
    {
        CmnFns::do_message_box($this->success);
    }
}

?>
