<?php
/**
 * MailMime class
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2024-12-24
 * @package MailMime
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
 * Provide all MIME functionality
 */

class MailMime
{

    var $filelist = array();
    var $fileContent = array();
    var $errors = array();


    /**
     * Get full MIME type
     * $param The mime structure object
     */
    public static function GetCtype($struct)
    {
        $ctype_p = strtolower(trim($struct->ctype_primary));
        $ctype_s = strtolower(trim($struct->ctype_secondary));
        $type = $ctype_p . '/' . $ctype_s;
        return $type;
    }

    /**
     * Print text of text/plain MIME entity
     * $param The body of a mime structure object
     */
    public static function MsgBodyPlainText($text)
    {
    //	echo nl2br(htmlspecialchars($text));
        echo "<pre>";
        echo htmlspecialchars($text);
        echo "</pre>";
    }

    /**
     * Print HTML of text/html MIME entity
     * $param The body of a mime structure object
     */
    public static function MsgBodyHtmlText($text, $load_images = false)
    {
        if ( $load_images) {
            $block_external_images = false;
        } else {
            $block_external_images = true;
        }
        if (isset($_COOKIE['lang']) && file_exists("img/" . substr($_COOKIE['lang'], 0, 2) . ".blocked_img.png")) {
            $secremoveimg = "img/" . substr($_COOKIE['lang'], 0, 2) . ".blocked_img.png";
        } else {
            $secremoveimg = "img/blocked_img.png";
        }
        echo self::bodyHTMLFilter($text, $secremoveimg, $block_external_images);
    }

    /**
     * Recursively parse MIME structure
     * $param The mime structure object
     */

    public static function MsgParseBody($struct, $getAttachmentContent = false, $load_images = false)
    {
        global $filelist, $fileContent;
        global $errors;

        $ctype_p = strtolower(trim($struct->ctype_primary));
        $ctype_s = strtolower(trim($struct->ctype_secondary));

        switch ($ctype_p) {
            case "multipart":
                switch ($ctype_s) {
                    case "alternative":
                        // Handle multipart/alternative parts
                        $alt_entity = self::FindMultiAlt($struct->parts);
                        // Ignore if we return false NEEDS WORK
                        if ($alt_entity) self::MsgParseBody($alt_entity, $getAttachmentContent, $load_images);
                        break;
                    case "related":
                        // Handle multipart/related parts
                        $rel_entities = self::FindMultiRel($struct);
                        foreach ($rel_entities as $ent) {
                            self::MsgParseBody($ent, $getAttachmentContent, $load_images);
                        }
                        break;
                    default:
                        // Probably multipart/mixed here
                        // Recursively process nested mime entities
                        if (is_array($struct->parts) || is_object($struct->parts)) {
                            foreach ($struct->parts as $cur_part) {
                                self::MsgParseBody($cur_part, $getAttachmentContent, $load_images);
                            }
                        } else {
                            $errors['Invalid or Corrupt MIME Detected.'] = true;
                        }
                        break;
                }
                break;
            case "text":
                // Do not display attached text types
                if (property_exists($struct, "d_parameters")) {
                    if ($attachment = $struct->d_parameters['filename'] or $attachment = $struct->d_parameters['name']) {
                        array_push($filelist, $attachment);
                        if ($getAttachmentContent)
                            $fileContent[] = $struct->body;
                        break;
                    }
                }
                switch ($ctype_s) {
                    // Plain text
                    case "plain":
                        if (! $getAttachmentContent)
                            self::MsgBodyPlainText($struct->body);
                        break;
                    // HTML text
                    case "html":
                        if (! $getAttachmentContent)
                            self::MsgBodyHtmlText($struct->body, $load_images);
                        break;
                    // Text type we do not support
                    default:
                        $errors['Portions of text could not be displayed'] = true;
                }
                break;
            default:
                // Save the listed filename or notify the
                // reader that this mail is not displayed completely
                if (property_exists($struct, "d_parameters")) {
                    if (property_exists($struct, "disposition") && $struct->disposition == "attachment" ) {
                        if ($attachment = $struct->d_parameters['filename'] or $attachment = $struct->d_parameters['name']) {
                            if ( ! @is_array($filelist) ) { $filelist = array(); }
                            array_push($filelist, $attachment);
                            if ($getAttachmentContent)
                                $fileContent[] = $struct->body;
                        } else {
                            $errors['Unsupported MIME objects present'] = true;
                        }
                    } else if (property_exists($struct, "disposition") && $struct->disposition == "inline" && isset($struct->headers['content-id']) && ($struct->ctype_primary == 'image') ) {
                        if ($attachment = trim($struct->headers['content-id'],'<>')) {
                            if ( ! @is_array($filelist) ) { $filelist = array(); }
                            $filelist[$attachment]['name'] = $struct->ctype_parameters['name'];
                            $filelist[$attachment]['cid'] = trim($struct->headers['content-id'],'<>');
                            $fileContent[$attachment]['body'] = $struct->body;
                            $fileContent[$attachment]['ctype'] = $struct->ctype_primary."/".$struct->ctype_secondary;
                        }
                    }
                } else if ( isset($struct->headers['content-id']) && ($struct->ctype_primary == 'image') ) {
                    if ($attachment = trim($struct->headers['content-id'],'<>')) {
                        if ( ! @is_array($filelist) ) { $filelist = array(); }
                        $filelist[$attachment]['name'] = $struct->ctype_parameters['name'];
                        $filelist[$attachment]['cid'] = trim($struct->headers['content-id'],'<>');
                        $fileContent[$attachment]['body'] = $struct->body;
                        $fileContent[$attachment]['ctype'] = $struct->ctype_primary."/".$struct->ctype_secondary;
                    }
                }
        }
    }

