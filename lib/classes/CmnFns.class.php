<?php
/**
 * CmnFns class
 *
 * These functions common to most pages
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2024-12-24
 * @package CmnFns
 *
 * Copyright (C) 2024 mailzu-ng
 * License: GPL, see LICENSE
 */
/**
 * Base directory of application
 */
if ( ! defined('BASE_DIR') ) {
    @define('BASE_DIR', __DIR__ . '/../..');
}

// Define constants for method getGlobalVar()
@define('INORDER', 0);
@define('GET', 1);
@define('POST', 2);
@define('SESSION', 3);
@define('SERVER', 4);
@define('FORM', 5);

/**
 * Provides functions common to most pages
 */
class CmnFns
{
    /**
     * Convert minutes to hours
     * @param double $time time to convert in minutes
     * @return string time in 12 hour time
     */
    public static function formatTime($time)
    {
        global $conf;

        // Set up time array with $timeArray[0]=hour, $timeArray[1]=minute
        // If time does not contain decimal point
        // then set time array manually
        // else explode on the decimal point
        $hour = intval($time / 60);
        $min = $time % 60;
        if ($conf['app']['timeFormat'] == 24) {
            $a = '';                // AM/PM does not exist
            if ($hour < 10) $hour = '0' . $hour;
        } else {
            $a = ($hour < 12 || $hour == 24) ? translate('am') : translate('pm');    // Set am/pm
            if ($hour > 12) $hour = $hour - 12;    // Take out of 24hr clock
            if ($hour == 0) $hour = 12;        // Don't show 0hr, show 12 am
        }
        // Set proper minutes (the same for 12/24 format)
        if ($min < 10) $min = 0 . $min;
        // Put into a string and return
        return $hour . ':' . $min . $a;
    }

    /**
     * Convert ISO8601 date to date format
     * @param string $date string (yyyy-mm-dd)
     * @return int timestamp
     */
    public static function formatDateISO($date)
    {
        $time = strtotime($date);
        return $time;
    }

    /**
     * Convert timestamp to date format
     * @param string $date timestamp
     * @param string $format format to put datestamp into
     * @return string date as $format or as default format
     */
    public static function formatDate($date, $format = '')
    {
        global $dates;

        if (empty($format)) $format = $dates['general_date'];
        return strftime($format, $date);
    }

    /**
     * Convert UNIX timestamp to datetime format
     * @param string $ts MySQL timestamp
     * @param string $format format to put datestamp into
     * @return string date/time as $format or as default format
     */
    public static function formatDateTime($ts, $format = '')
    {
        global $conf;
        global $dates;

        if (empty($format))
            $format = $dates['general_datetime'] . ' ' . (($conf['app']['timeFormat'] == 24) ? '%H' : '%I') . ':%M:%S' . (($conf['app']['timeFormat'] == 24) ? '' : ' %p');
        return strftime($format, $ts);
    }

    /**
     * Convert minutes to hours/minutes
     * @param int $minutes minutes to convert
     * @return string version of hours and minutes
     */
    public static function minutes_to_hours($minutes)
    {
        if ($minutes == 0)
            return '0 ' . translate('hours');

        $hours = (intval($minutes / 60) != 0) ? intval($minutes / 60) . ' ' . translate('hours') : '';
        $min = (intval($minutes % 60) != 0) ? intval($minutes % 60) . ' ' . translate('minutes') : '';
        return ($hours . ' ' . $min);
    }

    /**
     * Return the current script URL directory
     * @param none
     * @return url url of curent script directory
     */
    public static function getScriptURL()
    {
        global $conf;
        $uri = $conf['app']['weburi'];
        return (strrpos($uri, '/') === false) ? $uri : substr($uri, 0, strlen($uri));
    }

