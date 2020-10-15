<?php

/**
 * @return array
 */
function content_access_nfs()
{
    // required library
    activate_library('internalservice');
    activate_library('nfs');

    // remove NFS share
    if (@isset($_GET[ 'removenfs' ]) ) {
        $sharenfs = nfs_sharenfs_list($queryfs);
        if (@isset($sharenfs[$_GET['removenfs']])) {
            dangerouscommand(nfs_removeshare($_GET[ 'removenfs' ]), 'access.php?nfs');
        } else {
            friendlyerror(
                'cannot remove "' . $_GET[ 'removenfs' ]
                . '" because the share was not found.', 'access.php?nfs'
            );
        }
    }

    // activate visual elements
    page_register_javascript('pages/access/nfs.js');
    page_register_stylesheet('pages/files/filesystems.css');
    page_register_stylesheet('pages/access/widget_itemlist.css');
    page_register_javascript('pages/access/widget_extratab.js');
    page_register_stylesheet('pages/access/widget_extratab.css');

    // queried NFS share
    $queryfs = ( @strlen($_GET[ 'q' ]) > 0 ) ? $_GET[ 'q' ] : false;

    // fetch NFS database and redirect if nonexisting queryfs
    $sharenfs = nfs_sharenfs_list($queryfs);
    if (!$sharenfs AND $queryfs ) {
        redirect_url('access.php?nfs');
    }

    $showmount = nfs_showmount_list();
    $nfsautostart = internalservice_queryautostart('nfs');
    $nfsrunning = internalservice_querystart('nfs');

    $table_nfs_items = table_nfs_items($sharenfs, $queryfs);

    if ($queryfs ) {
        $query_mp = htmlentities($sharenfs[ $queryfs ][ 'mountpoint' ]);
        $query_sharenfs = htmlentities($sharenfs[ $queryfs ][ 'sharenfs' ]);
        $query_showmount = htmlentities(@$showmount[ $query_mp ]);
        $table_nfs_shareconf = table_nfs_shareconf($sharenfs, $queryfs);
        $parentfs = ( @trim($sharenfs[ $queryfs ][ 'parent' ]) ) ?
        $sharenfs[ $queryfs ][ 'parent' ] : $queryfs;
        $table_nfs_children = table_nfs_children(
            nfs_sharenfs_list($parentfs),
            $showmount, $queryfs 
        );
        $table_nfs_accesscontrol = table_nfs_accesscontrol(
            $sharenfs[ $queryfs ][ 'options' ] 
        );
        $class_ac_y = ( !empty($table_nfs_accesscontrol) ) ? 'normal' : 'hidden';
        $class_ac_n = ( empty($table_nfs_accesscontrol) ) ? 'normal' : 'hidden';
        $querychild = ( $queryfs == $parentfs );
        // easy permissions
        $easypermissions = nfs_geteasypermissions(@$sharenfs[ $queryfs ][ 'options' ]);
        // share profile for modify share profile page
        $access_profile = nfs_getprofile(@$sharenfs[ $queryfs ]);
        $not_shared = ( @$sharenfs[ $queryfs ][ 'sharenfs' ] === 'off' );
        $all_profiles = ['public', 'protected', 'private'];
        foreach ( $all_profiles as $profile ) {
            $class_mp[ $profile ] = ( $access_profile == $profile AND!$not_shared ) ?
            'normal' : 'hidden';
            $check[ $profile ] = ( $access_profile == $profile ) ? 'selected' : '';
        }
        $readonly = nfs_getreadonly(@$sharenfs[ $queryfs ][ 'options' ]);
    }

    // zfs filesystem list (for creating new NFS share)
    if (!$queryfs ) {
        // required libraries
        activate_library('html');
        activate_library('zfs');
        // call functions
        $zfsfslist = zfs_filesystem_list(false, '-t filesystem');
        if (is_array($zfsfslist) ) {
            foreach ( $zfsfslist as $fsname => $fsdata ) {
                if (@isset($sharenfs[ $fsname ]) ) {
                    unset($zfsfslist[ $fsname ]);
                }
            }
        }
        $nfs_zfsfslist = html_zfsfilesystems($zfsfslist, @$_GET[ 'newfs' ]);
    }

    // classes
    $class_notrunning = ( !$nfsrunning ) ? 'normal' : 'hidden';
    $class_noautostart = ( !$nfsautostart ) ? 'normal' : 'hidden';
    $class_nonfsshares = ( count($table_nfs_items) == 0 ) ? 'normal' : 'hidden';
    $class_newshare = ( @$_GET[ 'newfs' ] ) ? 'normal' : 'hidden';
    $class_query = ( $queryfs ) ? 'normal' : 'hidden';
    $class_noquery = ( !$queryfs ) ? 'normal' : 'hidden';
    $class_queryparent = ( $queryfs AND!$querychild ) ? 'normal' : 'hidden';
    $class_querychild = ( $queryfs AND $querychild ) ? 'normal' : 'hidden';
    $class_shared = ( !@$not_shared ) ? 'normal' : 'hidden';
    $class_notshared = ( @$not_shared ) ? 'normal' : 'hidden';

    // default IP
    $defaultip = @htmlentities($_SERVER[ 'REMOTE_ADDR' ]);
    $serverip = @htmlentities($_SERVER[ 'SERVER_ADDR' ]);

    // export new tags
    return @[
    'PAGE_TITLE' => 'NFS',
    'PAGE_ACTIVETAB' => 'NFS',
    'TABLE_NFS_ITEMS' => $table_nfs_items,
    'TABLE_NFS_CHILDREN' => $table_nfs_children,
    'TABLE_NFS_ACCESSCONTROL' => $table_nfs_accesscontrol,
    'TABLE_NFS_SHARECONF' => $table_nfs_shareconf,
    'CLASS_NOTRUNNING' => $class_notrunning,
    'CLASS_NOAUTOSTART' => $class_noautostart,
    'CLASS_NONFSSHARES' => $class_nonfsshares,
    'CLASS_NEWSHARE' => $class_newshare,
    'CLASS_QUERY' => $class_query,
    'CLASS_NOQUERY' => $class_noquery,
    'CLASS_QUERYCHILD' => $class_queryparent,
    'CLASS_QUERYPARENT' => $class_querychild,
    'CLASS_SHARED' => $class_shared,
    'CLASS_NOTSHARED' => $class_notshared,
    'CLASS_EP_Y' => ( $easypermissions ) ? 'normal' : 'hidden',
    'CLASS_EP_N' => ( !$easypermissions ) ? 'normal' : 'hidden',
    'CLASS_AC_Y' => $class_ac_y,
    'CLASS_AC_N' => $class_ac_n,
    'CLASS_AC_RO' => ( $readonly ) ? 'normal' : 'hidden',
    'CLASS_AC_RW' => ( !$readonly ) ? 'normal' : 'hidden',
    'CLASS_MP_PUBLIC' => $class_mp[ 'public' ],
    'CLASS_MP_PROTECTED' => $class_mp[ 'protected' ],
    'CLASS_MP_PRIVATE' => $class_mp[ 'private' ],
    'CHECK_MP_PUBLIC' => $check[ 'public' ],
    'CHECK_MP_PROTECTED' => $check[ 'protected' ],
    'CHECK_MP_PRIVATE' => $check[ 'private' ],
    'NFS_ZFSFSLIST' => $nfs_zfsfslist,
    'NFS_DEFAULTIP' => $defaultip,
    'NFS_SERVERIP' => $serverip,
    'QUERY_FSNAME' => htmlentities($queryfs),
    'QUERY_PARENT' => htmlentities($parentfs),
    'QUERY_CHILDREN' => @count($sharenfs),
    'QUERY_MP' => htmlentities($query_mp),
    'QUERY_SHARENFS' => $query_sharenfs,
    'QUERY_SHOWMOUNT' => $query_showmount,
    ];
}

