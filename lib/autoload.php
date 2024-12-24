<?php
/**
 * This loads configuration, and calls other autoladers
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @package mailzu-ng
 *
 * @version 2021-11-08
 *
 * Copyright (C) 2021 mailzu-ng
 * License: GPL, see LICENSE
 */

/**
 * Base directory of application
 */
if ( ! defined('BASE_DIR') ) {
    @define('BASE_DIR', realpath(__DIR__ . '/..'));
}



/**
 * Include configuration file
 **/
require_once(BASE_DIR . '/config/config.php');
if (!( isset($conf) && is_array($conf) )) {
    echo "conf from ".BASE_DIR."/config/config.php not loaded".PHP_EOL;
    exit();
}

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Pear
 */
if ($conf['app']['safeMode']) {
    ini_set('include_path', (dirname(realpath(__FILE__)) . '/pear/' . PATH_SEPARATOR . ini_get('include_path')));
    include_once('pear/PEAR.php');
    include_once('pear/Net/Socket.php');
    include_once('pear/Mail/mimeDecode.php');
} else {
/*
    if ( @file_exists(BASE_DIR.'/lib/pear/'.'PEAR.php') ) {
        include_once(BASE_DIR.'/lib/pear/'.'PEAR.php');
    } else {
        include_once('PEAR.php');
    }
*/
    if ( @file_exists(BASE_DIR.'/lib/pear/'.'Net/Socket.php') ) {
        include_once(BASE_DIR.'/lib/pear/'.'Net/Socket.php');
    } else {
        include_once('Net/Socket.php');
    }
    if ( @file_exists(BASE_DIR.'/lib/pear/'.'Mail/mimeDecode.php') ) {
        include_once(BASE_DIR.'/lib/pear/'.'Mail/mimeDecode.php');
    } else {
        include_once('Mail/mimeDecode.php');
    }
}

/*
 * Require composer autoloader
 */
if ( @file_exists('../vendor/autoload.php') ) {
    require '../vendor/autoload.php';
} else if ( @file_exists(BASE_DIR . '/vendor/autoload.php') ) {
    require BASE_DIR . '/vendor/autoload.php';
}


if (!function_exists('is_countable')) {
  /**
   * Verify that the content of a variable is an array or an object
   * implementing Countable
   *
   * @param mixed $var The value to check.
   * @return bool Returns TRUE if var is countable, FALSE otherwise.
   */
  function is_countable($var) {
    return is_array($var)
      || $var instanceof \Countable
      || $var instanceof \SimpleXMLElement
      || $var instanceof \ResourceBundle;
  }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

