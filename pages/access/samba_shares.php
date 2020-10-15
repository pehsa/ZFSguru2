<?php

/**
 * @return array
 */
function content_access_samba_shares()
{
    // required modules
    activate_library('internalservice');
    activate_library('samba');

    // remove NFS share (click on trash bin icon)
    if (@isset($_GET[ 'removeshare' ]) ) {
        $sambaconf = samba_readconfig();
        if (@isset($sambaconf['shares'][$_GET['removeshare']])) {
            samba_removeshare($_GET[ 'removeshare' ]);
        } else {
            friendlyerror(
                'cannot remove "' . $_GET[ 'removeshare' ]
                . '" because the share was not found.', 'access.php?shares'
            );
        }
        redirect_url('access.php?shares');
    }

    // javascript + stylesheet
    page_register_javascript('pages/access/samba_shares.js');
    page_register_stylesheet('pages/access/widget_itemlist.css');
    page_register_stylesheet('pages/access/widget_userdrag.css');
    page_register_javascript('pages/access/widget_userdrag.js');
    page_register_stylesheet('pages/access/widget_extratab.css');
    page_register_javascript('pages/access/widget_extratab.js');

    // read samba configuration
    $sambaconf = samba_readconfig();
    if ($sambaconf === false ) {
        error('Could not read Samba configuration file!');
    }

    // check whether Samba is running
    $isrunning = internalservice_querystart('samba');

    // classes
    $class_notrunning = ( !$isrunning ) ? 'normal' : 'hidden';
    $class_corruptconfig = ( $sambaconf === false ) ? 'normal' : 'hidden';
    $class_noshares = ( @count($sambaconf[ 'shares' ]) < 1 ) ? 'normal' : 'hidden';
    $class_deleteselectedshares = ( $class_noshares === 'hidden' ) ?
    '' : 'disabled="disabled"';

    // get samba user group list
    $grouplist = samba_usergroups();
    // process tables
    $table_samba_sharelist = table_samba_sharelist($sambaconf);
    $table_samba_standardusers = table_samba_standardusers($grouplist);

    // queried share
    $queryshare = @$_GET[ 'share' ];
    $class_noquery = 'normal';
    if ($queryshare == '') {
        // activate library for shares_zfsfslist
        activate_library('html');
        if (@strpos($_GET[ 'newshare' ], '/') !== false ) {
            $newshare_name = @htmlentities(
                substr(
                    $_GET[ 'newshare' ],
                    strrpos($_GET[ 'newshare' ], '/') + 1 
                ) 
            );
        } else {
            $newshare_name = @htmlentities($_GET[ 'newshare' ]);
        }
        $shares_zfsfslist = html_zfsfilesystems(false, @$_GET[ 'newshare' ]);
        // set div visibility classes
        $class_query = 'hidden';
        $class_newshare = ( $newshare_name ) ? 'normal' : 'hidden';
        $class_nonewshare = ( !$newshare_name ) ? 'normal' : 'hidden';
    } elseif (@isset($sambaconf['shares'][$queryshare])) {
        // display only queried share
        $table_samba_sharelist = [
        $queryshare => $table_samba_sharelist[ $queryshare ]
        ];
        $class_query = 'normal';
        $class_noquery = 'hidden';
        $querytarget = @htmlentities($sambaconf[ 'shares' ][ $queryshare ][ 'path' ]);
        $querycomment = @htmlentities($sambaconf[ 'shares' ][ $queryshare ][ 'comment' ]);
        // process tables
        $table_samba_groups = table_samba_groups($grouplist);
        $table_samba_fullaccess =
        table_samba_share_accesslist('fullaccess', $queryshare, $sambaconf);
        $table_samba_readonly =
        table_samba_share_accesslist('readonly', $queryshare, $sambaconf);
        $table_samba_noaccess =
        table_samba_share_accesslist('noaccess', $queryshare, $sambaconf);
        $table_samba_sharevars = table_samba_sharevariables($sambaconf, $queryshare);
        // make the grouplist open by default when no standard users are configured
        $class_userlist = '';
        $class_grouplist = '';
        if (@count(reset($grouplist)) > 0 ) {
            $class_userlist = 'active';
        } else {
            $class_grouplist = 'active';
        }
    } else {
        friendlyerror(
            'no share with the name <b>' . htmlentities($queryshare)
            . '</b> exists!', $url
        );
    }

    // classes
    $class_newshare = ( @$newshare_name ) ? 'normal' : 'hidden';
    $class_nonewshare = ( !@$newshare_name ) ? 'normal' : 'hidden';

    // export new tags
    return @[
    'PAGE_TITLE' => 'Samba shares',
    'PAGE_ACTIVETAB' => 'Shares',
    'CLASS_SAMBA_NOTRUNNING' => $class_notrunning,
    'CLASS_SAMBA_CORRUPTCONFIG' => $class_corruptconfig,
    'CLASS_SM_SHARE' => $sm_share,
    'CLASS_SM_DOMAIN' => $sm_domain,
    'CLASS_SM_ADS' => $sm_ads,
    'CLASS_SM_SERVER' => $sm_server,
    'CLASS_AB_LDAPSAM' => $ab_ldapsam,
    'CLASS_AB_SMBPASSWD' => $ab_smbpasswd,
    'CLASS_SAMBA_NOSHARES' => $class_noshares,
    'CLASS_DELETESELECTEDSHARES' => $class_deleteselectedshares,
    'SAMBA_WORKGROUP' => $workgroup,

    // Samba shares
    'TABLE_SAMBA_SHARELIST' => $table_samba_sharelist,
    'TABLE_SAMBA_GROUPS' => $table_samba_groups,
    'TABLE_SAMBA_STANDARDUSERS' => $table_samba_standardusers,
    'TABLE_SAMBA_FULLACCESS' => $table_samba_fullaccess,
    'TABLE_SAMBA_READONLY' => $table_samba_readonly,
    'TABLE_SAMBA_NOACCESS' => $table_samba_noaccess,
    'TABLE_SAMBA_SHAREVARS' => $table_samba_sharevars,
    'CLASS_QUERY' => $class_query,
    'CLASS_NOQUERY' => $class_noquery,
    'CLASS_NEWSHARE' => $class_newshare,
    'CLASS_NONEWSHARE' => $class_nonewshare,
    'CLASS_USERLIST' => $class_userlist,
    'CLASS_GROUPLIST' => $class_grouplist,
    'QUERY_SHARENAME' => $queryshare,
    'QUERY_TARGET' => $querytarget,
    'QUERY_COMMENT' => $querycomment,
    'QUERY_BROWSE' => $querybrowse,
    'QUERY_WRITABLE' => $querywritable,
    'QUERY_PUBLIC' => $querypublic,
    'NEWSHARE_NAME' => $newshare_name,
    'SHARES_ZFSFSLIST' => $shares_zfsfslist,
    ];
}

