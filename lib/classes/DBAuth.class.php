<?php
/**
 * DBAuth class
 * @author Samuel Tran
 * @version 2021-11-08
 * @package DBAuth
 *
 * Following functions taken from PhpScheduleIt,
 *    Nick Korbel <lqqkout13@users.sourceforge.net>:
 * db_connect(), cleanRow(), get_err()
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
 * Provide all database access/manipulation functionality for SQL Auth
 */
class DBAuth
{
    // Reference to the database object
    var $db;

    // The database hostname with port (hostname[:port])
    var $dbHost;

    // Database type
    var $dbType;
    // Database name
    var $dbName;

    // Database user
    var $dbUser;
    // Password for database user
    var $dbPass;

    // Name for auth table that contains usernames and passwords
    var $dbTable;
    // Name of the Username field of the MySQL table
    var $dbTableUsername;
    // Name of the password field of the MySQL table
    var $dbTablePassword;
    // Name of the 'first name' or 'full name' field of the MySQL table
    var $dbTableName;
    // Name of the email address field of the MySQL table
    var $dbTableMail;

    // Hash configuration
    // 1            = passwords will be stored md5 encrypted on database
    // other number = passwords will be stored as is on database
    var $isMd5;

    // The user's logon name
    var $logonName;
    // The user's first name
    var $firstName;
    // The user's mail address
    var $emailAddress;

    var $err_msg = '';

    /**
     * DBEngine constructor to initialize object
     * @param none
     */
    function __construct()
    {
        global $conf;

        $this->dbType = $conf['auth']['dbType'];
        $this->dbHost = $conf['auth']['dbHostSpec'];
        $this->dbName = $conf['auth']['dbName'];
        $this->dbUser = $conf['auth']['dbUser'];
        $this->dbPass = $conf['auth']['dbPass'];
        $this->isMd5 = $conf['auth']['dbIsMd5'];
        $this->dbTable = $conf['auth']['dbTable'];
        $this->dbTableUsername = $conf['auth']['dbTableUsername'];
        $this->dbTablePassword = $conf['auth']['dbTablePassword'];
        $this->dbTableName = $conf['auth']['dbTableName'];
        $this->dbTableMail = $conf['auth']['dbTableMail'];

        $this->db_connect();
    }

    // Connection handling methods -------------------------------------------

    /**
     * Create a persistent connection to the database
     * @param none
     */
    function db_connect()
    {
        /***********************************************************
         * / This uses PDO
         * / See https://www.php.net/manual/en/book.pdo.php
         * / for more information and syntax on PDO
         * /**********************************************************/

        // Data Source Name: This is the universal connection string
        // See https://www.php.net/manual/en/pdo.connections.php
        // for more information on DSN
        // Set utf8 as client charset
        switch ($this->dbType) {
            case "mysql":
                $dsn = $this->dbType . ':host=' . $this->dbHost . ';dbname=' . $this->dbName.';charset=utf8';
                break;
            default:
                $dsn = $this->dbType . ':host=' . $this->dbHost . ';dbname=' . $this->dbName;
                break;
        }

        try {
            $db = new PDO($dsn, $this->dbUser, $this->dbPass);
        } catch (PDOException $e) {
            die ('Error connecting to database: ' . $e->getMessage());
        }


        // Set fetch mode to return associatve array
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);

        $this->db = $db;
    }


    // User methods -------------------------------------------

    /**
     * Authenticates user
     * @param string $username
     * @param string $password
     * @return boolean
     */
    function authUser($username, $password)
    {
        if ($this->isMd5)
            $password = md5($password);

        $query = "SELECT $this->dbTableUsername, $this->dbTableMail"
            . (!empty($this->dbTableName) ? ", $this->dbTableName" : '')
            . " FROM $this->dbTable"
            . " WHERE $this->dbTableUsername=?"
            . " AND $this->dbTablePassword=?";

        $values = array($username, $password);

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute($values);
        // Check if error
        $this->check_for_error($result, $q);

        // Fetch the first row of data
        if ( $rs = $q->fetch(PDO::FETCH_ASSOC) ) {
            $rs = $this->cleanRow($rs);

            $this->logonName = $rs[$this->dbTableUsername];
            $this->firstName = (!empty($rs[$this->dbTableName]) ?
                $rs[$this->dbTableName] : $rs[$this->dbTableUsername]);
            $this->emailAddress = array($rs[$this->dbTableMail]);

            $q->closeCursor();

            return true;
        } else {
            $this->err_msg = translate('There are no records in the table.');
            return false;
        }
    }

    /**
     * Checks to see if there was a database error and die if there was
     * @param bool $result result boolean
     * @param object $q statement object
     */
    function check_for_error($result, $q)
    {
        if ( $result === false ) {
            $err_array = $q->errorInfo();
            $this->err_msg = 'Error['.$err_array[1].']: '.$err_array[2].' SQLSTATE='.$err_array[0];
            CmnFns::do_error_box(translate('There was an error executing your query') . '<br />'
                . $this->err_msg
                . '<br />' . '<a href="javascript: history.back();">' . translate('Back') . '</a>');
        }
        return false;
    }

    /**
     * Strips out slashes for all data in the return row
     * - THIS MUST ONLY BE ONE ROW OF DATA -
     * @param array $data array of data to clean up
     * @return array with same key => value pairs (except slashes)
     */
    function cleanRow($data)
    {
        $rval = array();

        foreach ($data as $key => $val)
            $rval[$key] = stripslashes($val);
        return $rval;
    }

    /**
     * Returns the last database error message
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
        $rval = array(
            'logonName' => $this->logonName,
            'firstName' => $this->firstName,
            'emailAddress' => $this->emailAddress
        );
        return $rval;
    }
}

?>