/**
 * @param       $sharenfs
 * @param false $queryfs
 *
 * @return array
 */
function table_nfs_items($sharenfs, $queryfs = false)
{
    if (!@is_array($sharenfs) ) {
        return [];
    }
    $table_shares = [];
    foreach ( $sharenfs as $sharename => $share ) {
        // skip shares with inheritance, unless it is the queried filesystem
        if ($share[ 'inherited' ]AND( $sharename != $queryfs ) ) {
            continue;
        }
        // access type
        $access_type = nfs_getprofile(@$share);
        if ($share[ 'sharenfs' ] === 'off' ) {
            $access_type = 'notshared';
        }
        // set classes for each type
        $alltypes = [
            'public', 'protected', 'private', 'custom',
        'noaccess', 'disabled', 'problem', 'notshared'
        ];
        foreach ( $alltypes as $type ) {
            $access[ $type ] = 'hidden';
        }
        $access[ $access_type ] = 'normal';

        // short name
        if (strrpos($sharename, '/') !== false) {
            $shortname = htmlentities(substr($sharename, strrpos($sharename, '/') + 1));
        } else {
            $shortname = $sharename;
        }

        // add row to table
        $table_shares[ $sharename ] = [
        'SHARE_CLASS' => ( $access_type === 'notshared' ) ? 'disabled' : '',
        'SHARE_NAME' => htmlentities($sharename),
        'SHARE_SHORTNAME' => $shortname,
        // images
        'SHARE_PUBLIC' => $access[ 'public' ],
        'SHARE_PROTECTED' => $access[ 'protected' ],
        'SHARE_PRIVATE' => $access[ 'private' ],
        'SHARE_CUSTOM' => $access[ 'custom' ],
        'SHARE_NOACCESS' => $access[ 'noaccess' ],
        'SHARE_DISABLED' => $access[ 'disabled' ],
        'SHARE_PROBLEM' => $access[ 'problem' ],
        'SHARE_SHARED' => ( $access_type !== 'notshared' ) ? 'normal' : 'hidden',
        'SHARE_NOTSHARED' => $access[ 'notshared' ],
        ];
    }
    return $table_shares;
}

