<?php

function content_pools_pools() 
{
    // required libraries
    activate_library('zfs');

    // table: poollist
    $table_poollist = table_pools_poollist();

    // import pool
    $class_noimportables = 'hidden';
    $class_importable = 'hidden';

    // table: import pool
    $table_importpool = array();
    if (@isset($_POST[ 'import_pool' ])OR @isset($_POST[ 'import_pool_deleted' ]) ) {
        $importdestroyed = @isset($_POST[ 'import_pool_deleted' ]);
        if ($importdestroyed ) {
            $importables = zfs_pool_import_list(true);
        } else {
            $importables = zfs_pool_import_list(false);
        }
        if (count($importables) > 0 ) {
            $class_importable = 'normal';
            $i = 0;
            if (@is_array($importables) ) {
                foreach ( $importables as $importable ) {
                    $table_importpool[] = array(
                    'IMPORT_TYPE' => ( $importdestroyed ) ? 'destroyed' : 'hidden',
                    'IMPORT_ID' => $importable[ 'id' ],
                    'IMPORT_IMG' => ( $importable[ 'canimport' ] ) ? 'ok' : 'warn',
                    'IMPORT_POOLNAME' => htmlentities($importable[ 'pool' ]),
                    'IMPORT_POOLDATA' => htmlentities($importable[ 'rawoutput' ]),
                    'IMPORT_BOUNDARY' => ( ++$i < count($importables) ) ? 'normal' : 'hidden'
                    );
                }
            }
        } else {
            $class_noimportables = 'normal';
            $table_importpool[] = array(
            'IMPORT_POOLDATA' => 'No importable pools have been found.' );
        }
    }

    // pool count
    $poolcount = count($table_poollist);
    $poolcountstr = ( $poolcount == 1 ) ? '' : 's';

    // export tags
    return array(
    'PAGE_ACTIVETAB' => 'Pool status',
    'PAGE_TITLE' => 'Pool status',
    'TABLE_POOLLIST' => $table_poollist,
    'TABLE_IMPORTPOOL' => $table_importpool,
    'CLASS_IMPORTABLE' => $class_importable,
    'CLASS_NOIMPORTABLES' => $class_noimportables,
    'POOL_COUNT' => $poolcount,
    'POOL_COUNT_STRING' => $poolcountstr
    );
}

function table_pools_poollist() 
{
    // required libraries
    activate_library('zfs');

    // process table poollist
    $poollist = array();
    $zpools = zfs_pool_list();
    if (!is_array($zpools) ) {
        $zpools = array();
    }
    foreach ( $zpools as $poolname => $pooldata ) {
        $class = ( @$_GET[ 'query' ] == $poolname ) ? 'activerow' : 'normal';
        $poolspa = zfs_pool_version($poolname);
        $zpool_status = shell_exec("zpool status \$poolname");
        if (strpos($zpool_status, 'raidz3') !== false ) {
            $redundancy = 'RAID7 (triple parity)';
        } elseif (strpos($zpool_status, 'raidz2') !== false ) {
            $redundancy = 'RAID6 (double parity)';
        } elseif (strpos($zpool_status, 'raidz1') !== false ) {
            $redundancy = 'RAID5 (single parity)';
        } elseif (strpos($zpool_status, 'mirror') !== false ) {
            $redundancy = 'RAID1 (mirroring)';
        } else {
            $redundancy = 'RAID0 (no redundancy)';
        }
        $statusclass = 'normal';
        if ($pooldata[ 'status' ] === 'ONLINE' ) {
            $statusclass = 'green pool_online';
        } elseif ($pooldata[ 'status' ] === 'FAULTED'
            OR $pooldata[ 'status' ] === 'UNAVAIL'
        ) {
            $statusclass = 'red pool_faulted';
            if ($class === 'normal' ) {
                $class = 'failurerow pool_faulted';
            }
        }
        elseif ($pooldata[ 'status' ] === 'DEGRADED'
            OR $pooldata[ 'status' ] === 'OFFLINE'
        ) {
            $statusclass = 'amber pool_degraded';
            if ($class === 'normal' ) {
                $class = 'warningrow pool_degraded';
            }
        }
        $poollist[] = array(
        'POOLLIST_CLASS' => $class,
        'POOLLIST_POOLNAME' => htmlentities(trim($poolname)),
        'POOLLIST_SPA' => $poolspa,
        'POOLLIST_REDUNDANCY' => $redundancy,
        'POOLLIST_SIZE' => $pooldata[ 'size' ],
        'POOLLIST_USED' => $pooldata[ 'used' ],
        'POOLLIST_FREE' => $pooldata[ 'free' ],
        'POOLLIST_STATUS' => $pooldata[ 'status' ],
        'POOLLIST_STATUSCLASS' => $statusclass,
        'POOLLIST_POOLNAME_URLENC' => htmlentities(trim($poolname))
        );
    }
    return $poollist;
}

function submit_pools_importpool() 
{
    // required library
    activate_library('zfs');

    // redirect URL
    $url = 'pools.php';

    // scan POST variables for hidden or destroyed pool import buttons
    $result = false;
    foreach ( $_POST as $var => $value ) {
        if (strpos($var, 'import_hidden_') === 0) {
            $poolid = substr($var, strlen('import_hidden_'));
            $result = zfs_pool_import($poolid, false);
        } elseif (strpos($var, 'import_destroyed_') === 0) {
            $poolid = substr($var, strlen('import_destroyed_'));
            $result = zfs_pool_import($poolid, true);
        }
    }

    // verify result
    if ($result ) {
        page_feedback(
            'pool imported successfully, it should be visible now!',
            'b_success' 
        );
        redirect_url($url);
    } elseif ( @strlen($poolid) > 0 ) {
        friendlyerror('failed importing pool', $url);
    }
}