    /**
     * Get the best MIME entity for multipart/alternative
     * Adapted from SqurrelMail
     * $param Array of MIME entities
     * $return Single MIME entity
     */
    public static function FindMultiAlt($parts)
    {
        $alt_pref = array('text/plain', 'text/html');
        $best_view = 0;
        // Bad Headers sometimes have invalid MIME....
        if (is_array($parts) || is_object($parts)) {
            foreach ($parts as $cur_part) {
                $type = self::GetCtype($cur_part);
                if ($type == 'multipart/related') {
                        @$type = $cur_part->d_parameters['type'];
                    // Mozilla bug. Mozilla does not provide the parameter type.
                    if (!$type) $type = 'text/html';
                }
                $altCount = count($alt_pref);
                for ($j = $best_view; $j < $altCount; ++$j) {
                    if (($alt_pref[$j] == $type) && ($j >= $best_view)) {
                        $best_view = $j;
                        $struct = $cur_part;
                    }
                }
            }
            return $struct;
        } else {
            $errors['Invalid or Corrupt MIME Detected.'] = true;
        }
    }

    /**
     * Get the list of related entities for multipart/related
     * Adapted from SqurrelMail
     * $param multipart/alternative structure
     * @return List of MIME entities
     */
    public static function FindMultiRel($struct)
    {
        $entities = array();
        $type = true;
        if (property_exists($struct, "d_parameters")) {
            $type = $struct->d_parameters['type'];
        }
        // Mozilla bug. Mozilla does not provide the parameter type.
        if (!$type) $type = 'text/html';
        // Bad Headers sometimes have invalid MIME....
        if (is_array($struct->parts) || is_object($struct->parts)) {
            foreach ($struct->parts as $part) {
                if (self::GetCtype($part) == $type || self::GetCtype($part) == "multipart/alternative") {
                    array_push($entities, $part);
                }
            }
        } else {
            $errors['Invalid or Corrupt MIME Detected.'] = true;
        }
        return $entities;
    }

    // Wrapper function for htmlfilter, taken from src