/**
 * @param       $sharenfs
 * @param       $showmount
 * @param false $queryfs
 *
 * @return array
 */
function table_nfs_children( $sharenfs, $showmount, $queryfs = false )
{
    $table_nfs_sharelist = [];
    foreach ( $sharenfs as $fs => $sharedata ) {
        $mp = trim($sharedata[ 'mountpoint' ]);
        if (strpos($mp, '/') !== false ) {
            $mp_short = substr(substr($mp, strrpos($mp, '/') + 1), 0, 12);
        } else {
            $mp_short = substr($mp, 0, 12);
        }
        $shareaccess = ( @$showmount[ $mp ] ) ? $showmount[ $mp ] : '???';
        // filesystem classes
        $poolfs = ( strpos($fs, '/') === false );
        $class_fspool = ( $poolfs ) ? 'normal' : 'hidden';
        $class_fsnor = ( !$poolfs AND!$sharedata[ 'inherited' ] ) ? 'normal' : 'hidden';
        $class_fsgrey = ( !$poolfs AND $sharedata[ 'inherited' ] ) ? 'normal' : 'hidden';

        $profile = nfs_getprofile($sharedata);
        $easypermissions = nfs_geteasypermissions($sharedata[ 'options' ]);

        // add table row
        $table_nfs_sharelist[] = [
        'CLASS_ACTIVEROW' => ( $queryfs AND( $fs == $queryfs ) ) ? 'activerow' : '',
        'CLASS_FSPOOL' => $class_fspool,
        'CLASS_FSNORMAL' => $class_fsnor,
        'CLASS_FSGREY' => $class_fsgrey,
        'NFS_FSNAME' => htmlentities($fs),
        'NFS_MOUNTPOINT' => htmlentities($mp),
        'NFS_MOUNTPSHORT' => htmlentities($mp_short),
        'NFS_SHARESETTING' => htmlentities($sharedata[ 'sharenfs' ]),
        'NFS_SHAREACCESS' => htmlentities($shareaccess),
        'CHILD_PROFILE' => $profile,
        'CHILD_EP_Y' => ( $easypermissions ) ? 'normal' : 'hidden',
        'CHILD_EP_N' => ( !$easypermissions ) ? 'normal' : 'hidden',
        ];
    }
    return $table_nfs_sharelist;
}

