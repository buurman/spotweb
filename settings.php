<?php

/* ************************************************************************ */
/* ************************************************************************ */
/* ************************************************************************ */
/*       Please do not modify this file. You can override settings          */
/*     in a file named 'ownsettings.php' which will be automatically        */
/*                included for every Spotweb installation.                  */
/* ************************************************************************ */
/* ************************************************************************ */
/* ************************************************************************ */

$settings = [];
/*
 * Where is your 'openssl.cnf' file stored? This file needs to be readable
 * for OpenSSL to function. OpenSSL greatly speeds up the verifying and
 * signing of new keys.
 */
$settings['openssl_cnf_path'] = __DIR__.'/lib/openssl/openssl.cnf';

/*
 * Define a cookie host. We try to automtaically set this, but feel
 * free to override this in the correct place.
 *
 * Make sure you set a valid cookie host if you do decide to override
 * this as it will cause issues with logging in etc if you don't.
 */
if (isset($_SERVER['HTTP_HOST'])) {
    // Strip leading periods
    $cookie_domain = ltrim($_SERVER['HTTP_HOST'], '.');

    // Strip www.
    if (strpos($cookie_domain, 'www.') === 0) {
        $cookie_domain = substr($cookie_domain, 4);
    }

    //Strip port numbers
    $cookie_domain = explode(':', $cookie_domain);
    $cookie_domain = '.'.$cookie_domain[0];
} // if

/*
 * Per RFC 2109, cookie domains must contain at least one dot other than the
 * first. For hosts such as 'localhost' or IP Addresses we don't set a cookie domain.
 */
if (isset($cookie_domain) && count(explode('.', $cookie_domain)) > 2 && !filter_var(ltrim($cookie_domain, '.'), FILTER_VALIDATE_IP)) {
    $settings['cookie_host'] = $cookie_domain;
    unset($cookie_domain);
} else {
    $settings['cookie_host'] = '';
} // else

/*
 * translate Spotweb 'categories' to Sabnzbd+ categories. We use a very basic default,
 * but feel free to overide them from within your ownsettings.php
 *
 * Currently these categories are system wide.
 */
$settings['sabnzbd']['categories'] = [
    0	=> ['default' 	 => 'movies',				// Default categorie when nothing else matches
        'a5'		        => 'books',
        'a11'		       => 'books',
        'd2'		        => 'anime',
        'd11'		       => 'tv',
        'd29'		       => 'anime',
        'z3'		        => 'erotica', ],
    1	=> ['default'	=> 'music'],
    2	=> ['default'	=> 'games'],
    3	=> ['default'	=> 'apps',
        'a3'		      => 'consoles',
        'a4'		      => 'consoles',
        'a5'		      => 'consoles',
        'a6'		      => 'consoles',
        'a7'		      => 'consoles',
        'a8'		      => 'consoles',
        'a9'		      => 'consoles',
        'a10'		     => 'consoles',
        'a11'		     => 'consoles',
        'a12'		     => 'consoles',
        'a13'		     => 'pda',
        'a14'		     => 'pda',
        'a15'		     => 'pda', ],
];

/*
 * Include, if any, ownsettings which should also be a valid PHP file.
 * Settings set in this file, will override settings from this file,
 * so please always use the 'ownsettings.php' file.
 *
 */
if (file_exists(__DIR__.'/ownsettings.php')) {
    require_once __DIR__.'/ownsettings.php';
}

/*
 * List of quicklinks. First we test whether those are set within the 'ownsettings.php' file,
 * because if they are we don't want to override or add to them.
 *
 * We cannot create this before ownsetings.php (so an user could add/change existing links),
 * because in earlier versions of Spotweb we tested for 'keep_watchlist' and 'keep_downloads'
 * settings in this file.
 *
 * If we would change it now, it would break compatibility with existing installations, and we
 * don't want to do that until we move this configuration to the settings page in whole.
 */
if (!isset($settings['quicklinks'])) {
    $settings['quicklinks'] = [];
    $settings['quicklinks'][] = ['Reset filters', 'home', '?search[tree]=&amp;search[unfiltered]=true', '', [SpotSecurity::spotsec_view_spots_index, ''], null];
    $settings['quicklinks'][] = ['New', 'today', '?search[tree]=&amp;search[unfiltered]=true&amp;search[value][]=New:0', '', [SpotSecurity::spotsec_keep_own_seenlist, ''], 'count_newspots'];
    $settings['quicklinks'][] = ['Watchlist', 'fav', '?search[tree]=&amp;search[unfiltered]=true&amp;search[value][]=Watch:0', '', [SpotSecurity::spotsec_keep_own_watchlist, ''], 'keep_watchlist'];
    $settings['quicklinks'][] = ['Downloaded', 'download', '?search[tree]=&amp;search[unfiltered]=true&amp;search[value][]=Downloaded:0', '', [SpotSecurity::spotsec_keep_own_downloadlist, ''], 'keep_downloadlist'];
    $settings['quicklinks'][] = ['Recently viewed', 'eye', '?search[tree]=&amp;search[unfiltered]=true&amp;search[value][]=Seen:0', '', [SpotSecurity::spotsec_keep_own_seenlist, ''], 'keep_seenlist'];
    $settings['quicklinks'][] = ['My spots', 'fav', '?search[tree]=&amp;search[unfiltered]=true&amp;search[value][]=MyPostedSpots:0', '', [SpotSecurity::spotsec_post_spot, ''], null];
    $settings['quicklinks'][] = ['Statistics', 'stats', '?page=statistics', '', [SpotSecurity::spotsec_view_statistics, ''], null];
    $settings['quicklinks'][] = ['Documentation', 'help', 'https://github.com/spotweb/spotweb/wiki', 'external', [SpotSecurity::spotsec_view_spots_index, ''], null];
} // if isset