/**
 * @param $sambaconf
 *
 * @return array
 */
function table_samba_sharelist( $sambaconf )
{
    $table_shares = [];
    if (@is_array($sambaconf[ 'shares' ]) ) {
        foreach ( $sambaconf[ 'shares' ] as $sharename => $share ) {
            // set some checkboxes on/off according to data
            // TODO: deprecated: samba config aliases handled by samba_readconfig now
            // default if non present:
            // browseable = yes (synonym: browsable)
            // read only = yes (inverted synonym: writeable)
            // guest ok = no (inverted synonym: guest ok)
            $adv_nobrowse = '';
            if (@$share[ 'browseable' ] === 'no' ) {
                $adv_nobrowse = 'selected';
            }
            $adv_noreadonly = '';
            if (@$share[ 'read only' ] === 'no' ) {
                $adv_noreadonly = 'selected';
            }
            $adv_guestnotok = '';
            if (@$share[ 'guest ok' ] === 'no' ) {
                $adv_guestnotok = 'selected';
            }

            // extra share options
            $share_extra = '';
            $table_share_extra = [];
            foreach ( $share as $name => $value ) {
                if (!in_array(
                    trim($name), [
                                   'path', 'comment', 'browseable',
                    'read only', 'guest ok'
                               ]
                ) 
                ) {
                    $table_share_extra[] = [
                    'SE_VARNAME' => htmlentities($name),
                    'SE_DISPLAYNAME' => htmlentities(ucfirst($name)),
                    'SE_VALUE' => htmlentities($value)
                    ];
                }
            }

            // activerow
            $activerow = ( ( @$_GET[ 'share' ] )AND( @$_GET[ 'share' ] == $sharename ) ) ?
            'activerow' : 'normal';

            // access type
            $sambapermissions = samba_share_permissions($sambaconf, $sharename);
            $access_type = samba_share_accesstype($sambapermissions);
            // path suffix (filesystem/dir name)
            $path_suffix = substr(@$share[ 'path' ], strrpos($share[ 'path' ], '/') + 1);

            // set classes for each type
            $alltypes = [
                'public', 'protected', 'private', 'custom',
            'noaccess', 'disabled', 'problem'
            ];
            foreach ( $alltypes as $type ) {
                $access[ $type ] = 'hidden';
            }
            $access[ $access_type ] = 'normal';

            // add row to table
            $table_shares[ $sharename ] = [
            'TABLE_SHARE_EXTRA' => $table_share_extra,
            'SHARE_CLASS' => $activerow,
            'SHARE_NAME' => htmlentities($sharename),
            'SHARE_COMMENT' => @$share[ 'comment' ],
            'SHARE_PATH' => @$share[ 'path' ],
            'SHARE_PATH_SUFFIX' => $path_suffix,
            // advanced settings
            'SHARE_NOBROWSE' => $adv_nobrowse,
            'SHARE_NOREADONLY' => $adv_noreadonly,
            'SHARE_GUESTNOTOK' => $adv_guestnotok,
            // images
            'SHARE_PUBLIC' => $access[ 'public' ],
            'SHARE_PROTECTED' => $access[ 'protected' ],
            'SHARE_PRIVATE' => $access[ 'private' ],
            'SHARE_CUSTOM' => $access[ 'custom' ],
            'SHARE_NOACCESS' => $access[ 'noaccess' ],
            'SHARE_DISABLED' => $access[ 'disabled' ],
            'SHARE_PROBLEM' => $access[ 'problem' ],
            ];
        }
    }
    return $table_shares;
}

