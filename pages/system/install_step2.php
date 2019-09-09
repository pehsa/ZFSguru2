<?php

function content_system_install_step2() 
{
    // required library
    activate_library('disk');
    activate_library('zfs');

    // include CSS from step1
    page_register_stylesheet('pages/system/install_step1.css');

    // GET variables
    $version = @$_GET[ 'version' ];
    $source = @$_GET[ 'source' ];
    $target = @$_GET[ 'target' ];

    // call functions
    $zfspools = zfs_pool_list();
    $physdisks = disk_detect_physical();
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();

    // raw disks
    $rawdisks = array();

    // tables
    $table_zfspools = table_zfspools($zfspools);
    // disable GPT and raw disks for now - to be enabled in future release
    // TODO: gpt and raw devices
    // $table_gptdevices = table_gptdevices($gpart);
    // $table_rawdisks = table_rawdisks($rawdisks);
    $table_gptdevices = array();
    $table_rawdisks = array();

    // export new tags
    return array(
    'PAGE_ACTIVETAB' => 'Install',
    'PAGE_TITLE' => 'Install (step 2)',
    'TABLE_INSTALL_ZFSPOOLS' => $table_zfspools,
    'TABLE_INSTALL_GPTDEVICES' => $table_gptdevices,
    'TABLE_INSTALL_RAWDISKS' => $table_rawdisks,
    'INSTALL_VERSION' => $version,
    'INSTALL_SOURCE' => $source,
    );
}

function table_zfspools( $poollist ) 
{
    $array = array();
    foreach ( $poollist as $poolname => $pool ) {
        if (in_array($pool[ 'status' ], array( 'ONLINE', 'DEGRADED' )) ) {
            $array[] = array(
                'ZFSPOOL_NAME' => htmlentities($poolname),
                'ZFSPOOL_FREE' => htmlentities($pool[ 'free' ]),
            );
        }
    }
    return $array;
}

function table_gptdevices( $gpart ) 
{
    $array = array();
    foreach ( $gpart as $rawdisk => $gptdisk ) {
        if (@is_array($gptdisk[ 'multilabel' ]) ) {
            foreach ( $gptdisk[ 'multilabel' ] as $gptlabel => $disknode ) {
                $array[] = array(
                    'GPTDEV_NAME' => htmlentities($gptlabel),
                    'GPTDEV_FREE' => sizebinary(( int )@$gptdisk[ 'providers' ][ $disknode ][ 'length' ], 1),
                    );
            }
        }
    }
    return $array;
}

function table_rawdisks( $rawdisks ) 
{
    $array = array();
    foreach ( $rawdisks as $rawdisk ) {
        $array[] = array(
        'RAWDISK_NAME' => htmlentities($rawdisk[ 'name' ]),
        'RAWDISK_FREE' => htmlentities($rawdisk[ 'free' ]),
        );
    }
    return $array;
}

function submit_install_disablebootfs() 
{
    $url = 'system.php?install&dist=' . @$_GET[ 'dist' ] . '&sysver=' . @$_GET[ 'sysver' ];
    $poolname = false;
    // scan POST vars for poolname
    foreach ( $_POST as $name => $value ) {
        if (substr($name, 0, strlen('disablebootfs_')) == 'disablebootfs_' ) {
            $poolname = trim(substr($name, strlen('disablebootfs_')));
        }
    }
    // sanitize
    $s = sanitize($poolname);
    if (!$s ) {
        friendlyerror('invalid pool name; cannot disable boot filesystem', $url);
    }
    // defer to dangerous command function
    dangerouscommand('/sbin/zpool set bootfs= ' . $poolname, $url);
}