/**
 * @param $options
 *
 * @return array
 */
function table_nfs_accesscontrol( $options )
{
    $table_nfs_ac = [];
    if (@isset($options[ 'network' ]) ) {
        foreach ( $options[ 'network' ] as $acrecord ) {
            $hasprefix = ( strrpos($acrecord, '/') !== false );
            $ip = ( $hasprefix ) ? substr($acrecord, 0, strrpos($acrecord, '/')) :
            $acrecord;
            if ($hasprefix ) {
                $prefix = substr($acrecord, strrpos($acrecord, '/'));
            } elseif (@isset($options[ 'mask' ]) ) {
                $prefix = '/' . convert_ip2cidr($options[ 'mask' ][ 0 ]);
            } else {
                $prefix = '/32';
            }
            $table_nfs_ac[] = [
            'NFS_AC_IP' => $ip,
            'NFS_AC_PREFIX' => $prefix,
            'NFS_AC_PREFIX0' => ( $prefix === '/0' ) ? 'normal' : 'hidden',
            'NFS_AC_PREFIX32' => ( $prefix === '/32' ) ? 'normal' : 'hidden',
            'NFS_AC_B64' => base64_encode($acrecord),
            ];
        }
    }
    return $table_nfs_ac;
}

/**
 * @param $sharenfs
 * @param $queryfs
 *
 * @return array
 */
function table_nfs_shareconf($sharenfs, $queryfs)
{
    // required library
    activate_library('nfs');
    // call function
    $nfsvars = nfs_configuration_list();
    // create table
    $table_nfs_shareconf = [];
    foreach ( $nfsvars as $nfsvar => $nfsvardesc ) {
        // check if nfsvar exists in current configuration
        $exists = @isset($sharenfs[ $queryfs ][ 'options' ][ $nfsvar ]);
        $class_activerow = ( $exists ) ? 'activerow' : 'normal';
        $booleanvars = ['alldirs', 'quiet', 'ro', 'webnfs', 'public'];
        $class_showtext = ( !in_array($nfsvar, $booleanvars, true) AND( $nfsvar !== 'network' ) ) ? 'normal' : 'hidden';
        $class_network = ( $nfsvar === 'network' ) ? 'normal' : 'hidden';
        $checked = ( $exists ) ? 'checked="checked"' : '';
        $table_nfs_shareconf[] = [
        'CLASS_ACTIVEROW' => $class_activerow,
        'CLASS_SHOWTEXT' => $class_showtext,
        'CLASS_NETWORK' => $class_network,
        'CONFIG_NAME' => $nfsvar,
        'CONFIG_CHECKED' => $checked,
        'CONFIG_VALUE' => @$sharenfs[ $queryfs ][ 'options' ][ $nfsvar ][ 0 ],
        'CONFIG_DESC' => $nfsvardesc
        ];
    }
    return $table_nfs_shareconf;
}

/**
 * @return array
 */
function table_nfs_variables()
{
    $nfsconfig = nfs_configuration_list();
    $table_nfs_variables = [];
    foreach ( $nfsconfig as $configvar ) {
        $table_nfs_variables[] = [
        'CONFIG_NAME' => htmlentities($configvar),
        'CONFIG_VALUE' => htmlentities($configvar)
        ];
    }
    return $table_nfs_variables;
}


/* helper functions */

/**
 * @param $cidr
 *
 * @return string
 */
function convert_cidr2ip( $cidr )
{
    return long2ip(-1 << ( 32 - ( int )$cidr ));
}

/**
 * @param $ip
 *
 * @return int|string
 */
function convert_ip2cidr( $ip )
{
    for ( $cidr = 0; $cidr <= 32; $cidr++ ) {
        if (convert_cidr2ip($cidr) == $ip ) {
            return $cidr;
        }
    }
    return '??';
}


/* submit functions */

