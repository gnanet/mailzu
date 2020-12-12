<?php
/**
 * Initialization file.  Please do not edit.
 * @author Nick Korbel <lqqkout13@users.sourceforge.net>
 * @version 29-08-2017
 * @package phpScheduleIt
 */
/**
 * Please refer to readme.html and LICENSE for any additional information
 *
 * Copyright (C) 2003 - 2005 phpScheduleIt
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
session_start();

$conf['app']['version'] = '0.9.6-github-gnafixes';
$conf['app']['footlink'] = 'https://github.com/gnanet/mailzu';

include_once('constants.php');
include_once('langs.php');

if ($lang = determine_language()) {    // Functions exist in the langs.php file
    set_language($lang);
    load_language_file($lang);
}
/********************************************************************/
?>
