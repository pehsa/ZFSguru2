<?php

function content_files_filesystems() 
{
    // import zfs lib
    activate_library('zfs');

    // set active tab
    $page[ 'activetab' ] = 'Filesystems';

    // call function
    $zfslist = zfs_filesystem_list();
    if (!is_array($zfslist) ) {
        $zfslist = array();
    }

    // keep track of ZFSguru specific filesystems
    $gurufs = false;
    $hidegurufs = ( @isset($_GET[ 'displaygurufs' ]) ) ? false : true;
    $displaygurufs = ( $hidegurufs ) ? '' : '&displaygurufs';

    // construct filesystem list table
    $queryfs = 'XXX';
    $fslist = array();
    $i = 0;
    foreach ( $zfslist as $fsname => $fsdata ) {
        // behavior for system filesystems
        $systemfs = zfs_filesystem_issystemfs($fsname);
        if ($systemfs ) {
            $gurufs = true;
            if ($hidegurufs ) {
                continue;
            }
        }

        // filesystem class
        $fsclass = ( $systemfs ) ? 'failurerow filesystem_system ' : '';
        if (strpos($fsname, '/') === false ) {
            $fsclass = 'darkrow filesystem_root ';
        } else {
            $fsclass .= 'normal';
        }

        // filesystem mountpoint
        if ($fsdata[ 'mountpoint' ] === 'legacy' ) {
            $fsmountpoint = '<i>legacy</i>';
        } elseif ($fsdata[ 'mountpoint' ] == '-' ) {
            $fsmountpoint = '<i>volume</i>';
        } else {
            $fsmountpoint = '<a href="files.php?browse='
            . str_replace('%2F', '/', urlencode($fsdata[ 'mountpoint' ]))
            . '">' . htmlentities($fsdata[ 'mountpoint' ]) . '</a>';
        }

        // classes
        $poolfs = ( strpos($fsname, '/') === false );
        $volumefs = ( $fsdata[ 'mountpoint' ] == '-' );
        $class_fspool = ( $poolfs ) ? 'normal' : 'hidden';
        $class_fsnormal = ( !$systemfs AND!$poolfs AND!$volumefs ) ?
        'normal' : 'hidden';
        $class_fssystem = ( $systemfs AND!$poolfs AND!$volumefs ) ?
        'normal' : 'hidden';
        $class_fsvolume = ( $volumefs ) ? 'normal' : 'hidden';

        // add row to fslist table
        $fslist[] = array(
        'CLASS_FSPOOL' => $class_fspool,
        'CLASS_FSNORMAL' => $class_fsnormal,
        'CLASS_FSSYSTEM' => $class_fssystem,
        'CLASS_FSVOLUME' => $class_fsvolume,
        'FS_ESC' => $fsname,
        'FS_USED' => $fsdata[ 'used' ],
        'FS_AVAIL' => $fsdata[ 'avail' ],
        'FS_REFER' => $fsdata[ 'refer' ],
        'FS_CLASS' => $fsclass,
        'FS_MOUNTPOINT' => $fsmountpoint
        );
    }

    // filesystem selectbox
    $fsselectbox = '';
    if (is_array($zfslist) ) {
        foreach ( $zfslist as $fsname => $fsdata ) {
            // determine whether fs is system filesystem
            $fsbase = @substr($fsname, strpos($fsname, '/') + 1);
            if ($basepos = strpos($fsbase, '/') ) {
                $fsbase = @substr($fsbase, 0, $basepos);
            }
            if (( $fsbase === 'zfsguru' )OR(strpos($fsbase, 'zfsguru-system') === 0)OR( $fsbase === 'SWAP001' ) ) {
                $querygurufs = true;
            } else {
                $querygurufs = false;
            }
            // add option to filesystem selectbox
            if ($fsname == $queryfs ) {
                $fsselectbox .= '<option value="' . htmlentities($fsname)
                . '" selected="selected">' . htmlentities($fsname) . '</option>';
            } elseif (!$hidegurufs OR!$querygurufs ) {
                $fsselectbox .= '<option value="' . htmlentities($fsname) . '">'
                . htmlentities($fsname) . '</option>';
            }
        }
    }

    // display/hide gurufs class
    $class_gurufs = ( $gurufs ) ? 'normal' : 'hidden';
    $class_gurufs_display = ( $hidegurufs ) ? 'normal' : 'hidden';
    $class_gurufs_hide = ( !$hidegurufs ) ? 'normal' : 'hidden';

    // export new tags
    return array(
    'PAGE_ACTIVETAB' => 'Filesystems',
    'PAGE_TITLE' => 'Filesystems',
    'TABLE_FILES_FSLIST' => $fslist,
    'CLASS_GURUFS' => $class_gurufs,
    'CLASS_GURUFS_DISPLAY' => $class_gurufs_display,
    'CLASS_GURUFS_HIDE' => $class_gurufs_hide,
    'DISPLAYGURUFS' => $displaygurufs,
    'FILES_FSSELECTBOX' => $fsselectbox
    );
}

function submit_filesystem_create() 
{
    // POST vars
    $s = sanitize(@$_POST[ 'create_fs_name' ], null, $fsname, 32);
    $parent = @$_POST[ 'create_fs_on' ];
    $fspath = $parent . '/' . $fsname;
    $url = 'files.php';
    $url2 = 'files.php?query=' . $fspath;

    // sanity check
    if (!$s ) {
        friendlyerror(
            'please enter a valid name for the new filesystem, use only '
            . 'alphanumerical + _ + - characters with a maximum length of 32', $url 
        );
    }
    if ($parent == '') {
        friendlyerror('please select a valid parent filesystem', $url);
    }

    // execute
    $commands = array(
    '/sbin/zfs create ' . $fspath,
    '/usr/sbin/chown -R 1000:1000 /' . $fspath,
    '/bin/chmod 777 /' . $fspath
    );
    dangerouscommand($commands, $url2);
}
