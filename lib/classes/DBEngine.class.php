<?php
/**
 * DBEngine class
 * This uses PDO for database access
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2021-11-08
 * @package DBEngine
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
 * Provide all database access/manipulation functionality
 */
class DBEngine
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

    var $dbUsersTable;

    var $err_msg = '';
    var $numRows;

    /**
     * DBEngine constructor to initialize object
     * @param none
     */
    function __construct()
    {
        global $conf;

        $this->dbType = $conf['db']['dbType'];
        $this->dbName = $conf['db']['dbName'];
        $this->dbUser = $conf['db']['dbUser'];
        $this->dbPass = $conf['db']['dbPass'];
        $this->dbHost = $conf['db']['hostSpec'];
        $this->dbUsersTable = $conf['db']['dbUsersTable'];
        $this->db_connect();
    }

    /**
     * Create a persistent connection to the database
     * @param none
     * @global $conf
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
            CmnFns::write_log('Error connecting to database: ' . $e->getMessage(), $_SESSION['sessionID']);
            die ('Error connecting to database: ' . $e->getMessage());
        }


        // Set fetch mode to return associatve array
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);

        $this->db = $db;
    }

    /**
     * Return counts for spam, banned, viruses, bad headers, and pending
     * @return array of the 5 counts
     */
    function get_site_summary()
    {
        global $conf;

        $rval = array();
        $total = array('spam' => 0, 'banned' => 0, 'virus' => 0, 'header' => 0, 'pending' => 0, 'total' => 0);

        $query = "SELECT date,
			MAX(stattable.spam) AS spam,
			MAX(stattable.banned) AS banned,
			MAX(stattable.viruses) AS viruses,
			MAX(stattable.badheaders) AS badheaders,
			MAX(stattable.pending) AS pending
			FROM (
				SELECT CAST(time_iso AS DATE) AS date,
					COUNT(msgs.content) AS spam,
					0 AS banned,
					0 AS viruses,
					0 AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					WHERE msgs.content='S' AND NOT (msgs.quar_type = '')
					AND msgrcpt.rs IN ('','v')
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					COUNT(msgs.content) AS banned,
					0 AS viruses,
					0 AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					WHERE msgs.content='B' AND NOT (msgs.quar_type = '')
					AND msgrcpt.rs IN ('','v')
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					0 AS banned,
					COUNT(msgs.content) AS viruses,
					0 AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					WHERE msgs.content='V' AND NOT (msgs.quar_type = '')
					AND msgrcpt.rs IN ('','v')
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					0 AS banned,
					0 AS viruses,
					COUNT(msgs.content) AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					WHERE msgs.content='H' AND NOT (msgs.quar_type = '')
					AND msgrcpt.rs IN ('','v')
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					0 AS banned,
					0 AS viruses,
					0 AS badheaders,
					COUNT(msgs.content) AS pending
					FROM msgs JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					WHERE msgrcpt.rs='p' AND NOT (msgs.quar_type = '')
					GROUP BY CAST(time_iso AS DATE)
			) AS stattable
			GROUP BY date
			ORDER BY date";


        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute();
        // Check if error
        $this->check_for_error($result, $q);

        while ( $rs = $q->fetch(PDO::FETCH_ASSOC) )
        {
            $timestamp = CmnFns::formatDateISO($rs['date']);
            $date = CmnFns::formatDate($timestamp);
            $totalthisdate = $rs['spam'] + $rs['banned'] + $rs['viruses'] + $rs['badheaders'] + $rs['pending'];
            $rval[$date] = array('spam' => $rs['spam'],
                'banned' => $rs['banned'],
                'virus' => $rs['viruses'],
                'header' => $rs['badheaders'],
                'pending' => $rs['pending'],
                'total' => $totalthisdate);
        }

        // Total the data
        foreach ($rval as $date => $typearray) {
            foreach ($typearray as $type => $count) {
                $total[$type] += $count;
            }
        }

        $rval['Total'] = $total;
        $q->closeCursor();

        return $rval;
    }

    // User methods -------------------------------------------

    /**
     * Return counts for spam, banned, viruses, bad headers, and pending
     * @param string full email address
     * @return array of the 5 counts
     */
    function get_user_summary($emailaddresses)
    {
        global $conf;

        $rval = array();
        $total = array('spam' => 0, 'banned' => 0, 'virus' => 0, 'header' => 0, 'pending' => 0, 'total' => 0);

        // Get where clause for recipient email address(es)
        $recipEmailClause = $this->convertEmailaddresses2SQL($emailaddresses);

        # mysql seems to run faster with a left join
        if ($conf['db']['dbType'] == 'mysql') {
            $join_type = ' LEFT JOIN';
        } else {
            $join_type = ' INNER JOIN';
        }

        $query = "SELECT date,
			MAX(stattable.spam) AS spam,
			MAX(stattable.banned) AS banned,
			MAX(stattable.viruses) AS viruses,
			MAX(stattable.badheaders) AS badheaders,
			MAX(stattable.pending) AS pending
			FROM (
				SELECT CAST(time_iso AS DATE) AS date,
					COUNT(msgs.content) AS spam,
					0 AS banned,
					0 AS viruses,
					0 AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					$join_type maddr AS recip ON msgrcpt.rid=recip.id
					WHERE msgs.content='S' AND NOT (msgs.quar_type = '') AND msgrcpt.rs IN ('','v')
					AND $recipEmailClause
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					COUNT(msgs.content) AS banned,
					0 AS viruses,
					0 AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					$join_type maddr AS recip ON msgrcpt.rid=recip.id
					WHERE msgs.content='B' AND NOT (msgs.quar_type = '') AND msgrcpt.rs IN ('','v')
					AND $recipEmailClause
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					0 AS banned,
					COUNT(msgs.content) AS viruses,
					0 AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					$join_type maddr AS recip ON msgrcpt.rid=recip.id
					WHERE msgs.content='V' AND NOT (msgs.quar_type = '') AND msgrcpt.rs IN ('','v')
					AND $recipEmailClause
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					0 AS banned,
					0 AS viruses,
					COUNT(msgs.content) AS badheaders,
					0 AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					$join_type maddr AS recip ON msgrcpt.rid=recip.id
					WHERE msgs.content='H' AND NOT (msgs.quar_type = '') AND msgrcpt.rs IN ('','v')
					AND $recipEmailClause
					GROUP BY CAST(time_iso AS DATE)
				UNION
				SELECT CAST(time_iso AS DATE) AS date,
					0 AS spam,
					0 AS banned,
					0 AS viruses,
					0 AS badheaders,
					COUNT(msgs.content) AS pending
					FROM msgs INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id
					$join_type maddr AS recip ON msgrcpt.rid=recip.id
					WHERE msgrcpt.rs='p' AND NOT (msgs.quar_type = '')
					AND $recipEmailClause
					GROUP BY CAST(time_iso AS DATE)
			) AS stattable
			GROUP BY date
			ORDER BY date";

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute();
        // Check if error
        $this->check_for_error($result, $q);

        while ( $rs = $q->fetch(PDO::FETCH_ASSOC) )
        {
            $timestamp = CmnFns::formatDateISO($rs['date']);
            $date = CmnFns::formatDate($timestamp);
            $totalthisdate = $rs['spam'] + $rs['banned'] + $rs['viruses'] + $rs['badheaders'] + $rs['pending'];
            $rval[$date] = array('spam' => $rs['spam'],
                'banned' => $rs['banned'],
                'virus' => $rs['viruses'],
                'header' => $rs['badheaders'],
                'pending' => $rs['pending'],
                'total' => $totalthisdate);
        }

        // Total the data
        foreach ($rval as $date => $typearray) {
            foreach ($typearray as $type => $count) {
                $total[$type] += $count;
            }
        }

        $rval['Total'] = $total;
        $q->closeCursor();

        return $rval;
    }

    /**
     * Return all message in quarantine associated with $emailaddress
     * @param string $content_type message type ('B', 'S', ...)
     * @param array $emailaddresses user email address(es)
     * @param string $order sql order string
     * @param string $vert sql vertical order string
     * @param array $search_array for search engine
     * @param boolean $msgs_all if true get messages for all users, if false get messages for users in $emailaddresses
     * @param integer $rs_option : 0 for new and read messages; 1 for pending messagesr; 2 for new, read and pending
     * @param integer $page : page number, 0 by default
     * @param boolean $get_all , if true get all messages. False by default.
     * @return array of messages in quarantine
     */
    function get_user_messages($content_type, $emailaddresses, $order = 'msgs.time_num', $vert = 'DESC', $search_array = '', $msgs_all = false, $rs_option = 0, $page = 0, $get_all = false)
    {
        global $conf;

        # MySQL seems to run faster with a LEFT JOIN
        if ($conf['db']['dbType'] == 'mysql') {
            $join_type = ' LEFT JOIN';
        } else {
            $join_type = ' INNER JOIN';
        }

        // grab the display size limit set in config.php
//		$sizeLimit = isset ( $conf['app']['displaySizeLimit'] ) &&
//				is_numeric( $conf['app']['displaySizeLimit'] ) ? $conf['app']['displaySizeLimit'] : 50;
        if (isset($_SESSION['sessionMailAdmin']) || isset($_SESSION['sessionAdmin'])) {
            $sizeLimit = isset ($conf['app']['displaySizeLimitAdmin']) && is_numeric($conf['app']['displaySizeLimitAdmin']) ?
                $conf['app']['displaySizeLimitAdmin'] : 100;
        } else {
            $sizeLimit = isset ($conf['app']['displaySizeLimit']) && is_numeric($conf['app']['displaySizeLimit']) ?
                $conf['app']['displaySizeLimit'] : 50;
        }


        $rowsval = array();
        $rval = array();

        if (is_array($search_array)) {
            $search_clause = "";
            foreach ($search_array as $filter) {
                $search_clause .= ' AND ' . $filter;
            }
        }

        if (!$msgs_all) {
            // Get where clause for recipient email address(es)
            $emailaddr_clause = (!empty($emailaddresses) ?
                ' AND ' . $this->convertEmailaddresses2SQL($emailaddresses) :
                '');
        }

        switch ($rs_option) {
            case 0:
                $rs_clause = ' AND msgrcpt.rs in (\'\', \'v\')';
                break;
            case 1:
                $rs_clause = ' AND msgrcpt.rs=\'p\'';
                break;
            case 2:
                $rs_clause = ' AND msgrcpt.rs in (\'\', \'v\', \'p\')';
                break;
            default:
                $rs_clause = '';
        }

        if (isset($_SESSION['sessionMailAdmin']) || isset($_SESSION['sessionAdmin'])) {
            $type_clause = ($content_type == 'A' ? ' msgs.content in (\'S\', \'B\', \'V\', \'H\')' : ' msgs.content=?');
        } else {
            if ($content_type == 'A') {
                $type_clause = ' msgs.content in (\'S\', \'B\'';
                $type_clause = ($conf['app']['allowBadHeaders'] ? $type_clause . ', \'H\'' : $type_clause);
                $type_clause = ($conf['app']['allowViruses'] ? $type_clause . ', \'V\')' : $type_clause . ')');
            } else {
                $type_clause = ' msgs.content=?';
            }
        }

        $query = "SELECT
			msgs.time_num,
			msgs.from_addr,
			msgs.mail_id,
			msgs.partition_tag,
			msgs.subject,
			msgs.spam_level,
			msgs.content,
			msgrcpt.rs,
			msgs.quar_type,
			recip.email
			FROM msgs
			INNER JOIN msgrcpt		ON msgs.mail_id = msgrcpt.mail_id
			$join_type maddr AS sender 	ON msgs.sid = sender.id
			$join_type maddr AS recip  	ON msgrcpt.rid = recip.id
			WHERE $type_clause"
            // Only check against the email address when not admin
            . ($msgs_all ? ' ' : $emailaddr_clause)
            . " $rs_clause
			$search_clause
			AND msgs.quar_type <> ''
			ORDER BY $order $vert ";

        // Prepare query
        $q = $this->db->prepare($query);

        if ($content_type != 'A') {
            // Prepend the content type if we want a specific type of mail
            $values = array($content_type);
            // Execute query
            $result = $q->execute($values);
        } else {
            $result = $q->execute();
        }

        // Check if error
        $this->check_for_error($result, $q);

        while ( $rs = $q->fetch(PDO::FETCH_ASSOC) ) {
            $rowsval[] = $this->cleanRow($rs);
        }
        $this->numRows = count($rowsval);

        if ($this->numRows <= 0) {
            return NULL;
        }

        if ($get_all) {
            return $rowsval;
        } else {
            // the row to start fetching
            $from = $page * $sizeLimit;
            // how many results per page
            $res_per_page = $sizeLimit;
            // the last row to fetch for this page
            $to = $from + $res_per_page - 1;
            foreach (range($from, $to) as $rownum) {
                if (!$row = @$rowsval[$rownum]) {
                    break;
                }
                $rval[] = $this->cleanRow($row);
            }
        }

        $q->closeCursor();

        return $rval;
    }

    /**
     * Return message(s) in quarantine associated with $emailaddress and $mail_id
     * @param string $emailaddress user email address
     * @param string $mail_id message mail_id
     * @param boolean $isAdmin is true is the logged in user is MailAdmin/SuperAdmin
     * @return array of message(s)
     */
    function get_message($emailaddress, $mail_id)
    {
        global $conf;

        # MySQL seems to run faster with a LEFT JOIN
        if ($conf['db']['dbType'] == 'mysql') {
            $join_type = ' LEFT JOIN';
        } else {
            $join_type = ' INNER JOIN';
        }

        $recipEmailClause = $this->convertEmailaddresses2SQL($emailaddress);

        $rval = array();

        $query = 'SELECT msgs.time_num, msgs.secret_id, msgs.partition_tag, msgs.subject, msgs.from_addr, msgs.spam_level,'
            . ' msgrcpt.rs, recip.email, msgs.host, msgs.content, msgs.quar_type, msgs.quar_loc'
            . ' FROM msgs'
            . ' INNER JOIN msgrcpt ON msgs.mail_id=msgrcpt.mail_id'
            . $join_type . ' maddr AS sender ON msgs.sid=sender.id'
            . $join_type . ' maddr AS recip  ON msgrcpt.rid=recip.id'
            . ' WHERE recip.email=? '
            . ' AND msgs.mail_id=? ';

        $values = array($emailaddress, $mail_id);

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute($values);
        // Check if error
        $this->check_for_error($result, $q);

        while ( $rs = $q->fetch(PDO::FETCH_ASSOC) )
        {
            $rval[] = $this->cleanRow($rs);
        }

        $this->numRows = count($rval);
        if ($this->numRows <= 0) {
            return NULL;
        }

        $q->closeCursor();

        return $rval;
    }

    /**
     * Set RS flag in table 'msgrcpt'
     * @param string $mail_id message mail_id
     * @param string $mail_rcpt user email address
     * @param string $flag status ('', 'R', 'D' ...)
     * @param boolean $isAdmin is true is the logged in user is MailAdmin/SuperAdmin
     * @return array of message(s)
     */
    function update_msgrcpt_rs($mail_id, $mail_rcpt, $flag)
    {
        // If its a pending message, do not set the rs flag to 'v'
        $cur_msg_array = $this->get_message($mail_rcpt, $mail_id);
        $msg_status = $cur_msg_array[0];
        if ($msg_status['rs'] == 'p' && $flag == 'v') return true;

        $partition_tag = isset($msg_status['partition_tag']) ? $msg_status['partition_tag'] : 0;

        $query = 'UPDATE msgrcpt SET rs=?'
            . ' WHERE mail_id=?'
            . ' AND rid=(SELECT id FROM maddr WHERE partition_tag=? AND email=?)';

        $values = array($flag, $mail_id, $partition_tag, $mail_rcpt);

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute($values);
        // Check if error
        $this->check_for_error($result, $q);

        return true;
    }

    /**
     * Function that returns number of entries for logged in user
     * where RS flag is equal to $flag
     * @param array $emailaddresses user email address(es)
     * @param string $flag 'P', 'R', ...
     * @return number of message(s)
     */
    function get_count_rs($emailaddresses, $flag)
    {
        // Get where clause for recipient email address(es)
        $emailaddr_clause = $this->convertEmailaddresses2SQL($emailaddresses);
        if ($emailaddr_clause != '')
            $emailaddr_clause = ' AND ' . $emailaddr_clause;

        $query = 'SELECT mail_id FROM msgrcpt, maddr as recip'
            . ' WHERE msgrcpt.rid=recip.id'
            . $emailaddr_clause
            . ' AND rs=?';

        $values = array($flag);

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute($values);
        // Check if error
        $this->check_for_error($result, $q);

        $count = @count($q->fetchAll());

        $q->closeCursor();

        return $count;
    }

    /**
     * Get the raw email from the database
     * @param string The unique identifying mail_id
     * @param string The recipient's email address
     * @return string The complete email string
     */
    function get_raw_mail($mail_id, $email_recip)
    {
        global $conf;

        $mail_text_column = ' mail_text';
        # If using the bytea or BLOB type for sql quarantine use proper conversion
        # (since amavisd 2.4.4
        if ($conf['db']['binquar']) {
            if ($conf['db']['dbType'] == 'mysql') {
                $mail_text_column = ' CONVERT(mail_text USING utf8) AS mail_text';
            } else {
                $mail_text_column = " encode(mail_text,'escape') AS mail_text";
            }
        }

        if (isset($_SESSION['sessionMailAdmin']) || isset($_SESSION['sessionAdmin'])) {
            $values = array($mail_id);
            $query = 'SELECT' . $mail_text_column . ' FROM quarantine ' .
                'WHERE mail_id=?';
        } else {
            $values = array($mail_id, $email_recip);
            $query = 'SELECT' . $mail_text_column . ' FROM quarantine Q, msgrcpt M, maddr recip ' .
                'WHERE (Q.mail_id=?) AND (M.mail_id=Q.mail_id) AND (M.rid=recip.id) ' .
                'AND (recip.email=?) ' .
                'ORDER BY chunk_ind';
        }

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute($values);
        // Check if error
        $this->check_for_error($result, $q);

        $rval = "";
        while ( $rs = $q->fetch(PDO::FETCH_ASSOC) ) {
            $rval .= $rs['mail_text'];
        }

        $q->closeCursor();

        return $rval;
    }

    /**
     * Checks to see if there was a database error, log in file and die if there was
     * @param bool $result result boolean
     * @param object $q statement object
     */
    function check_for_error($result, $q)
    {
        global $conf;
        if ( $result === false ) {
            $err_array = $q->errorInfo();
            $this->err_msg = 'Error['.$err_array[1].']: '.$err_array[2].' SQLSTATE='.$err_array[0];
            CmnFns::write_log($this->err_msg, $_SESSION['sessionID']);
            CmnFns::write_log('There was an error executing your query' . ' '
                . $q->queryString, $_SESSION['sessionID']);
            CmnFns::do_error_box(translate('There was an error executing your query') . '<br />'
                . $this->err_msg
                . '<br />' . '<a href="javascript: history.back();">' . translate('Back') . '</a>');
        } else {
            if ($conf['app']['debug']) {
                CmnFns::write_log("[DEBUG SQL QUERY]: ".$q->queryString);
            }

        }
        return false;
    }

    /**
     * Return an array of email aliases associated with $loginEmail
     * @param string $loginEmail user's login email address
     * @return array of email addresses, or an empty array
     */
    function get_useraliases($loginEmail)
    {
        global $conf;

        $query = sprintf("SELECT aliases FROM ".$this->dbUsersTable." WHERE aliases IS NOT NULL AND email = '%s';", $loginEmail);

        // Prepare query
        $q = $this->db->prepare($query);
        // Execute query
        $result = $q->execute();
        // Check if error
        $this->check_for_error($result, $q);

        $rval = array();
        $rs = $q->fetchAll();
        if ( empty($rs[0]['aliases']) !== true ) {
            $rval = explode(",", $rs[0]['aliases']);
        }
        $q->closeCursor();
        return $rval;
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

    /**
     * Convert search filter into SQL code
     * @param string $field field of table to filter on
     * @param string $criterion search criterion
     * @param string $string search string
     * @return array containing SQL code
     */
    function convertSearch2SQL($field, $criterion, $string)
    {
        $result = array();

        if ($string != '') {
            switch ($criterion) {
                case "contains":
                    $search_clause = "(" . $field . " LIKE '%" . $string . "%')";
                    break;
                case "not_contain":
                    $search_clause = "(" . $field . " NOT LIKE '%" . $string . "%')";
                    break;
                case "equals":
                    $search_clause = "(" . $field . "='" . $string . "')";
                    break;
                case "not_equal":
                    $search_clause = "NOT (" . $field . "='" . $string . "')";
                    break;
                default:
                    $search_clause = "";
            }
            array_push($result, $search_clause);
        }
        return $result;
    }

    /**
     * Convert array of mail address(es) into SQL search clause
     * @param array $emailaddresses list of email address(es)
     * @return string containing SQL code
     */
    function convertEmailaddresses2SQL($emailaddresses)
    {
        global $conf;
        $result = '';
        $in_sep = "','";

        if ( !empty($emailaddresses)) {
            // the simplest solution if a value is not array but we need an array
            if (! is_array($emailaddresses)) {
                $emailaddresses = array( $emailaddresses );
            }
            // use implode with a 3letter separator from a variable
            $result = " recip.email  IN ( '". implode($in_sep, $emailaddresses) ."' ) ";

            // Configured to support recipient delimiters?
            if (!empty($conf['recipient_delimiter'])) {
                $delimiter = $conf['recipient_delimiter'];
                foreach ($emailaddresses as $value) {
                    // separate localpart and domain
                    list($localpart, $domain) = explode("@", $value);
                    // Append any recipient delimited addresses
                    $result .= "OR recip.email LIKE '$localpart$delimiter%@$domain' ";
                }
            }
        }
        // Return results within parentheses to isolate OR statements
        return "($result)";
    }
}

?>