/**
 * @return array
 */
function table_samba_globalvariables()
{
    // required library
    activate_library('samba');
    $configvars = samba_variables_global();
    $table_configvars = [];
    foreach ( $configvars as $varname ) {
        if (!@isset($sambaconf[ 'res' ][ $sharename ][ $varname ]) ) {
            $table_configvars[] = [
                'CV_VAR' => htmlentities($varname)
            ];
        }
    }
    return $table_configvars;
}

/**
 * @param       $sambaconf
 * @param false $sharename
 *
 * @return array
 */
function table_samba_sharevariables( $sambaconf, $sharename = false )
{
    // required library
    activate_library('samba');
    $configvars = samba_variables_share();
    $table_configvars = [];
    foreach ( $configvars as $varname ) {
        if (!$sharename OR( !@isset($sambaconf[ 'shares' ][ $sharename ][ $varname ]) ) ) {
            $table_configvars[] = [
                'CV_VAR' => htmlentities($varname)
            ];
        }
    }
    return $table_configvars;
}

/**
 * @param $grouplist
 *
 * @return array
 */
function table_samba_standardusers( $grouplist )
{
    $table_standardusers = [];
    foreach ( reset($grouplist) as $user ) {
        $table_standardusers[] = [
        'SU_USERNAME' => htmlentities($user),
        'SU_USERUCFIRST' => htmlentities(ucfirst($user))
        ];
    }
    return $table_standardusers;
}

/**
 * @param $grouplist
 *
 * @return array
 */
function table_samba_groups( $grouplist )
{
    $table_sambagroups = [];
    foreach ( $grouplist as $groupname => $users ) {
        $table_users = [];
        foreach ( $users as $user ) {
            $table_users[] = [
            'SAMBAUSER_USERNAME' => htmlentities($user),
            'SAMBAUSER_USERUCFIRST' => htmlentities(ucfirst($user))
            ];
        }
        $class_hasusers = ( !empty($table_users) ) ? 'normal' : 'hidden';
        $stdgroup = ( $groupname === 'share' );
        $display_shares = ( $stdgroup ) ? 'Everyone' :
        htmlentities(ucfirst($groupname));
        $display_users = ( $stdgroup ) ? 'Standard users' :
        htmlentities(ucfirst($groupname));
        $specialgroup = ( $stdgroup ) ? 'normal' : 'hidden';
        $suffix = ( $stdgroup ) ? '_special' : '';
        $table_sambagroups[] = [
        'TABLE_SAMBA_USERS' => $table_users,
        'CLASS_SAMBAGROUP_HASUSERS' => $class_hasusers,
        'SAMBAGROUP_GROUPNAME' => htmlentities($groupname),
        'SAMBAGROUP_DISPLAY_SHARES' => $display_shares,
        'SAMBAGROUP_DISPLAY_USERS' => $display_users,
        'SAMBAGROUP_SPECIAL' => $specialgroup,
        'SAMBAGROUP_SUFFIX' => $suffix
        ];
    }
    return $table_sambagroups;
}