    /**
     * Prints an error message box and kills the app
     * @param string $msg error message to print
     * @param string $style inline CSS style definition to apply to box
     * @param boolean $die whether to kill the app or not
     */
    public static function do_error_box($msg, $style = '', $die = true)
    {
        global $conf;

        echo '<table border="0" cellspacing="0" cellpadding="0" align="center" class="alert" style="' . $style . '"><tr><td>' . $msg . '</td></tr></table>';

        if ($die) {
            echo '</td></tr></table>';        // endMain() in Template
            echo '<p align="center"><a href="http://www.mailzu.net">' . $conf['app']['title'] . ' v' . $conf['app']['version'] . '</a></p></body></html>';    // printHTMLFooter() in Template

            //$t = new Template();
            //$t->endMain();
            //$t->printHTMLFooter();
            die();
        }
    }

    /**
     * Prints out a box with notification message
     * @param string $msg message to print out
     * @param string $style inline CSS style definition to apply to box
     */
    public static function do_message_box($msg, $style = '')
    {
        echo '<table border="0" cellspacing="0" cellpadding="0" align="center" class="message" style="' . $style . '"><tr><td>' . $msg . '</td></tr></table>';
    }

    /**
     * Returns a reference to a new Link object
     * Used to make HTML links
     * @param none
     * @return Link object
     */
    public static function getNewLink()
    {
        return new Link();
    }

    /**
     * Returns a reference to a new Pager object
     * Used to iterate over limited recordesets
     * @param none
     * @return Pager object
     */
    public static function getNewPager()
    {
        return new Pager();
    }

    /**
     * Strip out slahses from POST values
     * @param none
     * @return array of cleaned up POST values
     */
    public static function cleanPostVals()
    {
        $rval = array();

        foreach ($_POST as $key => $val)
            $rval[$key] = stripslashes(trim($val));

        return $rval;
    }

    /**
     * Strip out slahses from an array of data
     * @param none
     * @return array of cleaned up data
     */
    public static function cleanVals($data)
    {
        $rval = array();

        foreach ($data as $key => $val)
            $rval[$key] = stripslashes($val);

        return $rval;
    }

    /**
     * Verifies vertical order and returns value
     * @param string $vert value of vertical order
     * @return string vertical order
     */
    public static function get_vert_order($get_name = 'vert')
    {
        // If no vertical value is specified, use DESC
        $vert = isset($_GET[$get_name]) ? $_GET[$get_name] : 'DESC';

        // Validate vert value, default to DESC if invalid
        switch ($vert) {
            case 'DESC';
            case 'ASC';
                break;
            default :
                $vert = 'DESC';
                break;
        }

        return $vert;
    }

    /**
     * Verifies and returns the order to list recordset results by
     * If none of the values are valid, it will return the 1st element in the array
     * @param array $orders all valid order names
     * @return string order of recorset
     */
    public static function get_value_order($orders = array(), $get_name = 'order')
    {
        if (empty($orders))        // Return null if the order array is empty
            return NULL;

        // Set default order value
        // If a value is specifed in GET, use that.  Else use the first element in the array
        $order = isset($_GET[$get_name]) ? $_GET[$get_name] : $orders[0];

        if (in_array($order, $orders))
            $order = $order;
        else
            $order = $orders[0];

        return $order;
    }

    /**
     * Opposite of php's nl2br function.
     * Subs in a newline for all brs
     * @param string $subject line to make subs on
     * @return reformatted line
     */
    public static function br2nl($subject)
    {
        return str_replace('<br />', "\n", $subject);
    }