function submit_access_nfs_newshare() 
{
    // required library
    activate_library('nfs');

    // zfs shared filesystem
    $nfsfs = @$_POST[ 'nfs_newshare_fs' ];

    // redirect URL
    $url = 'access.php?nfs';
    $url2 = $url . '&q=' . $nfsfs;

    // sanity
    if ($nfsfs == '') {
        redirect_url($url);
    }

    // NFS easy permissions
    $ep = ( @$_POST[ 'nfs_newshare_ep' ] === 'on' );
    $sharenfs = ( $ep ) ? '-alldirs -mapall=1000:1000 ' : '';

    // NFS profile
    $shareprofile = @$_POST[ 'nfs_newshare_profile' ];
    if (( $shareprofile === 'private' )AND( @strlen($_POST[ 'nfs_newshare_privateip' ]) > 0 ) ) {
        $sharenfs .= '-network ' . $_POST[ 'nfs_newshare_privateip' ];
    } elseif ($shareprofile === 'protected' ) {
        $sharenfs .= '-network 10.0.0.0/8 -network 172.16.0.0/12 '
        . '-network 192.168.0.0/16';
    } elseif ($shareprofile === 'public'
        AND($sharenfs === '')
    ) {
        $sharenfs = 'on';
    } elseif ($shareprofile === 'notshared' ) {
        $sharenfs = 'off';
    }

    // share NFS filesystem
    dangerouscommand('/sbin/zfs set sharenfs="' . $sharenfs . '" ' . $nfsfs, $url2);
    redirect_url($url2);
}

function submit_access_nfs_massaction() 
{
    // required library
    activate_library('nfs');

    // list of filesystems selected
    $massfs = [];
    foreach ( $_POST as $postname => $postvalue ) {
        if (strncmp($postname, 'cb_nfsfs_', 9) === 0) {
            $massfs[] = substr($postname, strlen('cb_nfsfs_'));
        }
    }

    // mass action
    $action = @$_POST[ 'nfs_massaction' ];

    // mass action: unshare
    $commands = [];
    if ($action === 'inherit' ) {
        foreach ( $massfs as $fs ) {
            $commands = array_merge($commands, nfs_removeshare($fs));
        }
    }
    if ($action === 'off' ) {
        foreach ( $massfs as $fs ) {
            $commands = array_merge($commands, nfs_removeshare($fs, true));
        }
    }
    // mass action: share with selected profile
    if (in_array($action, ['public', 'protected', 'private']) ) {
        foreach ( $massfs as $fs ) {
            $commands = array_merge(
                $commands,
                nfs_setprofile($fs, $action, @$_POST[ 'nfs_massaction_privateip' ]) 
            );
        }
    }
    // mass action: easy permissions
    if ($action === 'epoff' ) {
        foreach ( $massfs as $fs ) {
            $commands = array_merge($commands, nfs_seteasypermissions($fs, false));
        }
    }
    if ($action === 'epon' ) {
        foreach ( $massfs as $fs ) {
            $commands = array_merge($commands, nfs_seteasypermissions($fs));
        }
    }
    if ($action === 'resetpermissions' ) {
        foreach ( $massfs as $fs ) {
            $commands = array_merge($commands, nfs_resetpermissions($fs));
        }
    }

    // execute commands and redirect
    if (@$commands ) {
        dangerouscommand($commands, 'access.php?nfs&q=' . $fs);
    } else {
        friendlyerror('nothing to do', 'access.php?nfs');
    }
}

function submit_access_nfs_changeprofile() 
{
    // required library
    activate_library('nfs');
    // required library
    activate_library('nfs');
    $nfsfs = @$_POST[ 'nfs_changeprofile_fs' ];
    $url = 'access.php?nfs&q=' . @$_GET[ 'q' ];
    if ($nfsfs == '') {
        friendlyerror('cannot change profile: no filesystem selected', $url);
    }
    dangerouscommand(
        nfs_setprofile(
            $nfsfs, @$_POST[ 'nfs_changeprofile_profile' ],
            @$_POST[ 'nfs_changeprofile_privateip' ] 
        ), $url 
    );
}