/**
 * @param $accesstype
 * @param $sharename
 * @param $sambaconf
 *
 * @return array
 */
function table_samba_share_accesslist( $accesstype, $sharename, $sambaconf )
{
    $table_accesslist = [];
    $shareperms = samba_share_permissions($sambaconf, $sharename);
    if (@is_array($shareperms[ $accesstype ]) ) {
        foreach ( $shareperms[ $accesstype ] as $name ) {
            if ($name === 'guest' ) {
                $type = 'sambaguestxx';
                $image = 'user-guest';
            } elseif (( $name {           0          } == '+' )OR( $name {           0          } === '@' )
            ) {
                $type = 'sambagroupxx';
                $image = 'group';
            }
            else {
                $type = 'standarduser';
                $image = 'user';
            }
            $rname = ( $type === 'sambagroupxx' ) ? substr($name, 1) : $name;
            $uname = ( $name === '+share'
            OR $name === '@share' ) ? 'Everyone' : $rname;
            $table_accesslist[] = [
            'SP_TYPE' => $type,
            'SP_NAME' => htmlentities($rname),
            'SP_UCFIRST' => htmlentities(ucfirst($uname)),
            'SP_IMAGE' => $image,
            ];
        }
    }
    return $table_accesslist;
}



/* submit functions */

function submit_access_samba_shares_create() 
{
    // required libraries
    activate_library('samba');
    activate_library('zfs');

    // POST data
    $zfsfs = @$_POST[ 'newshare_zfsfs' ];
    $custommp = @$_POST[ 'newshare_mp' ];
    sanitize(@$_POST[ 'newshare_sharename' ], 'a-zA-Z0-9\-\_\.', $sambasharename, 12);
    $comment = @$_POST[ 'newshare_comment' ];
    $accessprofile = @$_POST[ 'newshare_accessprofile' ];
    $accessprivate = @$_POST[ 'newshare_accessprivate' ];

    // redirect URL
    $url = 'access.php?shares&newshare';
    $url2 = $url . '=' . $sambasharename;

    // call functions
    $sambaconf = samba_readconfig();
    if ($zfsfs === '/mp/' ) {
        $mountpoint = $custommp;
    } else {
        $zfsmp = zfs_filesystem_properties($zfsfs, 'mountpoint');
        $mountpoint = @$zfsmp[ $zfsfs ][ 'mountpoint' ][ 'value' ];
    }

    // sanity checks
    $reservedsharenames = ['global', 'homes', 'printers'];
    foreach ( $reservedsharenames as $reservedname ) {
        if ($sambasharename == $reservedname ) {
            friendlyerror(
                'you have chosen a reserved name, '
                . 'please choose a different name!', $url 
            );
        }
    }
    if ($sambasharename == '') {
        friendlyerror('failed creating a samba name for this filesystem', $url);
    }
    if (@isset($sambaconf[ 'shares' ][ $sambasharename ]) ) {
        friendlyerror('this share name already exists!', $url);
    }
    if ($mountpoint {        0        } !== '/'
    ) {
        friendlyerror(
            'incorrect mountpoint supplied; '
            . 'it should start with a forward slash!', $url 
        );
    }

    // craft new share based on chosen share profile
    $newshare = [
    'path' => $mountpoint,
    'comment' => $comment,
    'browsable' => 'yes',
    'guest ok' => 'no',
    'read only' => 'yes',
    'write list' => '@share'
    ];
    // apply access profile
    if ($accessprofile === 'public' ) {
        $newshare[ 'guest ok' ] = 'yes';
        $newshare[ 'read only' ] = 'no';
    } elseif ($accessprofile === 'private' ) {
        $newshare[ 'write list' ] = $accessprivate;
    } elseif ($accessprofile === 'noaccess' ) {
        unset($newshare[ 'write list' ]);
    }
    // add new share and save configuration
    $sambaconf[ 'shares' ][ $sambasharename ] = $newshare;
    $result = samba_writeconfig($sambaconf);
    if ($result AND( $zfsfs === '/mp/' ) ) {
        page_feedback(
            'created a new share called <b>' . htmlentities($sambasharename)
            . '</b> pointing to <b>' . htmlentities($mountpoint) . '</b>', 'b_success' 
        );
    } elseif ($result ) {
        page_feedback(
            'created a new share called <b>' . htmlentities($sambasharename)
            . '</b> pointing to <b>' . htmlentities($zfsfs) . '</b>', 'b_success' 
        );
    } else {
        page_feedback('could not save Samba configuration!', 'a_failure');
    }
    // redirect to share query page
    redirect_url($url2);
}