    /**
     * Writes a log string to the log file specified in config.php
     * @param string $string log entry to write to file
     * @param string $userid memeber id of user performing the action
     * @param string $ip ip address of user performing the action
     */
    public static function write_log($string, $userid = NULL, $ip = NULL)
    {
        global $conf;
        $delim = "\t";
        $file = $conf['app']['logfile'];
        $values = '';

        if (!$conf['app']['use_log'])        // Return if we aren't going to log
            return;

        if (empty($ip))
            $ip = $_SERVER['REMOTE_ADDR'];

        clearstatcache();            // Clear cached results

        if (!is_dir(dirname($file)))
            mkdir(dirname($file), 0777);    // Create the directory

        if (!touch($file))
            return;                // Return if we cant touch the file

        if (!$fp = fopen($file, 'a'))
            return;                // Return if the fopen fails

        flock($fp, LOCK_EX);            // Lock file for writing
        if (!fwrite($fp, '[' . date('D, d M Y H:i:s') . ']' . $delim . $ip . $delim . $userid . $delim . $string . "\r\n"))    // Write log entry
            return;                    // Return if we cant write to the file
        flock($fp, LOCK_UN);            // Unlock file
        fclose($fp);
    }

    /**
     * Returns the day name
     * @param int $day_of_week day of the week
     * @param int $type how to return the day name (0 = full, 1 = one letter, 2 = two letter, 3 = three letter)
     */
    public static function get_day_name($day_of_week, $type = 0)
    {
        global $days_full;
        global $days_abbr;
        global $days_letter;
        global $days_two;

        $names = array(
            $days_full, $days_letter, $days_two, $days_letter
            /*
            array ('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            array ('S', 'M', 'T', 'W', 'T', 'F', 'S'),
            array ('Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'),
            array ('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat')
            */
        );

        return $names[$type][$day_of_week];
    }

    /**
     * Redirects a user to a new location
     * @param string $location new http location
     * @param int $time time in seconds to wait before redirect
     */
    public static function redirect($location, $time = 0, $die = true)
    {
        header("Refresh: $time; URL=$location");
        if ($die) exit;
    }

    /**
     * Prints out the HTML to choose a language
     * @param none
     */
    public static function print_language_pulldown()
    {
        global $conf;
        ?>
        <select name="language" class="textbox" onchange="changeLanguage(this);">
            <?php
            $languages = get_language_list();
            foreach ($languages as $lang => $settings) {
                echo '<option value="' . $lang . '"'
                    . ((determine_language() == $lang) ? ' selected="selected"' : '')
                    . '>' . $settings[3] . ($lang == $conf['app']['defaultLanguage'] ? ' ' . translate('(Default)') : '') . "</option>\n";
            }
            ?>
        </select>
        <?php
    }

    /**
     * Searches the input string and creates links out of any properly formatted 'URL-like' text
     * Written by Fredrik Kristiansen (russlndr at online.no)
     * and Albrecht Guenther (ag at phprojekt.de).
     * @param string $str string to search for links to create
     * @return string with 'URL-like' text changed into clickable links
     */
    public static function html_activate_links($str)
    {
        $str = preg_replace('/(((f|ht){1}tp:\/\/)[-a-zA-Z0-9@:%_+.~#?&\/\/=]+)/i', '<a href="\1" target="_blank">\1</a>', $str);
        $str = preg_replace('/([[:space:]()[{}])(www.[-a-zA-Z0-9@:%_+.~#?&\/\/=]+)/i', '\1<a href="http://\2" target="_blank">\2</a>', $str);
        $str = preg_replace('/([_.0-9a-z-]+@([0-9a-z][0-9a-z-]+.)+[a-z]{2,3})/i', '<a href="mailto:\1">\1</a>', $str);
        return $str;
    }

    /**
     * Verifies current page number and returns value
     * @param integer $page value of current page number
     * @return integer current page number
     */
    public static function get_current_page_number($get_name = 'page')
    {
        // If no page number is specified, use 0
        $page = (isset($_GET[$get_name]) && is_numeric($_GET[$get_name])) ? $_GET[$get_name] : 0;
        return $page;
    }

    /**
     * Gets the requested mail_id
     * @param none
     * @return string mail_id
     */
    public static function get_mail_id($get_name = 'mail_id')
    {
        // If there isnt one set, return NULL
        $mail_id = (isset($_GET[$get_name])) ? $_GET[$get_name] : NULL;
        return $mail_id;
    }

