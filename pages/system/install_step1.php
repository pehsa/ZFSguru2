<?php

function content_system_install_step1() 
{
    global $guru;

    // required libraries
    activate_library('gurudb');
    activate_library('server');
    activate_library('zfsguru');

    // call functions
    $currentver = common_systemversion();
    $platform = common_systemplatform();
    $locate = zfsguru_locatesystem();
    $system = gurudb_system();

    // variables
    $dist = @$_GET[ 'dist' ];
    $displayobsolete = ( @$_GET[ 'displayobsolete' ] ) ? true : false;
    $media_mounted = false;

    // scan for mounted media
    $livecd_mp = @scandir($guru[ 'path_media_mp' ]);
    if (@count($livecd_mp) > 2 ) {
        $media_mounted = true;
    }

    // table: systemversions
    $systemversions = table_systemversions(
        $system,
        $locate,
        $currentver,
        $platform,
        $dip,
        $avail,
        $obsolete
    );

    // automatic page refresh
    if ($dip ) {
        page_refreshinterval(2);
    }

    // classes
    $class_dip = ( $dip ) ? 'normal' : 'hidden';
    $class_obsolete_display = ( @isset($_GET[ 'displayobsolete' ]) ) ?
    'hidden' : 'normal';
    $class_obsolete_hide = ( @isset($_GET[ 'displayobsolete' ]) ) ?
    'normal' : 'hidden';

    // hintbox classes
    $class_avail = ( $avail ) ? 'normal' : 'hidden';
    $class_notavail = ( !$avail AND!$dip ) ? 'normal' : 'hidden';
    $class_mountcd = ( !$media_mounted ) ? 'normal' : 'hidden';
    $class_unmountcd = ( $media_mounted ) ? 'normal' : 'hidden';

    // export new tags
    return array(
    'PAGE_ACTIVETAB' => 'Install',
    'PAGE_TITLE' => 'Install (step 1)',
    'TABLE_INSTALL_SYSTEMVERSIONS' => $systemversions,
    'CLASS_DIP' => $class_dip,
    'CLASS_OBSOLETE_DISPLAY' => $class_obsolete_display,
    'CLASS_OBSOLETE_HIDE' => $class_obsolete_hide,
    'CLASS_AVAIL' => $class_avail,
    'CLASS_NOTAVAIL' => $class_notavail,
    'CLASS_MOUNTCD' => $class_mountcd,
    'CLASS_UNMOUNTCD' => $class_unmountcd,
    'INSTALL_DIST' => $dist,
    'INSTALL_CURRENT_DIST' => $currentver[ 'dist' ],
    'INSTALL_CURRENT_SYSVER' => $currentver[ 'sysver' ],
    'INSTALL_CURRENT_SHA512' => $currentver[ 'sha512' ],
    'INSTALL_PLATFORM' => $platform,
    'INSTALL_OBSOLETECOUNT' => $obsolete,
    );
}

function sort_system( $a, $b ) 
{
    $platform = common_systemplatform();
    if ($a[ $platform ][ 'date' ] > $b[ $platform ][ 'date' ] ) {
        return -1;
    }

    return 1;
}