function submit_access_nfs_accesscontrol() 
{
    // required library
    activate_library('nfs');

    // data
    $fs = @$_POST[ 'nfs_ac_fs' ];
    $url = 'access.php?nfs&q=' . @$_GET[ 'q' ];

    // Read-Write
    if (@isset($_POST[ 'submit_access_nfs_ac_readwrite' ]) ) {
        dangerouscommand(nfs_setreadonly($fs, false), $url);
    }
    if (@isset($_POST[ 'submit_access_nfs_ac_readonly' ]) ) {
        dangerouscommand(nfs_setreadonly($fs), $url);
    }

    // Easy Permissions
    if (@isset($_POST[ 'submit_access_nfs_ep_enable' ]) ) {
        dangerouscommand(nfs_seteasypermissions($fs), $url);
    }
    if (@isset($_POST[ 'submit_access_nfs_ep_disable' ]) ) {
        dangerouscommand(nfs_seteasypermissions($fs, false), $url);
    }
    // Reset permissions of all files contained by filesystem and subdirectories
    if (@isset($_POST[ 'submit_access_nfs_resetpermissions' ]) ) {
        dangerouscommand(nfs_resetpermissions($fs), $url);
    }

    // Access Control - add IP
    if (@isset($_POST[ 'submit_access_nfs_acnew' ]) ) {
        $acrecord = @$_POST[ 'ac_new_ip' ] . '/' . ( int )@$_POST[ 'ac_new_prefix' ];
        $sharenfs = nfs_sharenfs_list($fs);
        $networkconfig = $sharenfs[ $fs ][ 'options' ][ 'network' ];
        foreach ( $networkconfig as $acip ) {
            if ($acip == $acrecord ) {
                friendlyerror('IP already exists in Access Control configuration', $url);
            }
        }
        $networkconfig[] = $acrecord;
        dangerouscommand(
            nfs_setsharenfs(
                $fs,
                ['network' => $networkconfig]
            ), $url 
        );
    }

    // Access Control - remove IP
    if (is_array($_POST) ) {
        foreach ( $_POST as $postvar => $postvalue ) {
            if (strncmp($postvar, 'submit_access_nfs_acremove_', 27) === 0
            ) {
                $acrecord = base64_decode(
                    substr(
                        $postvar,
                        strlen('submit_access_nfs_acremove_') 
                    ) 
                );
                if (strpos($acrecord, '/') === false ) {
                    friendlyerror('cannot remove IP from access control configuration!', $url);
                }
                $sharenfs = nfs_sharenfs_list($fs);
                $networkconfig = [];
                if (is_array($sharenfs[ $fs ][ 'options' ][ 'network' ]) ) {
                    foreach ( @$sharenfs[ $fs ][ 'options' ][ 'network' ] as $acip ) {
                        if ($acip != $acrecord ) {
                            $networkconfig[] = $acip;
                        }
                    }
                }
                if (( count($networkconfig) + 1 ) !==          count($sharenfs[ $fs ][ 'options' ][ 'network' ])
                ) {
                    friendlyerror('IP not found in access control configuration!', $url);
                }
                dangerouscommand(
                    nfs_setsharenfs(
                        $fs,
                        ['network' => $networkconfig]
                    ), $url 
                );
            }
        }
    }
    redirect_url($url);
}

function submit_access_nfs_advanced() 
{
    // required library
    activate_library('nfs');

    // data
    $url = 'access.php?nfs&q=' . @$_GET[ 'q' ];
    $nfsfs = @$_POST[ 'nfs_fsname' ];
    if ($nfsfs === '') {
        friendlyerror('invalid form submitted; missing NFS filesystem name', $url);
    }

    // scan POST vars for selected checkboxes
    $newconfig = [];
    foreach ( $_POST as $postname => $postvalue ) {
        if (strncmp($postname, 'cb_nfsoption_', 13) === 0) {
            $option = substr($postname, strlen('cb_nfsoption_'));
            $index = 'text_nfsoption_'.$option;
            if (($option !== 'network') && $option && @isset($_POST[$index])) {
                $newconfig[ $option ][] = @$_POST[ 'text_nfsoption_' . $option ];
            }
        }
    }

    // execute and redirect
    dangerouscommand(nfs_setsharenfs($nfsfs, $newconfig), $url);
    redirect_url($url);
}