    /**
     * Verifies and returns the order to list recordset results by
     * /**
     * Convert an array to a query string
     * @param array $array
     * @param array $exclude_vars to be excluded from the resulting string
     * @param boolean $url_encode_ampersands
     */
    public static function array_to_query_string($array, $exclude_vars = array(), $url_encode_ampersands = true)
    {
        if (!is_array($array))
            return '';
        if (!$array)
            return '';
        $str = '';
        $i = 0;
        foreach ($array as $name => $val) {
            if (!in_array($name, $exclude_vars)) {
                if ($i > 0)
                    if ($url_encode_ampersands)
                        $str .= '&amp;';
                    else
                        $str .= '&';
                $str .= urlencode($name) . '=' . urlencode($val);
                $i++;
            }
        }
        return $str;
    }

    /**
     * Generate HTML for multipage links
     * @param integer $page current page
     * @param integer $sizeLimit maximum number of messages per page
     * @param integer $count total number of messages
     */
    public static function genMultiPagesLinks($page, $sizeLimit, $count)
    {
        global $link;

        $total_pages = $count / $sizeLimit;

        $php_self = $_SERVER['PHP_SELF'];

        if ($page != 0) {
            $query_string = CmnFns::array_to_query_string($_GET, array('page'));
            $query_string_first = $query_string . '&page=0';
            $query_string_previous = $query_string . '&page=' . ($page - 1);
            $pager_html = $link->getLink($php_self . '?' . $query_string_first, translate('first'), '', '', translate('Go to first page')) . " | ";
            $pager_html .= $link->getLink($php_self . '?' . $query_string_previous, translate('previous'), '', '', translate('Go to previous page')) . " | ";
        } else {
            $pager_html = translate('first') . " | " . translate('previous') . " | ";
        }

        $pager_html .= '&nbsp;&nbsp;';

        // for large search results where we page beyone the first 20 pages,
        // print elipsis instead of making the pager be super wide.
        $elipsis_printed = false;

        for ($i = 0; $i < $count; $i += $sizeLimit) {
            $page_num = $i / $sizeLimit;
            if ($count > $sizeLimit * 20 && abs($page_num - $page) > 10) {
                if (!$elipsis_printed) {
                    $pager_html .= '...&nbsp;&nbsp;';
                    $elipsis_printed = true;
                }
            } else if ($page == $page_num) {
                $pager_html .= '<b>' . ($page_num + 1) . '</b>';
                $pager_html .= '&nbsp;&nbsp;';
                $elipsis_printed = false;
            } else {
                $query_string = CmnFns::array_to_query_string($_GET, array('page'));
                $query_string .= '&page=' . $page_num;
                $pager_html .= $link->getLink($php_self . '?' . $query_string, ($page_num + 1), '', '', translate('Go to page') . ' ' . ($page_num + 1));
                $pager_html .= '&nbsp;&nbsp;';
                $elipsis_printed = false;
            }
        }

        if ($page + 1 < $total_pages) {
            $query_string = CmnFns::array_to_query_string($_GET, array('page'));
            $query_string_next = $query_string . '&page=' . ($page + 1);
            $query_string_last = $query_string . '&page=' . (ceil($total_pages) - 1);
            $pager_html .= ' | ' . $link->getLink($php_self . '?' . $query_string_next, strtolower(translate('Next')), '', '', translate('Go to next page'));
            $pager_html .= ' | ' . $link->getLink($php_self . '?' . $query_string_last, translate('last'), '', '', translate('Go to last page'));
        } else {
            $pager_html .= " | " . strtolower(translate('Next')) . " | " . translate('last');
        }

        return $pager_html;
    }

