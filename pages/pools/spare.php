<?php

function content_pools_spare() 
{
    // required libraries
    activate_library('disk');
    activate_library('html');
    activate_library('zfs');

    // call functions
    $poollist = zfs_pool_list();

    // poolstatus
    $poolstatus = array();
    foreach ( $poollist as $poolname => $data ) {
        $poolstatus[ $poolname ] = zfs_pool_memberdetails(zfs_pool_status($poolname), $poolname);
    }

    // prepare hot spares
    $hotspares = array();
    foreach ( $poolstatus as $poolname => $vdevs ) {
        foreach ( $vdevs as $vdev ) {
            if (@$vdev[ 'type' ] == 'hot spares' ) {
                $hotspares[ $poolname ] = @$vdev[ 'name' ];
            }
        }
    }

    // table hotspares
    $table_hotspares = array();
    foreach ( $hotspares as $poolname => $hotspare ) {
        $diskinfo = disk_info($hotspare);
        $table_hotspares[] = array(
        'SPARE_NAME' => $hotspare,
        'SPARE_SIZE' => sizehuman($diskinfo[ 'mediasize' ], 1),
        'SPARE_SIZB' => sizebinary($diskinfo[ 'mediasize' ], 1),
        'SPARE_POOL' => $poolname,
        );
    }
    // class no hotspares
    $class_nohotspares = ( empty($table_hotspares) ) ? 'normal' : 'hidden';

    // devicelist (use pool_list output as argument)
    $devicelist = html_memberdisks_select();

    // table pool checkbox
    $table_poolcheckbox = array();
    foreach ( $poollist as $poolname => $data ) {
        if ($data[ 'status' ] == 'ONLINE'
            OR $data[ 'status' ] == 'DEGRADED' 
        ) {
            $table_poolcheckbox[] = array(
                'PCB_POOLNAME' => htmlentities($poolname),
                'PCB_EXTRA' => ''
            );
        } else {
            $table_poolcheckbox[] = array(
                'PCB_POOLNAME' => htmlentities($poolname),
                'PCB_EXTRA' => 'disabled="disabled"'
            );
        }
    }

    // spare_
    $spare_ = '__SPARE__';

    // export new tags
    return array(
    'PAGE_TITLE' => 'Hot spares',
    'TABLE_HOTSPARES' => $table_hotspares,
    'TABLE_POOLCHECKBOX' => $table_poolcheckbox,
    'CLASS_NOHOTSPARES' => $class_nohotspares,
    'SPARE_DEVICELIST' => $devicelist,
    'SPARE_' => $spare_
    );
}

function submit_pools_spare() 
{
    $url = 'pools.php?spare';

    if (@isset($_POST[ 'spare_submit' ]) ) {
        // create new hot spare disk
        $device = @$_POST[ 'spare_device' ];

        // determine which pools have been submitted
        $pools = array();
        foreach ( $_POST as $name => $value ) {
            if (substr($name, 0, strlen('spare_pool_')) == 'spare_pool_' ) {
                $pools[] = substr($name, strlen('spare_pool_'));
            }
        }

        // sanity
        if (strlen($device) < 1 ) {
            friendlyerror('you must select a device to serve as Hot Spare', $url);
        }
        if (count($pools) < 1 ) {
            friendlyerror(
                'you must attach your Hot Spare device to at least one pool',
                $url 
            );
        }

        // create commands
        $commands = array();
        foreach ( $pools as $poolname ) {
            $commands[] = '/sbin/zpool add ' . $poolname . ' spare ' . $device;
        }

        // execute
        dangerouscommand($commands, $url);
    }

    // redirect back
    redirect_url($url);
}
