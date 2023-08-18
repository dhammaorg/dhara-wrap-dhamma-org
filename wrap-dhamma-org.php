<?php

/*
  Plugin Name: wrap-dhamma-org
  Description: retrieves, re-formats, and emits HTML for selected pages from www.dhamma.org
  Version: 3.1
  Authors: Joshua Hartwell <JHartwell@gmail.com> & Jeremy Dunn <jeremy.j.dunn@gmail.com>

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, version 3.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <https://www.gnu.org/licenses/>
 */
add_shortcode( 'wrap-dhamma', 'wrap_dhamma');

function wrap_dhamma($atts) {
    $page = ($atts["page"]);
    $lang = isset($lang) ? $lang : substr(get_bloginfo('language'), 0, 2);
    // validate page
    switch ($page) {
        case 'vipassana':
        case 'code':
        case 'goenka':
        case 'art':
        case 'qanda':
        case 'dscode':
        case 'osguide':
        case 'privacy':
            // JJD 3/12/23 bypass cloudflare - does not allow curl
            $url = 'https://www.dhamma.org/' . $lang . '/' . $page . "?raw";
            //$url = 'https://portal-prod.dhamma.org/' . $lang . '/' . $page . "?raw"; // works from web browser but not curl()
            $text_to_output = pull_page($url, $lang);
            break;

        default:
            die("invalid page '" . $page . "'");
    }

    // emit the required comment
    echo '<!-- ' . $url . ' has been dynamically reformatted on ' . date("D M  j G:i s Y T") . '. -->';

    $text_to_output = fixURLs($text_to_output);
    // emit the reformatted page
    echo $text_to_output;

    echo '<!-- end dynamically generated content.-->';
    // we're done
}

// JJD 5/15/22
function url_get_contents($Url) {
    if (!function_exists('curl_init')) {
        die('CURL is not installed!');
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $Url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function pull_page($url, $lang) {
    // JJD 3/17/23 two options in case server does not allow_url_fopen
    // JJD 8/18/23 #6 catch errors if www.dhamma.org is unavailable
    try {
        $allow_fopen = ini_get('allow_url_fopen');
        if ($allow_fopen) {
            $raw = file_get_contents($url);
        } else {
            $raw = url_get_contents($url);
        }
    } catch (Exception $e) {
        $raw = false;
    }
    if ($raw === false) {
        echo "Error retrieving content.";
    }
    $raw = stripH1($raw);
    return $raw;
}

function fixURLs($raw) {
    $LOCAL_URLS = [
        'art' => '/about/art-of-living/',
        'goenka' => '/about/goenka/',
        'vipassana' => '/about/vipassana/',
        '/' => '',
    ];

    foreach ($LOCAL_URLS as $from => $to) {
        $raw = str_replace('<a href="' . $from . '">', '<a href="' . get_option('home') . $to . '">', $raw);
        $raw = str_replace("<a href='" . $from . "'>", '<a href="' . get_option('home') . $to . '">', $raw);
    }

    $raw = preg_replace("#<a href=[\"']/?code/?[\"']>#", '<a href="' . get_option('home') . '/courses/code/">', $raw);
    $raw = str_replace("<a href='/bycountry/'>", "<a target=\"_blank\" href=\"http://courses.dhamma.org/en-US/schedules/schdhara\">", $raw);
    $raw = str_replace("<a href='/docs/core/code-en.pdf'>here</a>", "<a href='http://www.dhamma.org/en/docs/core/code-en.pdf'>here</a>", $raw);
    $raw = str_replace('"/en/docs/forms/Dhamma.org_Privacy_Policy.pdf"',
            '"https://www.dhamma.org/en/docs/forms/Dhamma.org_Privacy_Policy.pdf"', $raw);
    return $raw;
}

function fixVideoURLS($raw) {
    $raw = preg_replace("#<a href='./intro/#si", "<a href='http://video.server.dhamma.org/video/intro/", $raw);
    return $raw;
}

function stripH1($raw) {
    return preg_replace('@<h1[^>]*?>.*?<\/h1>@si', '', $raw); //This isn't a great solution, not very dynamic, but it gets the job done.
}

function stripHR($raw) {
    return preg_replace("@<hr.*?>@si", '', $raw);
}

function changeTag($source, $oldTag, $newTag) {
    $source = preg_replace("@<{$oldTag}>@si", "<{$newTag}>", $source);
    $source = preg_replace("@</{$oldTag}>@si", "</{$newTag}>", $source);
    return $source;
}

function fixGoenkaImages($raw) {
    //Make the Goenkaji images work - JDH 10/12/2014
    $raw = preg_replace('#/images/sng/#si', 'https://www.dhamma.org/images/sng/', $raw);

    //Make the goenka images inline - JDH 10/12/2014
    $raw = str_replace('class="www-float-right-bottom"', "align='right'", $raw);
    $raw = str_replace('<img alt="S. N. Goenka at U.N."', '<img alt="S. N. Goenka at U.N." style="display: block; margin-left: auto; margin-right: auto;"', $raw);
    $raw = str_replace('Photo courtesy Beliefnet, Inc.', '<p style="text-align:center">Photo courtesy Beliefnet, Inc.</p>', $raw);

    $dir = plugin_dir_path(__FILE__);
    $raw = str_replace('src="https://www.dhamma.org/assets/sng/sng-f01f4d6595afa4ab14edced074a7e45c.gif"', 'id="goenka-image" src="/wp-content/plugins/wrap-dhamma-org/goenka.png"', $raw);
    return $raw;
}

function stripTableTags($raw) {
    $raw = preg_replace("@</*?table.*?>@si", '', $raw);
    $raw = preg_replace("@</*?tr.*?>@si", '', $raw);
    $raw = preg_replace("@</*?td.*?>@si", '', $raw);
    return $raw;
}

function stripExessVideoLineBreaks($raw) {
    $raw = preg_replace("@\n@si", '', $raw);
    $raw = preg_replace("@[ ]+@", ' ', $raw);
    return $raw;
}

function getBodyContent($raw) {
    // take HTML between <body> and </body>
    $bodypos = strpos($raw, '<body>');
    $nohead = substr($raw, $bodypos + 6); // strip <body> tag as well
    $bodyendpos = strpos($nohead, '</body>');
    $raw = substr($nohead, 1, ($bodyendpos - 1));
    return $raw;
}

function fixBlueBallImages($raw) {
    $raw = preg_replace('#<IMG SRC="/images/icons/blueball.gif">#si', '', $raw);
    return $raw;
}

function stripHomeLink($raw) {
    $raw = preg_replace("#Download a free copy of <a href='http://www.real.com'>RealPlayer</a>.#si", "", $raw);
    $raw = preg_replace("#<br/> <a href='http://www.dhamma.org/'><img style='border:0' src='/images/icons/home.gif' alt=' '></A>#si", "", $raw);
    return $raw;
}
