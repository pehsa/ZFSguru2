<?php

function content_pools_expansion() 
{
    // required libraries
    activate_library('html');
    activate_library('zfs');

    // include CSS from pools->create
    page_register_stylesheet('pages/pools/create.css');

    // call functions
    $zpools = zfs_pool_list();

    // pool selectbox
    $poolselectbox = html_zfspools($zpools);

    // member disks
    $memberdisks = html_memberdisks();

    // expand disable
    $expanddisable = ( @empty($zpools) ) ? 'disabled="disabled"' : '';

    // new tags
    $newtags = array(
    'PAGE_ACTIVETAB' => 'Expansion',
    'PAGE_TITLE' => 'Expansion',
    'POOL_SELECTBOX' => $poolselectbox,
    'POOL_MEMBERDISKS' => $memberdisks,
    'POOL_EXPANDDISABLE' => $expanddisable
    );
    return $newtags;
}

function submit_pools_expandpool() 
{
    // required library
    activate_library('zfs');

    // gather data
    $zpool_name = @$_POST[ 'exp_zpool_name' ];
    $url = 'pools.php?expansion';
    $url2 = 'pools.php?query=' . $zpool_name;

    // call functions
    $status = zfs_pool_status($zpool_name);
    $vdev = zfs_extractsubmittedvdevs($url);

    // sanity checks
    if (strlen($zpool_name) < 0 ) {
        friendlyerror('invalid pool name', $url);
    }
    if (@$status[ 'pool' ] != $zpool_name ) {
        friendlyerror('this pool is unknown to the system', $url);
    }
    if (( @$status[ 'state' ] != 'ONLINE' )AND( @$status[ 'state' ] != 'DEGRADED' ) ) {
        friendlyerror(
            'this pool is not healthy (<b>'
            . @$status[ 'state' ] . '</b> instead of ONLINE or DEGRADED)', $url 
        );
    }
    if (@$vdev[ 'member_count' ] < 1 ) {
        friendlyerror('no member disks selected', $url);
    }
    $redundancy = zfs_extractsubmittedredundancy(
        @$_POST[ 'exp_zpool_redundancy' ],
        $vdev[ 'member_count' ], $url 
    );

    // warn if user chose RAID0 while pool has redundancy (mirror/raidz1/2/3)
    if ($redundancy == '' ) {
        $raid0 = true;
        foreach ( $status[ 'members' ] as $data ) {
            if (strpos($data[ 'name' ], 'mirror') !== false ) {
                $raid0 = false;
            } elseif (strpos($data[ 'name' ], 'raidz') !== false ) {
                $raid0 = false;
            }
        }
        if (!$raid0 ) {
            page_feedback(
                'you are adding a RAID0 vdev to a pool with redundancy, '
                . 'are you sure that is what you want?', 'a_warning' 
            );
        }
    }

    // mixed redundancy is mandatory for non-standard expansion
    if (@isset($_POST[ 'exp_mixed_redundancy' ]) ) {
        $mixed_redundancy = '-f ';
    } else {
        $mixed_redundancy = '';
    }

    // process 2-way or 3-way or 4-way mirrors
    if ($redundancy == 'mirror2'
        OR $redundancy == 'mirror3'
        OR $redundancy == 'mirror4' 
    ) {
        $member_arr = array();
        $member_str = '';
        for ( $i = 2; $i <= 10; $i++ ) {
            if ($redundancy == 'mirror' . $i ) {
                for ( $y = 0; $y <= 255; $y = $y + $i ) {
                    if (@isset($vdev[ 'member_disks' ][ $y ]) ) {
                        for ( $z = 0; $z <= ( $i - 1 ); $z++ ) {
                            $member_arr[ $y ][] = $vdev[ 'member_disks' ][ $y + $z ];
                        }
                    }
                }
            }
        }
        foreach ( $member_arr as $components ) {
            $member_str .= 'mirror ' . implode(' ', $components) . ' ';
        }
    } elseif ($redundancy == '' ) {
        $member_str = $vdev[ 'member_str' ];
    } else {
        $member_str = $redundancy . ' ' . $vdev[ 'member_str' ];
    }

    // handle sectorsize override
    $sectorsize = ( @$_POST[ 'exp_zpool_sectorsize' ] ) ?
    ( int )$_POST[ 'exp_zpool_sectorsize' ] : 512;
    $old_ashift_min = @trim(shell_exec('/sbin/sysctl -n vfs.zfs.min_auto_ashift'));
    $old_ashift_max = @trim(shell_exec('/sbin/sysctl -n vfs.zfs.max_auto_ashift'));
    $new_ashift = 9;
    for ( $new_ashift = 9; $new_ashift <= 17; $new_ashift++ ) {
        if (pow(2, $new_ashift) == $sectorsize ) {
            break;
        }
    }
    if ($new_ashift > 16 ) {
        error('unable to find correct ashift number for sectorsize override');
    }

    // command array
    $commands = array();

    // prepend commands for sectorsize override
    if (is_numeric($sectorsize) ) {
        $commands[] = '/sbin/sysctl vfs.zfs.min_auto_ashift=' . ( int )$new_ashift;
        $commands[] = '/sbin/sysctl vfs.zfs.max_auto_ashift=' . ( int )$new_ashift;
    }

    // actual expansion command
    $commands[] = '/sbin/zpool add ' . $mixed_redundancy . $zpool_name . ' ' . $member_str;

    // append commands for sectorsize override
    if (is_numeric($sectorsize) ) {
        $commands[] = '/sbin/sysctl vfs.zfs.min_auto_ashift=' . ( int )$old_ashift_min;
        $commands[] = '/sbin/sysctl vfs.zfs.max_auto_ashift=' . ( int )$old_ashift_max;
    }

    // defer to dangerouscommand function
    dangerouscommand($commands, $url2);
}