function table_systemversions(
    $system,
    $locate,
    $currentver,
    $platform,
    &$dip,
    &$avail,
    &$obsolete
) {
    // query download location
    $dirs = common_dirs();

    // start systemversions table by quering each system version from GuruDB
    $table = array();
    $dip = false;
    $avail = false;
    $obsolete = 0;

    // sort system array by date (newest first)
    uasort($system, 'sort_system');

    // output table row for every system version
    foreach ( $system as $platforms ) {
        // skip system image if not the correct platform (system architecture)
        if (!@isset($platforms[ $platform ]) ) {
            continue;
        }

        // check download status
        // NOTE: the server_download_bg_query function should be performed before 
        // the file_exists routines, so that it doesn't detect any rejected downloads
        $data = $platforms[ $platform ];
        $download = server_download_bg_query(
            server_uri('system', $data[ 'filename' ]),
            $data[ 'filesize' ], $data[ 'sha512' ] 
        );
        if (is_int($download) ) {
            $dip = true;
        }

        // check availability of system image somewhere on disk or USB/CD media
        // TODO: not checksum but filename is important (checksum only for unknown?!)
        // TODO: offload to zfsguru library?
        $available = ( @$locate[ 'name' ][ $data[ 'name' ] ][ 'avail' ] === true );
        $source = @$locate[ 'name' ][ $data[ 'name' ] ][ 'source' ];
        $filepath = $dirs[ 'download' ] . '/' . $data[ 'filename' ];

        // hide system version if obsolete
        // unless currently running that version or mounted a LiveCD with that version
        if (((@$data['branch'] === 'obsolete') and (!@isset($_GET['displayobsolete']))) && ($data['sha512'] != $currentver['sha512']) && !$available) {
            $obsolete++;
            continue;
        }

        // check compatibility with ZFSguru web-interface
        // TODO: offload to ZFSguru library (zfsguru_checkcompatibility)
        $compatible = true;
        /*
        if (@is_numeric($data['compat']))
        $compatible = guru_checkcompatibility((int)$data['compat']);
        */
        $compat = ( $compatible ) ? 'ok' : 'no';

        // system image data
        $namelink = htmlentities($data[ 'name' ]);
        $class_sysver = ( $data[ 'sha512' ] == $currentver[ 'sha512' ] ) ?
        'activerow' : 'normal';
        $class_branch = ( @in_array($data[ 'branch' ], array( 'release', 'stable' )) ) ?
        'green' : 'red';
        $zfs_spa = substr($data[ 'zfsversion' ], 0, strpos($data[ 'zfsversion' ], '-'));
        $zfs_zpl = substr($data[ 'zfsversion' ], strpos($data[ 'zfsversion' ], '-') + 1);
        $size_human = @sizehuman($data[ 'filesize' ], 1);
        $size_binary = @sizebinary($data[ 'filesize' ], 1);
        $size_suffix = '';
        $class_size = 'normal';
        $availability = 'Unavailable';
        $notes = ( @strlen($data[ 'notes' ]) > 0 ) ? '<a href="' . $data[ 'notes' ] . '" '
        . 'onclick="window.open(this.href,\'_blank\');return false;">notes</a>': '-';

        // availability check
        if ($available ) {
            $avail = true;
            if ($compatible ) {
                $availability = '<span class="green bold italic">Available</span>';
                // TODO: re-implement unknown system versions!
                //    if ($data['name'] == 'Unknown')
                //     $namelink = '<a href="system.php?install&dist='.$dist.'&sysver=HASH'
                //      .$data['md5hash'].'">'.htmlentities($data['name']).'</a>';
                //    else
                $namelink = '<a href="system.php?install&version=' . $data[ 'name' ] . '&source='
                . $source . '">' . htmlentities($data[ 'name' ]) . '</a>';
            } else {
                $availability = '<i>not compatible: '
                . '<a href="system.php?update">update web-interface</a></i>';
            }
        } elseif (file_exists($filepath)AND is_int($download) ) {
            $availability = '<span class="blue bold">Downloading (' . $download . '%)</span>';
        } elseif ($download !== false ) {
            $availability = '<span class="blue bold">Downloading</span>';
        } elseif ($compatible ) {
            $availability = '<input type="submit" name="download_'
            . base64_encode($data[ 'name' ]) . '" ' . 'value="Download" />';
        } else {
            $availability = '<i>not compatible: '
            . '<a href="system.php?update">update web-interface</a></i>';
        }

        // add row to table array
        $table[] = array(
        'CLASS_SYSVER' => $class_sysver,
        'CLASS_BRANCH' => $class_branch,
        'CLASS_SIZE' => $class_size,
        'SYSVER_NAME' => $namelink,
        'SYSVER_BRANCH' => @htmlentities($data[ 'branch' ]),
        'SYSVER_BSDVERSION' => @htmlentities($data[ 'bsdversion' ]),
        'SYSVER_ZFS_SPA' => $zfs_spa,
        'SYSVER_ZFS_ZPL' => $zfs_zpl,
        'SYSVER_COMPAT' => $compat,
        'SYSVER_SIZE_BINARY' => $size_binary,
        'SYSVER_SIZE_SUFFIX' => $size_suffix,
        'SYSVER_AVAIL' => $availability,
        'SYSVER_NOTES_URL' => $notes
        );
    }
    return $table;
}

function submit_system_install_download() 
{
    global $guru;

    // required libraries
    activate_library('gurudb');
    activate_library('server');

    // query download location
    $dirs = common_dirs();

    // system version
    $sysver = '';
    foreach ( $_POST as $name => $value ) {
        if (strpos($name, 'download_') === 0) {
            $sysver = @base64_decode(substr($name, strlen('download_')));
        }
    }
    if (@strlen($sysver) < 1 ) {
        error('Invalid system version download request');
    }

    // fetch system versions
    $system = gurudb_system();
    $dist = common_distribution_type();
    $platform = common_systemplatform();

    // sanity checks
    if (!@isset($system[ $sysver ][ $platform ]) ) {
        error('Unknown system version "' . $sysver . '"');
    }
    $sys = $system[ $sysver ][ $platform ];
    $reserved = 64 * 1024 * 1024;
    if (disk_free_space($dirs[ 'download' ]) <        ( @$sys[ 'filesize' ] + $reserved ) 
    ) {
        if ($dist === 'livecd' ) {
            error(
                'Insufficient free memory; '
                . 'LiveCD is bound by RAM size; add more RAM!' 
            );
        } else {
            error(
                'Insufficient free space available; '
                . 'need more space on ' . $dirs[ 'download' ] 
            );
        }
    }

    // download system version - grab all data first
    $uri = server_uri('system', $sys[ 'filename' ]);
    server_download_bg($uri, $sys[ 'filesize' ], $sys[ 'sha512' ]);

    // delay redirection for several seconds to aide with download detection
    sleep(1);
    redirect_url('system.php?install');
}