function submit_access_samba_shares_remove() 
{
    // required modules
    activate_library('samba');

    // read samba configuration
    $sambaconf = samba_readconfig();
    if ($sambaconf === false ) {
        error('Could not read Samba configuration file!');
    }

    // redirect URL
    $redir = 'access.php?shares';

    // check for submitted form
    if (@isset($_POST[ 'submit_sambadeleteshares' ]) ) {
        // only remove shares and write changes to disk
        $newconf = $sambaconf;
        $removed_arr = [];
        foreach ( $_POST as $name => $value ) {
            if (strncmp($name, 'cb_sambashare_', 14) === 0) {
                $sharename = trim(substr($name, strlen('cb_sambashare_')));
                $removed_arr[] = $sharename;
                unset($newconf[ 'shares' ][ $sharename ]);
            }
        }
        // save configuration
        if (count($removed_arr) > 0 ) {
            $result = samba_writeconfig($newconf);
            // redirect
            if ($result !== true ) {
                error('Error writing Samba configuration file ("' .false. '")');
            } else {
                friendlynotice(
                    'removed the following shares: <b>'
                    . implode(', ', $removed_arr) . '</b>', $redir 
                );
            }
        } else {
            friendlynotice('no shares selected for deletion!', $redir);
        }
    }
    redirect_url($redir);
}

function submit_access_samba_shares_advanced() 
{
    // elevated privileges
    activate_library('samba');

    // read samba configuration
    $sambaconf = samba_readconfig();

    // fetch POST data
    $sharename = @$_POST[ 'advanced_sharename' ];
    $newvar_varname = @$_POST[ 'newvariable_varname' ];
    $newvar_value = @$_POST[ 'newvariable_value' ];

    // redirect URL
    $redir = 'access.php?share=' . $sharename;

    // sanity check
    if (!$sambaconf[ 'shares' ][ $sharename ] ) {
        friendlyerror('invalid form submitted', $redir);
    }

    // restart samba
    if (@isset($_POST[ 'samba_restart_samba' ]) ) {
        $result = samba_restartservice();
        if ($result == 0 ) {
            friendlynotice('samba restarted!', $redir);
        } else {
            friendlyerror('could not restart Samba (' . $result . ')', $redir);
        }
    }

    // change all advanced variables related to share
    foreach ( $_POST as $postvar => $postvalue ) {
        if ((strncmp($postvar, 'advancedvar_', 12) === 0) && $postvalue != '') {
            $postvariable = trim(
                str_replace(
                    '_', ' ',
                    substr($postvar, strlen('advancedvar_'))
                )
            );
            $sambaconf[ 'shares' ][ $sharename ][ $postvariable ] = $postvalue;
        }
    }

    // remove variables with checkbox checked
    foreach ( $_POST as $postvar => $postvalue ) {
        if ((strncmp($postvar, 'cb_advanced_', 12) === 0) && $postvalue === 'on') {
            $postvariable = trim(
                str_replace(
                    '_', ' ',
                    substr($postvar, strlen('cb_advanced_'))
                )
            );
            unset($sambaconf[ 'shares' ][ $sharename ][ $postvariable ]);
        }
    }

    // add new variable to samba share configuration
    if ($newvar_varname != '' AND $newvar_value != '') {
        $sambaconf[ 'shares' ][ $sharename ][ $newvar_varname ] = $newvar_value;
    }

    // write modified samba configuration to disk
    samba_writeconfig($sambaconf);
    redirect_url($redir);
}

