<?php

function content_files_zvol() 
{
    // required library
    activate_library('html');
    activate_library('zfs');

    // call functions
    $zvols = zfs_filesystem_volumes();
    $zfsver = zfs_version();

    // retrieve queried volume
    $queryzvol = ( @$_GET[ 'zvol' ] ) ? $_GET[ 'zvol' ] : '';

    // queried volume size
    $rawsize = ( int )@$zvols[ $queryzvol ][ 'diskinfo' ][ 'mediasize' ];
    $querysize = sizebinary($rawsize, 1);
    $queryresize = round(( double )$rawsize / ( 1024 * 1024 * 1024 ), 4);

    // queried volume block size
    $rawsize = zfs_filesystem_properties(false, 'volblocksize');
    $queryblocksize = @$rawsize[ $queryzvol ][ 'volblocksize' ][ 'value' ];

    // hide query div if no volume is queried
    $queryhidden = ( $queryzvol ) ? '' : 'class="hidden"';

    // filesystem selectbox
    $fs = html_zfsfilesystems();

    // zvol properties
    $prop = zfs_filesystem_properties(false, 'refreservation');
    $prop2 = zfs_filesystem_properties(false, 'org.freebsd:swap');
    // note: sync not supported by older FreeBSD ZFS v15 implementation; only v28+
    if ($zfsver[ 'spa' ] >= 28 ) {
        $prop3 = zfs_filesystem_properties(false, 'sync');
    }

    // volume type depends on SWAP attribute being set
    $isswap = ( @$prop2[ $queryzvol ][ 'org.freebsd:swap' ][ 'value' ] === 'on' ) ?
    'normal' : 'hidden';
    $isnotswap = ( @$prop2[ $queryzvol ][ 'org.freebsd:swap' ][ 'value' ] !== 'on' ) ?
    'normal' : 'hidden';

    // swap non-active
    $isswap_nonactive = 'hidden';
    if ($isswap === 'normal' ) {
        $swapctl = shell_exec("/sbin/swapctl -l");
        if (strpos($swapctl, $queryzvol) === false ) {
            $isswap_nonactive = 'normal';
        }
    }

    // syncronous writes
    $class_sync = 'hidden';
    $class_nosync = 'hidden';
    if ($zfsver[ 'spa' ] >= 28 ) {
        if (@$prop3[ $queryzvol ][ 'sync' ][ 'value' ] === 'disabled' ) {
            $class_nosync = 'normal';
        } else {
            $class_sync = 'normal';
        }
    }

    // craft zvol table
    $volumes = array();
    foreach ( $zvols as $zvolname => $zvol ) {
        $prov = ( @$prop[ $zvolname ][ 'refreservation' ][ 'value' ] === 'none' ) ?
        '<b>thin</b>' : 'full';
        $activerow = ( $zvolname == $queryzvol AND $zvolname ) ?
        'class="activerow"' : '';
        $volumes[] = array(
        'ZVOL_ACTIVEROW' => $activerow,
        'ZVOL_NAME' => $zvolname,
        'ZVOL_SIZEBINARY' => sizebinary($zvol[ 'diskinfo' ][ 'mediasize' ]),
        'ZVOL_SIZEBYTES' => $zvol[ 'diskinfo' ][ 'mediasize' ],
        'ZVOL_REFER' => htmlentities($zvol[ 'refer' ]),
        'ZVOL_USED' => htmlentities($zvol[ 'used' ]),
        'ZVOL_PROVISIONING' => $prov,
        'ZVOL_SIZESECTOR' => ( int )$zvol[ 'diskinfo' ][ 'sectorsize' ],
        );
    }

    // export new tags
    return array(
    'PAGE_ACTIVETAB' => 'Volumes',
    'PAGE_TITLE' => 'ZFS Volumes',
    'TABLE_ZVOL_VOLUMES' => $volumes,
    'CLASS_ISSWAP' => $isswap,
    'CLASS_ISNOTSWAP' => $isnotswap,
    'CLASS_ISSWAPNONACT' => $isswap_nonactive,
    'CLASS_SYNC' => $class_sync,
    'CLASS_NOSYNC' => $class_nosync,
    'ZVOL_FILESYSTEMS' => $fs,
    'ZVOL_QUERYHIDDEN' => $queryhidden,
    'ZVOL_QUERYNAME' => $queryzvol,
    'ZVOL_QUERYSIZE' => $querysize,
    'ZVOL_QUERYBLOCKSIZE' => $queryblocksize,
    'ZVOL_QUERYRESIZE' => $queryresize
    );
}

