<?php

/**
 * This file provides output functions
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2024-12-24
 * @package Template
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
/**
 * Provides functions for outputting template HTML
 */
class Template
{
    var $title;
    var $link;
    var $dir_path;

    /**
     * Set the page's title
     * @param string $title title of page
     * @param int $depth depth of the current page relative to MailZu root
     */
    function __construct($title = '', $depth = 0)
    {
        global $conf;

        $this->title = (!empty($title)) ? $title : $conf['ui']['welcome'];
        $this->dir_path = str_repeat('../', $depth);
        $this->link = CmnFns::getNewLink();
        //Auth::Auth();	// Starts session
    }

    /**
     * Print all HTML5 headers
     * This function prints the HTML header code, CSS link, and JavaScript link
     *
     * DOCTYPE is HTML, with viewport defined to allow responsive display
     * @param none
     */
    function printHTMLHeader()
    {
        global $conf;
        global $languages;
        global $lang;
        global $charset;

        $path = $this->dir_path;
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo $languages[$lang][2] ?>">
        <head>
            <title>
                <?php echo $this->title ?>
            </title>
            <meta http-equiv="Content-Type" content="text/html"/>
            <meta charset="<?php echo $charset ?>"/>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="theme-color" content="#0F93DF">
            <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon"/>
            <link rel="icon" sizes="192x192" href="img/mailzu-x4-192x192-icon.png">
            <script language="JavaScript" type="text/javascript" src="<?php echo $path ?>functions.js"></script>
            <style type="text/css">
                @import url("<?php echo $path?>css.css?v=<?php echo md5_file($path.'css.css'); ?>");
            </style>
        </head>
        <body>
        <?php
    }

    /**
     * Print welcome header message
     * This function prints out a table welcoming
     *  the user.  It prints links to My Control Panel,
     *  Log Out, Help, and Email Admin.
     * If the user is the admin, an admin banner will
     *  show up
     * @global $conf
     */
    function printWelcome()
    {
        global $conf;

        // Print out logoImage if it exists
        echo (!empty($conf['ui']['logoImage']))
            ? '<div class="alignleft" ><img src="' . $conf['ui']['logoImage'] . '" alt="logo" vspace="5" /></div>' . "\n"
            : '';
        ?>
        <!-- welcome on -->
        <table width="100%" border="0" cellspacing="0" cellpadding="5" class="mainBorder">
            <tr>
                <td class="mainBkgrdClr">
                    <h4 class="welcomeBack">
                        <?php
                        echo translate('Welcome Back', array($_SESSION['sessionName'], 1));
                        // Notify if the person logged in is admin
                        echo(Auth::isMailAdmin() ? ' (' . translate('Administrator') . ')' : '');
                        ?>
                    </h4>
                </td>
                <td class="mainBkgrdClr" valign="top">
                    <div class="alignright">
                        <p>
                            <?php echo translate_date('header', time()); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>
        <!-- welcome off -->
        <?php
    }

    /**
     * Start main HTML table
     * @param none
     */
function startMain()
{
    ?>
    <p>&nbsp;</p>
    <!-- startmain on -->
<table width="100%" border="0" cellspacing="0" cellpadding="10" style="border: solid #CCCCCC 1px;">
    <tr>
    <td bgcolor="#FAFAFA">
    <!-- startmain off -->
    <?php
}

    /**
     * End main HTML table
     * @param none
     */
function endMain()
{
    ?>
    <!-- endmain on -->
    </td>
    </tr>
</table>
    <!-- endmain off -->
    <?php
}

    /**
     * Print HTML footer
     * This function prints out a tech email
     * link and closes off HTML page
     * @global $conf
     */
    function printHTMLFooter()
    {
        global $conf;
        ?>
        <p align="center"><a href="<?php echo $conf['app']['footlink']; ?>"><?php echo $conf['app']['title']; ?>
                v<?php echo $conf['app']['version']; ?></a></p>
        </body>
        </html>
        <?php
    }

    /**
     * Sets the link class variable to reference a new Link object
     * @param none
     */
    function set_link()
    {
        $this->link = CmnFns::getNewLink();
    }

    /**
     * Returns the link object
     * @param none
     * @return link object for this class
     */
    function get_link()
    {
        return $this->link;
    }

    /**
     * Sets a new title for the template page
     * @param string $title title of page
     */
    function set_title($title)
    {
        $this->title = $title;
    }
}
