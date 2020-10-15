<?php

/*
 ** ZFSguru main include
 ** always included in every request
 */

// include common library
require 'common.php';

// start timekeeper
timerstart('init');

// start guru global array
$guru = [];

// project data
$guru[ 'product_name' ] = 'ZFSguru';
$guru[ 'product_majorversion' ] = 0;
$guru[ 'product_minorversion' ] = 3;
$guru[ 'product_revision' ] = 1;
$guru[ 'product_suffix' ] = '';
$guru[ 'product_version_string' ] = $guru[ 'product_majorversion' ] . '.' .
$guru[ 'product_minorversion' ] . '.' .
$guru[ 'product_revision' ] .
$guru[ 'product_suffix' ];
$guru[ 'product_url' ] = 'http://zfsguru.com/';

// GUI compatibility version (used for system images and services)
// do not edit unless you know what you're doing
$guru[ 'compat_min' ] = '1';
$guru[ 'compat_max' ] = '1';

/*
 ** Path Locations
 */
$guru[ 'docroot' ] = dirname(__DIR__).'/'. '/';
$guru[ 'dev_livecd' ] = '/dev/iso9660/ZFSGURU-LIVECD';
$guru[ 'tempdir' ] = '/tmp/';

/* 
 ** Various
 */
$guru[ 'iso_date_format' ] = 'Y-M-d @ H:i';
$guru[ 'default_bootfs' ] = 'zfsguru';
$guru[ 'benchmark_magic_string' ] = 'XX00XXBENCHMARKXX00XX';
$guru[ 'benchmark_poolname' ] = 'gurubenchmarkpool';
$guru[ 'benchmark_zvolname' ] = 'guruzvoltest';
$guru[ 'recommended_zfsversion' ] = ['zpl' => 5, 'spa' => 28];

/*
 ** Configuration File
 ** If needed, change this variable to a www-group writeable file path
 */
$guru[ 'configuration_file' ] = $guru[ 'docroot' ] . '/config/config.bin';

/*
 ** File Locations
 ** If needed, change these accordingly
 */
$guru[ 'required_binaries' ] = [
    'Tar' => '/usr/bin/tar',
    'Sudo' => '/usr/local/bin/sudo',
    'sh' => '/bin/sh'
];
$guru[ 'path' ] = [
    'Samba' => '/usr/local/etc/smb4.conf',
    'OpenSSH' => '/etc/ssh/sshd_config'
];
$guru[ 'rc.d' ] = [
    'Lighttpd' => '/usr/local/etc/rc.d/lighttpd',
    'OpenSSH' => '/etc/rc.d/sshd',
    'Samba' => '/usr/local/etc/rc.d/samba_server',
    'NFS' => '/etc/rc.d/nfsserver',
    'iSCSI' => '/usr/local/etc/rc.d/istgt',
    'powerd' => '/etc/rc.d/powerd'
];
$guru[ 'runcontrol' ] = [
    'Apache' => 'apache22',
    'OpenSSH' => 'sshd',
    'Lighttpd' => 'lighttpd',
    'Samba' => 'samba',
    'NFS' => 'nfs_server',
    'iSCSI' => 'istgt'
];

/*
 ** Default Preferences
 ** Do NOT change these! Your actual preferences are stored in a file
 */
$guru[ 'default_preferences' ] = [
    'uuid' => '',
    'language' => 'en',
    'preferred_master' => '',
    'preferred_slave' => '',
    'advanced_mode' => true,
    'timezone' => 'UTC',

    'access_control' => 2,
    'access_whitelist' => '',
    'authentication' => '',

    'theme' => 'default',
    'command_confirm' => false,
    'destroy_pools' => true,
    'timekeeper' => true,

    'refresh_rate' => 14400,
    'connect_timeout' => 3,
    'offline_mode' => false,
    'segment_hide' => 1024,

    // invisible
    'refresh_lastcheck' => 0,
    'bulletin_unread' => '?',
    'bulletin_lastread' => 0,
];

// include page library
require 'page.php';

// start procedures to be executed every page request
require 'procedure.php';