function submit_access_samba_shares_dragdrop() 
{
    // elevated privileges
    activate_library('samba');
    activate_library('super');

    // fetch POST data
    $sharename = @$_POST[ 'samba_sharename' ];
    $dragname = @$_POST[ 'samba_shares_name' ];
    $dragtype = @$_POST[ 'samba_shares_type' ];
    $dragtarget = @$_POST[ 'samba_shares_target' ];

    // redirect URL
    if ($sharename ) {
        $redir = 'access.php?share=' . urlencode($sharename);
    } else {
        redirect_url('access.php?shares');
    }

    // samba configuration
    $sambaconf = samba_readconfig();
    $sambashareperms = samba_share_permissions($sambaconf, $sharename);

    // conversion array
    $conv = [
    'fullaccess' => 'write list',
    'readonly' => 'read list',
    'noaccess' => 'invalid users'
    ];

    // act depending on drag and drop type
    if ($dragtype === 'standarduser'
        OR $dragtype === 'useringroup'
    ) {
        $list_arr = @explode(
            ' ', trim(
                $sambaconf[ 'shares' ][ $sharename ][ $conv[ $dragtarget ] ] 
            ) 
        );
        if (in_array($dragname, $list_arr, true)) {
            friendlyerror(
                'user <b>' . htmlentities($dragname) . '</b> already part of this '
                . 'access list', $redir 
            );
        }
        // remove dragitem from all access lists
        $aclist = [];
        foreach ( $conv as $conv_a => $conv_b ) {
            $tmp_arr = @explode(
                ' ', trim(
                    $sambaconf[ 'shares' ][ $sharename ][ $conv_b ] 
                ) 
            );
            foreach ( $tmp_arr as $tmp_item ) {
                if (($tmp_item != '') && $tmp_item != $dragname) {
                    $aclist[ $conv_b ][ $tmp_item ] = $tmp_item;
                }
            }
        }
        // add dragitem to appropriate list
        if (@isset($conv[ $dragtarget ]) ) {
            $aclist[ $conv[ $dragtarget ] ][ $dragname ] = $dragname;
        }
        // update samba configuration array to reflect changes in access lists
        foreach ( $conv as $conv_a => $conv_b ) {
            if (@is_array($aclist[ $conv_b ]) ) {
                $sambaconf[ 'shares' ][ $sharename ][ $conv_b ] = implode(' ', $aclist[ $conv_b ]);
            } elseif (@isset($sambaconf[ 'shares' ][ $sharename ][ $conv_b ]) ) {
                unset($sambaconf[ 'shares' ][ $sharename ][ $conv_b ]);
            }
        }
        // save configuration
        samba_writeconfig($sambaconf);
    } elseif ($dragtype === 'sambagroup' ) {
        $list_arr = @explode(
            ' ', trim(
                $sambaconf[ 'shares' ][ $sharename ][ $conv[ $dragtarget ] ] 
            ) 
        );
        if (in_array('+'.$dragname, $list_arr, true) OR in_array('@'.$dragname, $list_arr, true)) {
            friendlyerror(
                'group <b>' . htmlentities($dragname) . '</b> already part of this '
                . 'access list', $redir 
            );
        }
        // remove dragname from all lists
        $aclist = [];
        foreach ( $conv as $conv_a => $conv_b ) {
            $tmp_arr = @explode(
                ' ', trim(
                    $sambaconf[ 'shares' ][ $sharename ][ $conv_b ] 
                ) 
            );
            foreach ( $tmp_arr as $tmp_item ) {
                if (($tmp_item != '') && !preg_match('/(@+])'.$dragname.'/', $tmp_item)) {
                    $aclist[ $conv_b ][ $tmp_item ] = $tmp_item;
                }
            }
        }
        // add group to list
        $aclist[ $conv[ $dragtarget ] ][ '@' . $dragname ] = '@' . $dragname;
        foreach ( $conv as $conv_a => $conv_b ) {
            if (@is_array($aclist[ $conv_b ]) ) {
                $sambaconf[ 'shares' ][ $sharename ][ $conv_b ] = implode(' ', $aclist[ $conv_b ]);
            } elseif (@isset($sambaconf[ 'shares' ][ $sharename ][ $conv_b ]) ) {
                unset($sambaconf[ 'shares' ][ $sharename ][ $conv_b ]);
            }
        }
        samba_writeconfig($sambaconf);
    }
    elseif ($dragtype === 'sambaguest' ) {
        if ($dragtarget === 'fullaccess' ) {
            $sambaconf[ 'shares' ][ $sharename ][ 'guest ok' ] = 'yes';
            $sambaconf[ 'shares' ][ $sharename ][ 'read only' ] = 'no';
        } elseif ($dragtarget === 'readonly' ) {
            $sambaconf[ 'shares' ][ $sharename ][ 'guest ok' ] = 'yes';
            $sambaconf[ 'shares' ][ $sharename ][ 'read only' ] = 'yes';
        }
        elseif ($dragtarget === 'noaccess' ) {
            $sambaconf[ 'shares' ][ $sharename ][ 'guest ok' ] = 'no';
        }
        // save samba configuration
        samba_writeconfig($sambaconf);
    }
    else {
        friendlyerror('incorrect FORM submission', $redir);
    }

    redirect_url($redir);
}