    /**
     * Generate HTML for search engine
     * @param $content_type : 'B' (attachment) or 'S' (spam)
     */
    public static function searchEngine($content_type, $submit_page, $full_search = false)
    {
        global $conf;

        $fields_array = array("f" => translate('From'),
            "s" => translate('Subject')
        );
        if (Auth::isMailAdmin() || $conf['app']['allowMailid']) {
            $fields_array = array_merge(array("m" => "Mail ID"), $fields_array);
        }
        if ($full_search) $fields_array = array_merge(array("t" => translate('To')), $fields_array);

        ?>
        <table id="searchenginetbl" border=0 width="100%">
            <tbody>
            <form action="<?php echo $submit_page ?>" method="get" name="quarantine">
                <tr>
                    <td colspan=2 align="center"><?php echo translate('Search for messages whose:'); ?>&nbsp;</td>
                </tr>
                <tr>
                    <td class="alignright">&nbsp;
                        <?php
                        $i = 1;
                        $array_size = count($fields_array);
                        foreach ($fields_array as $k => $name) {
                            echo "\t\t\t$name: \n";
                            echo "\t\t\t<select name='" . $k . "_criterion' class='button'>\n";
                            echo "\t\t\t<option value='contains'";
                            echo "contains" == CmnFns::getGlobalVar($k . '_criterion', GET) ? " selected='true'>" : ">";
                            echo translate('contains') . "</option>\n";
                            echo "\t\t\t<option value='not_contain'";
                            echo "not_contain" == CmnFns::getGlobalVar($k . '_criterion', GET) ? " selected='true'>" : ">";
                            echo translate('doesn\'t contain') . "</option>\n";
                            echo "\t\t\t<option value='equals'";
                            echo "equals" == CmnFns::getGlobalVar($k . '_criterion', GET) ? " selected='true'>" : ">";
                            echo translate('equals') . "</option>\n";
                            echo "\t\t\t<option value='not_equal'";
                            echo "not_equal" == CmnFns::getGlobalVar($k . '_criterion', GET) ? " selected='true'>" : ">";
                            echo translate('doesn\'t equal') . "</option>\n";
                            echo "\t\t\t</select>\n";
                            echo "\t\t\t<input type='text' name='" . $k . "_string' size='20' value='"
                                . CmnFns::getGlobalVar($k . '_string', GET) . "' />\n";
                            echo ($i % 2) ? "\t\t\t&nbsp;</td>\n\t\t\t<td class='alignleft'>&nbsp\n" : "\t\t\t&nbsp;</td></tr>\n\t\t\t<tr><td class='alignright'>&nbsp\n";
                            $i++;
                        }
                        ?>
                        <?php echo translate('Content Type'); ?>:
                        <select name="ctype" class="button">
                            <option value="A" <?php echo($content_type == 'A' ? ' selected="true"' : ''); ?>>
                                <?php echo translate('All'); ?></option>
                            <option value="S" <?php echo($content_type == 'S' ? ' selected="true"' : ''); ?>>
                                <?php echo translate('Spam'); ?></option>
                            <option value="B" <?php echo($content_type == 'B' ? ' selected="true"' : ''); ?>>
                                <?php echo translate('Banned'); ?></option>
                            <?php if (Auth::isMailAdmin() || $conf['app']['allowViruses']) { ?>
                                <option value="V" <?php echo($content_type == 'V' ? ' selected="true"' : ''); ?>>
                                    <?php echo translate('Virus'); ?></option>
                            <?php }
                            if (Auth::isMailAdmin() || $conf['app']['allowBadHeaders']) { ?>
                                <option value="H" <?php echo($content_type == 'H' ? ' selected="true"' : ''); ?>>
                                    <?php echo translate('Bad Header'); ?></option>
                            <?php }
                            echo "</select>";
                            $i++;
                            echo ($i % 2) ? "&nbsp;</td></tr>\n\t\t\t<tr><td colspan='2' align='center'>&nbsp\n" : "&nbsp;</td><td class='alignleft'>&nbsp";
                            ?>
                            <input type="submit" class="button" name="search_action"
                                   value="<?php echo translate('Search'); ?>"/>
                            <?php if (CmnFns::didSearch())
                                echo "<input type=\"submit\" class=\"button\" name=\"search_action\" value=\"" . translate('Clear search results') . "\" />";
                            ?>
                            &nbsp;</td>
                </tr>
            </form>
            </tbody>
        </table>
        <?php

    }