    public static function bodyHTMLFilter($body, $trans_image_path, $block_external_images = false)
    {
    $mail_id = CmnFns::get_mail_id();
    $recip_email = CmnFns::getGlobalVar('recip_email', GET);
    $query_string = CmnFns::querystring_exclude_vars(array('mail_id', 'recip_email'));


        $tag_list = array(
            false,
            "object",
            "meta",
            "html",
            "head",
            "base",
            "link",
            "frame",
            "iframe",
            "plaintext",
            "marquee"
        );

        $rm_tags_with_content = array(
            "script",
            "applet",
            "embed",
            "title",
            "frameset",
            "xmp",
            "xml"
        );

        $self_closing_tags =  array(
            "img",
            "br",
            "hr",
            "input",
            "outbind"
        );

        $force_tag_closing = true;

        $rm_attnames = array(
            "/.*/" =>
                array(
                    // "/target/i",
                    "/^on.*/i",
                    "/^dynsrc/i",
                    "/^data.*/i",
                    "/^lowsrc.*/i"
                )
        );

        $bad_attvals = array(
            "/.*/" =>
            array(
                "/^src|background/i" =>
                array(
                    array(
                        '/^([\'"])\s*\S+script\s*:.*([\'"])/si',
                        '/^([\'"])\s*mocha\s*:*.*([\'"])/si',
                        '/^([\'"])\s*about\s*:.*([\'"])/si'
                    ),
                    array(
                        "\\1$trans_image_path\\2",
                        "\\1$trans_image_path\\2",
                        "\\1$trans_image_path\\2"
                    )
                ),
                "/^href|action/i" =>
                array(
                    array(
                        '/^([\'"])\s*\S+script\s*:.*([\'"])/si',
                        '/^([\'"])\s*mocha\s*:*.*([\'"])/si',
                        '/^([\'"])\s*about\s*:.*([\'"])/si'
                    ),
                    array(
                        "\\1#\\1",
                        "\\1#\\1",
                        "\\1#\\1"
                    )
                ),
                "/^style/i" =>
                array(
                    array(
                        "/\/\*.*\*\//",
                        "/expression/i",
                        "/binding/i",
                        "/behaviou*r/i",
                        "/include-source/i",
                        '/position\s*:/i',
                        '/(\\\\)?u(\\\\)?r(\\\\)?l(\\\\)?/i',
                        '/url\s*\(\s*([\'"])\s*\S+script\s*:.*([\'"])\s*\)/si',
                        '/url\s*\(\s*([\'"])\s*mocha\s*:.*([\'"])\s*\)/si',
                        '/url\s*\(\s*([\'"])\s*about\s*:.*([\'"])\s*\)/si',
                        '/(.*)\s*:\s*url\s*\(\s*([\'"]*)\s*\S+script\s*:.*([\'"]*)\s*\)/si'
                    ),
                    array(
                        "",
                        "idiocy",
                        "idiocy",
                        "idiocy",
                        "idiocy",
                        "idiocy",
                        "url",
                        "url(\\1#\\1)",
                        "url(\\1#\\1)",
                        "url(\\1#\\1)",
                        "\\1:url(\\2#\\3)"
                    )
                )
            )
        );

        if ($block_external_images) {
            array_push(
                $bad_attvals['/.*/']['/^src|background/i'][0],
                '/^([\'\"])\s*https*:.*([\'\"])/si'
            );
            array_push(
                $bad_attvals['/.*/']['/^src|background/i'][1],
                "\\1$trans_image_path\\1"
            );
            array_push(
                $bad_attvals['/.*/']['/^src|background/i'][0],
                '/^([\'\"])\s*cid*:.*([\'\"])/si'
            );
            array_push(
                $bad_attvals['/.*/']['/^src|background/i'][1],
                "\\1$trans_image_path\\1"
            );
            array_push(
                $bad_attvals['/.*/']['/^style/i'][0],
                '/url\(([\'\"])\s*https*:.*([\'\"])\)/si'
            );
            array_push(
                $bad_attvals['/.*/']['/^style/i'][1],
                "url(\\1$trans_image_path\\1)"
            );
        } else {
            array_push(
                $bad_attvals['/.*/']['/^src|background/i'][0],
                '/^([\'\"])\s*cid*:(.*)([\'\"])/si'
            );
            array_push(
                $bad_attvals['/.*/']['/^src|background/i'][1],
                "\\1get_attachment.php?mail_id=" . urlencode($mail_id) . "&amp;recip_email=" . urlencode($recip_email) . "&amp;fileid=\\2&amp;d_inline=1" . "&amp;".$query_string."\\3"
            );
        }

        $add_attr_to_tag = array(
            "/^a$/i" =>
                array('target' => '"_blank"')
        );

        $trusted = sanitize(
            $body,
            $tag_list,
            $rm_tags_with_content,
            $self_closing_tags,
            $force_tag_closing,
            $rm_attnames,
            $bad_attvals,
            $add_attr_to_tag,
            $trans_image_path,
            $block_external_images
        );
        return $trusted;
    }
}

