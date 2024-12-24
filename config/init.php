<?php
/**
 * Initialization file.
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2021-11-08
 * @package mailzu-ng
 *
 * Copyright (C) 2021 mailzu-ng
 * License: GPL, see LICENSE
 */

/**
 * Please refer to readme.html and LICENSE for any additional information
 *
 * Copyright (C) 2003 - 2021 MailZu
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the
 * Free Software Foundation, Inc.
 * 59 Temple Place
 * Suite 330
 * Boston, MA
 * 02111-1307
 * USA
 */

/********************************************************************/
/*                   DO NOT CHANGE THIS SECTION                     */
/********************************************************************/
// Start the session (do not edit!)
if (! headers_sent()) {
    if(session_status() !== PHP_SESSION_ACTIVE) session_start();
}

$conf['app']['version'] = '0.11.mailzu-ng-php72-37e0277+1';
$conf['app']['footlink'] = 'https://github.com/gnanet/mailzu';

include_once('constants.php');
include_once('langs.php');

if ($lang = determine_language()) {    // Functions exist in the langs.php file
    set_language($lang);
    load_language_file($lang);
}


?>