    /**
     * Did we do a search?
     * @param none
     * @return value boolean
     */
    public static function didSearch()
    {
        $return = false;
        $strings = array('f_string', 's_string', 't_string', 'm_string');
        foreach ($strings as $string) {
            if (CmnFns::getGlobalVar($string, GET) != '') $return = true;
        }
        return $return;
    }

    /**
     * Function that convert $_GET into query string and exclude array
     * @param array of variables to exclude
     * @return query string
     */
    public static function querystring_exclude_vars($excl_array = array())
    {
        return CmnFns::array_to_query_string($_GET, $excl_array);
    }

    /**
     * Gets the 'ctype' value
     * @param none
     * @return value
     */
    public static function get_ctype($get_name = 'ctype')
    {
        // If there isnt one set, return NULL
        $result = NULL;
        if (isset($_GET[$get_name]))
            $result = $_GET[$get_name];
        elseif (isset($_POST[$get_name]))
            $result = $_POST[$get_name];
        return $result;
    }

    /**
     * Gets the 'action' value
     * @param none
     * @return value
     */
    public static function get_action($get_name = 'action')
    {
        // If there isnt one set, return NULL
        $result = (isset($_POST[$get_name])) ? $_POST[$get_name] : NULL;
        return $result;
    }

    /**
     * Gets the 'query_string' value
     * @param none
     * @return value
     */
    public static function get_query_string($get_name = 'query_string')
    {
        // If there isnt one set, return NULL
        $result = (isset($_POST[$get_name])) ? $_POST[$get_name] : NULL;
        return $result;
    }

    /*
     * Search for the var $name in $_SESSION, $_POST, $_GET,
     * $_SERVER and set it in provided var.
     *
     * If $search is not provided,  or == INORDER, it will search
     * $_SESSION, then $_POST, then $_GET. Otherwise,
     * use one of the defined constants to look for
     * a var in one place specifically.
     *
     * Note: $search is an int value equal to one of the
     * constants defined above.
     *
     * example:
     *    getGlobalVar('page', GET);
     *  -- no quotes around last param!
     *
     * @param string $name the name of the var to search
     * @param int search constant defining where to look:
    *	INORDER, SESSION, FORM, POST, GET, SERVER
     * @return value of var
     */
    public static function getGlobalVar($name, $search = INORDER)
    {
        switch ($search) {
            /* we want the default case to be first here,
                   so that if a valid value isn't specified,
                   all four arrays will be searched. */
            default:
            case INORDER: // check session, post, get
            case SESSION:
                if (isset($_SESSION[$name]))
                    return $_SESSION[$name];
                elseif ($search == SESSION)
                    break;
            // fall through
            case FORM: // check post, get
            case POST:
                if (isset($_POST[$name]))
                    return $_POST[$name];
                elseif ($search == POST)
                    break;
            // fall through
            case GET:
                if (isset($_GET[$name]))
                    return $_GET[$name];
                /* For INORDER case, exit after GET */
                break;
            case SERVER:
                if (isset($_SERVER[$name]))
                    return $_SERVER[$name];
                break;
        }
        return NULL;
    }

    /*
    * Redirect using javascript
    * @param $location string
    */
    public static function redirect_js($location)
    {
        echo "<SCRIPT LANGUAGE=\"JavaScript\">";
        echo "parent.location.href = '" . $location . "';";
        echo "</SCRIPT>";
    }
}

?>