/*
 * When the OpenSSL module is loaded, make sure the "openssl_cnf_path"
 * setting variable points to an readable cnf file.
 */
if ((!is_readable($settings['openssl_cnf_path'])) && (extension_loaded('openssl'))) {
    throw new InvalidOwnSettingsSettingException('openssl_cnf_path does not contain a readable OpenSSL configuration filepath');
} // if

/*
 * Where is Spotweb intalled / accessible for the outside world?
 * We try to automatically create the proper URL to this site, but
 * if this somehow fails please set it yourselve. Spotweb url is used
 * for things like pushing the NZB file to your download manager, and
 * in notifications to users.
 * Determine the Spotweb url
 * use HTTPS in case of HTTPS in server vars or ssloveride in ownsettings or HTTP_X_SSL in server vars
 * For use of an nginx reverse proxy x_forwarded_uri $request_uri can be added if the location differs from the default location
 */
if (!isset($_SERVER['HTTP_X_FORWARDED_URI'])) {
    $loc = (dirname($_SERVER['PHP_SELF']) != '/' && dirname($_SERVER['PHP_SELF']) != '\\' ? dirname($_SERVER['PHP_SELF']).'/' : '/');
} else {
    $loc = ($_SERVER['HTTP_X_FORWARDED_URI'] != '/' && $_SERVER['HTTP_X_FORWARDED_URI'] != '\\' ? $_SERVER['HTTP_X_FORWARDED_URI'].'/' : '/');
}
$ssloverride = (isset($settings['ssloverride']) ? $settings['ssloverride'] : false);
$httpxssl = isset($_SERVER['HTTP_X_SSL']);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $nwsetting = $_SERVER['HTTP_X_FORWARDED_PROTO'].'://'.@$_SERVER['HTTP_HOST'].$loc;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL'])) {
    $nwsetting = ($_SERVER['HTTP_X_FORWARDED_SSL'] == 'on' ? 'https' : 'http').'://'.@$_SERVER['HTTP_HOST'].$loc;
} elseif (isset($_SERVER['SERVER_PROTOCOL'])) {
    $nwsetting = (((isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') or ($ssloverride == true) or ($httpxssl == true)) ? 'https' : 'http').'://'.@$_SERVER['HTTP_HOST'].$loc;
} else {
    $nwsetting = 'http://mijnuniekeservernaam/spotweb/';
} // if

/*
 * Add a closing slash to the Spotweb url
 */
if (substr($nwsetting, -1) != '/') {
    $nwsetting .= '/';
} // if
$settings['spotweburl'] = $nwsetting;

/*
 * In older Spotweb versions, users could set preferences
 * in this file. We don't allow this anymore as they are
 * set per user.
 */
if (isset($settings['prefs'])) {
    throw new InvalidOwnSettingsSettingException('Preferences are set per user, not in your ownsettings.php');
} // if

/*
 * Several settings are deprecated. Don't allow them to be set
 * in this system anymore.
 */
$ownsettingserror = '';
$array = ['blacklist_url', 'cookie_expires', 'deny_robots', 'enable_stacktrace', 'enable_timing', 'external_blacklist', 'nntp_hdr',
    'nntp_nzb', 'nntp_post', 'prefetch_image', 'prefetch_nzb', 'retention', 'retrieve_comments', 'retrieve_full', 'retrieve_full_comments',
    'retrieve_increment', 'retrieve_newer_than', 'retrieve_reports', 'sendwelcomemail', 'spot_moderation', 'allow_user_template',
    'auto_markasread', 'filters', 'index_filter', 'keep_downloadlist', 'keep_watchlist', 'nzb_search_engine', 'nzbhandling', 'show_multinzb',
    'count_newspots', 'keep_seenlist', 'show_nzbbutton', 'show_updatebutton', 'newuser_grouplist', 'nonauthenticated_userid',
    'templates', ];
foreach ($array as $value) {
    if (isset($settings[$value])) {
        $ownsettingserror .= ' * '.$value.PHP_EOL;
    } // if
} // foreach

if (!empty($ownsettingserror)) {
    throw new InvalidOwnSettingsSettingException('Please remove '.$ownsettingserror." from your 'ownsettings.php' file, this setting is set in the settings panel from within Spotweb itself");
} // if

// Controleer op oud type quicklinks (zonder preference link)
foreach ($settings['quicklinks'] as $link) {
    if (count($link) < 6) {
        throw new InvalidOwnSettingsSettingException('Quicklinks have to have a preferences check as well. Please modify the quickinks in your ownettings.php or remove them from your ownsetings.php');
    } // if
} // foreach

/*
 * First make sure no database settings are left in the main ownsettings.php anymore, as this is the first
 * part to deprecating the kludge that settings.php has become completely.
 */
if (!empty($settings['db'])) {
    throw new InvalidOwnSettingsSettingException('You need to remove the database settings from your ownsettings.php file and open install.php from your webbrowser. If you are upgrading, please consult https://github.com/spotweb/spotweb/wiki/Frequently-asked-questions/ first');
} // if

if (file_exists(__DIR__.'/reallymyownsettings.php')) {
    require_once __DIR__.'/reallymyownsettings.php';
}