function submit_zvol_create() 
{
    // required library
    activate_library('zfs');

    // call function
    $zfsver = zfs_version();

    // sanitize
    $s = sanitize(@$_POST[ 'zvol_name' ], null, $volname, 32);

    // variables
    $url = 'files.php?zvol';
    $fs = @$_POST[ 'zvol_filesystem' ];
    $path = $fs . '/' . $volname;
    $size_gib = @$_POST[ 'zvol_size' ];
    $blocksize = @$_POST[ 'zvol_blocksize' ];
    $sync = '';
    // sync only supported by FreeBSD ZFS v28+
    if ((@$_POST['zvol_sync'] != '')AND( $zfsver[ 'spa' ] >= 28 ) ) {
        $sync = '-o sync=' . $_POST[ 'zvol_sync' ] . ' ';
    }
    $swap = ( @$_POST[ 'zvol_swap' ] === 'on' ) ?
    '-o org.freebsd:swap=on ' : '';
    $sparse = ( @$_POST[ 'zvol_sparse' ] === 'on' ) ? '-s ' : '';

    // sanity checks
    if (!$s ) {
        friendlyerror(
            'please enter a valid name for your ZFS volume consisting of '
            . 'a maximum of 32 characters of type alphanumerical + _ + -', $url 
        );
    }
    if (( double )$size_gib < 0.1 ) {
        friendlyerror('please set a size for the volume', $url);
    }
    if (!$blocksize ) {
        friendlyerror('please select a block size for this volume', $url);
    }

    // command array
    $commands = array();
    $commands[] = '/sbin/zfs create ' . $sparse . '-b ' . $blocksize . ' '
    . $sync . $swap . '-V ' . $size_gib . 'g ' . $path;

    // activate as swap when applicable
    if ($swap !== '') {
        $commands[] = '/sbin/swapon /dev/zvol/' . $path;
    }

    // defer to dangerouscommand confirmation page
    dangerouscommand($commands, $url);
}

function submit_zvol_operations() 
{
    // acquire POST data
    $volname = @$_POST[ 'zvol_name' ];
    $volsize_gib = @$_POST[ 'zvol_resize' ];

    // sanity on volume name
    if ($volname == '') {
        error('no volume name submitted!');
    }

    // redirect URLs
    $url = 'files.php?zvol';
    $url2 = 'files.php?zvol=' . $volname;

    // zvol operations
    if (@isset($_POST[ 'destroy_zvol' ]) ) {
        // check whether in use as swap
        activate_library('super');
        $result = super_execute('/sbin/swapoff /dev/zvol/' . $volname);
        if ($result[ 'rv' ] == 0 ) {
            page_feedback(
                'this volume was in use as swap device; it has been removed '
                . 'as swap!', 'a_warning' 
            );
        }
        dangerouscommand('/sbin/zfs destroy ' . $volname, $url);
    } elseif (@isset($_POST[ 'resize_zvol' ]) ) {
        dangerouscommand('/sbin/zfs set volsize=' . $volsize_gib . 'g ' . $volname, $url2);
    } elseif (@isset($_POST[ 'enableswap_zvol' ]) ) {
        dangerouscommand(
            array(
            '/sbin/zfs set org.freebsd:swap=on ' . $volname,
            '/sbin/swapon /dev/zvol/' . $volname ), $url2 
        );
    } elseif (@isset($_POST[ 'disableswap_zvol' ]) ) {
        // disable swapctl and defer to dangerouscommand function
        activate_library('super');
        $result = super_execute('/sbin/swapoff /dev/zvol/' . $volname);
        if ($result[ 'rv' ] == 0 ) {
            page_feedback(
                'this volume was in use as swap device; it has been removed '
                . 'as swap!', 'a_warning' 
            );
        }
        dangerouscommand('/sbin/zfs set org.freebsd:swap=off ' . $volname, $url2);
    }
    elseif (@isset($_POST[ 'zvol_sync_on' ]) ) {
        dangerouscommand('/sbin/zfs set sync=standard ' . $volname, $url2);
    } elseif (@isset($_POST[ 'zvol_sync_off' ]) ) {
        dangerouscommand('/sbin/zfs set sync=disabled ' . $volname, $url2);
    }

    // default redirect
    page_feedback('nothing done', 'c_notice');
    redirect_url($url2);
}
