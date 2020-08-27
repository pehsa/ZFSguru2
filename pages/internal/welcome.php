<?php

function content_internal_welcome() 
{
    global $guru;

    // classes
    $class_w0 = 'hidden';
    $class_w1 = 'hidden';
    $class_w2 = 'hidden';
    $class_w3 = 'hidden';
    $class_w4 = 'hidden';
    $class_w5 = 'hidden';
    if (@isset($_GET[ 'welcome5' ]) ) {
        $class_w5 = 'normal';
    } elseif (@isset($_GET[ 'welcome4' ]) ) {
        $class_w4 = 'normal';
    } elseif (@isset($_GET[ 'welcome3' ]) ) {
        $class_w3 = 'normal';
    } elseif (@isset($_GET[ 'welcome2' ]) ) {
        $class_w2 = 'normal';
    } elseif (@isset($_GET[ 'welcome1' ]) ) {
        $class_w1 = 'normal';
    } else {
        $class_w0 = 'normal';
        page_rawfile('pages/internal/intro.page');
        die();
    }

    // step 1: protection
    if ($class_w1 === 'normal' ) {
        $w1_ac_1 = ( @$_SESSION[ 'welcomewizard' ][ 'access_control' ] == 1 ) ?
        'checked="checked"' : '';
        $w1_ac_2 = ( ( @$_SESSION[ 'welcomewizard' ][ 'access_control' ] == 2 )OR( !@isset($_SESSION[ 'welcomewizard' ][ 'access_control' ]) ) ) ?
        'checked="checked"' : '';
        $w1_ac_3 = ( @$_SESSION[ 'welcomewizard' ][ 'access_control' ] == 3 ) ?
        'checked="checked"' : '';
        $w1_noauth = ( @strlen($_SESSION[ 'welcomewizard' ][ 'authentication' ]) > 0 ) ?
        '' : 'checked="checked"';
        $w1_auth = ( @strlen($_SESSION[ 'welcomewizard' ][ 'authentication' ]) > 0 ) ?
        'checked="checked"' : '';
        $w1_auth_pw = ( @strlen($_SESSION[ 'welcomewizard' ][ 'authentication' ]) > 0 ) ?
        $_SESSION[ 'welcomewizard' ][ 'authentication' ] : '';
        $w1_user_ipaddr = $_SERVER[ 'REMOTE_ADDR' ];
    }

    // step 2: physical disks
    $physdisks = array();
    if ($class_w2 === 'normal' ) {
        // required library
        activate_library('disk');
        // call functions
        $disks = disk_detect_physical();
        $dmesg = disk_detect_dmesg();
        $gpart = disk_detect_gpart();
        $labels = disk_detect_label();

        // variables
        $diskcount = @( int )count($disks);
        $querydisk = @$_GET[ 'query' ];

        // list each disk (partition)
        if (@is_array($disks) ) {
            foreach ( $disks as $diskname => $data ) {
                $activerow = ( $querydisk == $diskname ) ? 'class="activerow"' : '';
                if ($data[ 'sectorsize' ] == '512' ) {
                    // standard sector size
                    $sectorsize = '512 B';
                    $sectorclass = 'network_sector_normal';
                } else {
                    // native high sector size
                    $sectorsize = @sizebinary($data[ 'sectorsize' ]);
                    $sectorclass = 'high';
                }

                // process GPT/GEOM label string
                $labelstr = '';
                if (@strlen($labels[ $diskname ]) > 0 ) {
                    $labelstr .= 'GEOM: ' . @htmlentities($labels[ $diskname ]);
                }
                if (@strlen($gpart[ $diskname ][ 'label' ]) > 0 ) {
                    if ($labelstr !== '') {
                        $labelstr .= '<br />';
                    }
                    $labelstr .= 'GPT: ' . @htmlentities($gpart[ $diskname ][ 'label' ]);
                }

                // add new row to table array
                $physdisks[] = array(
                'DISK_ACTIVEROW' => $activerow,
                'DISK_NAME' => htmlentities($diskname),
                'DISK_LABEL' => $labelstr,
                'DISK_SIZE_LEGACY' => @sizehuman($data[ 'mediasize' ], 1),
                'DISK_SIZE_BINARY' => @sizebinary($data[ 'mediasize' ], 1),
                'DISK_CLASS_SECTOR' => $sectorclass,
                'DISK_SIZE_SECTOR' => $sectorsize,
                'DISK_IDENTIFY' => @$dmesg[ $diskname ]
                );
            }
        }
    }

    // step 3: ZFS pools
    if ($class_w3 === 'normal' ) {
        // required libraries
        activate_library('html');
        activate_library('zfs');

        // call functions
        $zpools = zfs_pool_list();
        $zfsver = zfs_version();

        // include stylesheet from pools.php for import styling
        page_register_stylesheet('pages/pools/pools.css');

        // pool count
        $poolcount = count($zpools);
        $poolcountstr = ( $poolcount == 1 ) ? '' : 's';

        // process table poollist
        $bootablepools = false;
        foreach ( $zpools as $poolname => $pooldata ) {
            $activerow = ( @$_GET[ 'query' ] == $poolname ) ? ' class="activerow"' : '';
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
            $poollist[] = array(
            'POOLLIST_ACTIVEROW' => $activerow,
            'POOLLIST_POOLNAME' => htmlentities(trim($poolname)),
            'POOLLIST_SPA' => $poolspa,
            'POOLLIST_REDUNDANCY' => $redundancy,
            'POOLLIST_SIZE' => $pooldata[ 'size' ],
            'POOLLIST_USED' => $pooldata[ 'used' ],
            'POOLLIST_FREE' => $pooldata[ 'free' ],
            'POOLLIST_STATUS' => $pooldata[ 'status' ],
            'POOLLIST_POOLNAME_URLENC' => htmlentities(trim($poolname))
            );

            // check boot status by looking whether at least one member has GPT label
            $poolstatus = zfs_pool_status($poolname);
            foreach ( $poolstatus[ 'members' ] as $memberdata ) {
                if (@substr($memberdata[ 'name' ], 0, strlen('gpt/')) === 'gpt/' ) {
                    $bootablepools = true;
                }
            }
        }

        // SPA list
        $poolspa = '';
        for ( $i = 1; $i <= $zfsver[ 'spa' ]; $i++ ) {
            if (( $i <= 28 )OR( $i >= 5000 ) ) {
                $userver = ( ( ( int )@$_GET[ 'spa' ] > 0 )AND( ( int )@$_GET[ 'zpl' ] > 0 ) );
                $userchosen = ( @$_GET[ 'spa' ] == $i );
                $selected = ( $userchosen OR( !$userver ) ) ? 'selected ' : '';
                $poolspa .= ( '  <option ' . $selected . 'value="' . $i . '">' . $i );
                if ($userchosen ) {
                    $poolspa .= ' (selected)';
                }
                if ($i == 5000 ) {
                    $poolspa .= ' (feature flags)';
                }
                $poolspa .= '</option>' . chr(10);
            }
        }

        // ZPL list
        $poolzpl = '';
        for ( $i = 1; $i <= $zfsver[ 'zpl' ]; $i++ ) {
            $userver = ( ( ( int )@$_GET[ 'spa' ] > 0 )AND( ( int )@$_GET[ 'zpl' ] > 0 ) );
            $userchosen = ( @$_GET[ 'zpl' ] == $i );
            $selected = ( $userchosen OR( !$userver ) ) ? 'selected ' : '';
            $poolzpl .= ( '  <option ' . $selected . 'value="' . $i . '">' . $i );
            if ($userchosen ) {
                $poolzpl .= ' (selected)';
            }
            $poolzpl .= '</option>' . chr(10);
        }

        // radio button (zfs version)
        $specifyversion = ( @$_GET[ 'spa' ]OR @$_GET[ 'zpl' ] );
        $radio_modernzfs = ( !$specifyversion ) ? 'checked="checked"' : '';
        $radio_specify = ( $specifyversion ) ? 'checked="checked"' : '';

        // whole disks
        $wholedisks = html_wholedisks();

        // import classes
        $class_importable = 'hidden';
        $class_noimportables = 'hidden';

        // import data
        if (@!isset($_SESSION[ 'welcomewizard' ][ 'noimportablepools' ]) ) {
            $_SESSION[ 'welcomewizard' ][ 'noimportablepools' ] = false;
        }
        $noimportablepools = $_SESSION[ 'welcomewizard' ][ 'noimportablepools' ];
        $scannedforpools = false;

        // import pool table
        $table_importpool = array();
        if (@isset($_POST[ 'import_pool' ])OR @isset($_POST[ 'import_pool_deleted' ]) ) {
            $importdestroyed = @isset($_POST[ 'import_pool_deleted' ]);
            if ($importdestroyed ) {
                $importables = zfs_pool_import_list(true);
            } else {
                $importables = zfs_pool_import_list(false);
            }
            $scannedforpools = true;
            if (is_array($importables)AND count($importables) > 0 ) {
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
                // update SESSION that we have no more pools to import
                $noimportablepools = true;
                $_SESSION[ 'welcomewizard' ][ 'noimportablepools' ] = true;
                $class_noimportables = 'normal';
            }
        }

        // create pool button and text
        $w3_createpoolbutton = ( $noimportablepools ) ? '' : 'disabled="disabled"';
        $w3_createpooltext = ( $noimportablepools ) ? 'hidden' : 'normal';

        // visible sections
        $w3_class_poollist = ( !empty($poollist) ) ? 'normal' : 'hidden';
        $w3_class_poolcreate = ( $noimportablepools ) ? 'normal' : 'hidden';

        // advice box
        $pools = ( empty($zpools) ) ? false : true;
        $w3_advice_scan = ( !$noimportablepools AND!$scannedforpools ) ?
        'normal' : 'hidden';
        $w3_advice_import = ( !$noimportablepools AND $scannedforpools ) ?
        'normal' : 'hidden';
        $w3_advice_nopool = ( $noimportablepools AND!$pools ) ? 'normal' : 'hidden';
        $w3_advice_noboot = ( $noimportablepools AND $pools AND!$bootablepools ) ?
        'normal' : 'hidden';
        $w3_advice_continue = ( $noimportablepools AND $pools AND $bootablepools ) ?
        'normal' : 'hidden';
        $w3_advicebox = ( $w3_advice_continue === 'normal' ) ? 'continue' : 'stop';
    }

    if ($class_w4 === 'normal' ) {
        $class_datasent = ( @isset($_GET[ 'datasent' ]) ) ? 'normal' : 'hidden';
        $dmesg = file_get_contents('/var/run/dmesg.boot');
        $dpos = strrpos($dmesg, 'Copyright (c) 1992-2011 The FreeBSD Project.');
        $w4_dmesg = substr($dmesg, ( int )$dpos);
    }

    if ($class_w5 === 'normal' ) {
        // fetch activation options
        $act = @$_SESSION[ 'welcomewizard' ][ 'activation' ];
        $act_feedback = @$_SESSION[ 'welcomewizard' ][ 'feedback' ];
        $act_feedback_text = @$_SESSION[ 'welcomewizard' ][ 'feedback_text' ];
        $uuid = @$_SESSION[ 'welcomewizard' ][ 'uuid' ];

        // classes
        $class_activation_success = ( $act < 3 AND($uuid != '') ) ? 'normal' :
        'hidden';
        $class_activation_skipped = ( $act == 3 ) ? 'normal' : 'hidden';
        $class_activation_failure = ( $act < 3 AND!$uuid ) ? 'normal' : 'hidden';

        // finish welcome wizard (cause next pageview to be handled normally)
        if (( int )$act > 0 ) {
            finish_welcomewizard();
        }
    }

    // export new tags
    return @array(
    'TABLE_PHYSDISKS' => $physdisks,
    'TABLE_POOLLIST' => $poollist,
    'TABLE_IMPORTPOOL' => $table_importpool,
    'CLASS_WELCOME0' => $class_w0,
    'CLASS_WELCOME1' => $class_w1,
    'CLASS_WELCOME2' => $class_w2,
    'CLASS_WELCOME3' => $class_w3,
    'CLASS_WELCOME4' => $class_w4,
    'CLASS_WELCOME5' => $class_w5,
    'CLASS_IMPORTABLE' => $class_importable,
    'CLASS_NOIMPORTABLES' => $class_noimportables,
    'CLASS_DATASENT' => $class_datasent,
    'CLASS_ACTI_SUCCESS' => $class_activation_success,
    'CLASS_ACTI_SKIPPED' => $class_activation_skipped,
    'CLASS_ACTI_FAILURE' => $class_activation_failure,
    'W1_AC_1' => $w1_ac_1,
    'W1_AC_2' => $w1_ac_2,
    'W1_AC_3' => $w1_ac_3,
    'W1_NOAUTH' => $w1_noauth,
    'W1_AUTH' => $w1_auth,
    'W1_AUTH_PW' => $w1_auth_pw,
    'W1_IPADDR' => $w1_user_ipaddr,
    'W3_CLASS_POOLLIST' => $w3_class_poollist,
    'W3_CLASS_POOLCREATE' => $w3_class_poolcreate,
    'W3_RADIO_MODERNZFS' => $radio_modernzfs,
    'W3_RADIO_SPECIFY' => $radio_specify,
    'W3_SPALIST' => $poolspa,
    'W3_ZPLLIST' => $poolzpl,
    'W3_WHOLEDISKS' => $wholedisks,
    'W3_CREATEPOOLBUTTON' => $w3_createpoolbutton,
    'W3_CREATEPOOLTEXT' => $w3_createpooltext,
    'W3_ADVICEBOX' => $w3_advicebox,
    'W3_ADVICE_SCAN' => $w3_advice_scan,
    'W3_ADVICE_IMPORT' => $w3_advice_import,
    'W3_ADVICE_NOPOOL' => $w3_advice_nopool,
    'W3_ADVICE_NOBOOT' => $w3_advice_noboot,
    'W3_ADVICE_CONTINUE' => $w3_advice_continue,
    'W4_DMESG' => $w4_dmesg,
    'W5_ACTIVATION' => $act,
    'W5_FEEDBACK' => $act_feedback,
    'W5_FEEDBACK_TEXT' => $act_feedback_text
    );
}

function submit_welcome_submit_0() 
{
    if (@isset($_POST[ 'submit0' ]) ) {
        redirect_url('?welcome1');
    }
}

function submit_welcome_submit_1() 
{
    global $guru;

    if (@isset($_POST[ 'goback0' ]) ) {
        redirect_url('?welcome0');
    } elseif (@isset($_POST[ 'skip_wizard' ]) ) {
        skip_welcomewizard();
        redirect_url('status.php');
    }
    elseif (@isset($_POST[ 'submit1' ]) ) {
        // sanity check: if authentication chosen password must be set
        if ($_POST[ 'authentication' ] == 2 ) {
            if ($_POST['auth_pass1'] == '') {
                friendlyerror(
                    'if you select authentication, you must set a password',
                    '?welcome1' 
                );
            }
            if ($_POST[ 'auth_pass1' ] != $_POST[ 'auth_pass2' ] ) {
                friendlyerror(
                    'the password you chosen does not match the verification '
                    . 'password - please try again', '?welcome1' 
                );
            }
        }
        // save settings
        $_SESSION[ 'welcomewizard' ][ 'access_control' ] = ( int )$_POST[ 'access_control' ];
        $_SESSION[ 'welcomewizard' ][ 'access_whitelist' ][ $_SERVER[ 'REMOTE_ADDR' ] ] =
        $_SERVER[ 'REMOTE_ADDR' ];
        if ($_POST[ 'authentication' ] == 2 ) {
            $_SESSION[ 'welcomewizard' ][ 'authentication' ] = ( string )$_POST[ 'auth_pass1' ];
        } else {
            $_SESSION[ 'welcomewizard' ][ 'authentication' ] = '';
        }
        // redirect
        redirect_url('?welcome2');
    }
}

function submit_welcome_submit_2() 
{
    if (@isset($_POST[ 'goback1' ]) ) {
        redirect_url('?welcome1');
    } elseif (@isset($_POST[ 'submit2' ]) ) {
        redirect_url('?welcome3');
    }
}

function submit_welcome_submit_3() 
{
    global $guru;

    // redirect URL
    $url = '?welcome3';

    if (@isset($_POST[ 'goback2' ]) ) {
        redirect_url('?welcome2');
    } elseif (@isset($_POST[ 'submit3' ]) ) {
        redirect_url('?welcome4');
    } elseif (@isset($_POST[ 'submit_createnewzpool' ]) ) {
        activate_library('disk');
        activate_library('zfs');

        // create new ZFS pool
        $s = sanitize(@$_POST[ 'new_zpool_name' ], null, $poolname, 32);
        if (!$s ) {
            friendlyerror(
                'please enter a valid pool name using only alphanumerical '
                . '+ underscore (_) + dash characters (-)', $url 
            );
        }
        $zpl = $_POST[ 'new_zpool_zpl' ];
        $spa = $_POST[ 'new_zpool_spa' ];
        $sectorsize = ( @$_POST[ 'new_zpool_sectorsize' ] ) ?
        ( int )$_POST[ 'new_zpool_sectorsize' ] : 512;

        // scan for selected whole disks
        $wholedisks = array();
        foreach ( $_POST as $var => $value ) {
            if ((strpos($var, 'addwholedisk_') === 0) && $value === 'on') {
                $wholedisks[] = substr($var, strlen('addwholedisk_'));
            }
        }

        // sanity checks
        if (empty($wholedisks) ) {
            friendlyerror('please select one or more disks to create a new pool', $url);
        }

        // validate ZFS pool/filesystem version
        $syszfs = zfs_version();
        $options_str = '';
        if ($spa == 5000 ) {
            $options_str .= '-d -o feature@async_destroy=enabled '
            . '-o feature@empty_bpobj=enabled -o feature@lz4_compress=enabled ';
        }
        if (( $spa > 0 )AND( $spa < $syszfs[ 'spa' ] ) ) {
            $options_str .= '-o version=' . $spa . ' ';
        }
        if (( $zpl > 0 )AND( $zpl < $syszfs[ 'zpl' ] ) ) {
            $options_str .= '-O version=' . $zpl . ' ';
        }
        $options_str .= '-O atime=off ';

        // apply force by default for welcome wizard
        $force = true;
        if ($force ) {
            $options_str .= '-f ';
        }

        // format disks with GPT
        $member_disks = array();
        foreach ( $wholedisks as $disk ) {
            // gather diskinfo
            $diskinfo = disk_info($disk);
            // gather GPT label from POST vars and validate
            $label = $poolname . '-disk' . ( count($member_disks) + 1 );
            // reservespace is the space we leave unused at the end of GPT partition
            $reservespace = 1;
            // TODO: this assumes sector size = 512 bytes!
            $reserve_sect = $reservespace * ( 1024 * 2 );
            // total sector size

            // determine size of data partition ($data_size)
            // $data_size = sectorcount minus reserve sectors + 33 for gpt + 2048 offset
            $data_size = $diskinfo[ 'sectorcount' ] - ( $reserve_sect + 33 + 2048 );
            // round $data_size down to multiple of 1MiB or 2048 sectors
            $data_size = floor($data_size / 2048) * 2048;
            // minimum 64MiB (assuming 512-byte sectors)
            if (( int )$data_size < ( 64 * 1024 * 2 ) ) {
                error(
                    'The data partition needs to be at least 64MiB large; '
                    . 'try reserving less space' 
                );
            }

            // format disk
            $result = super_script('format_disk', $disk);
            if ($result[ 'rv' ] != 0 ) {
                friendlyerror(
                    'Formatting disk ' . $disk . ' failed - perhaps it is in use?',
                    $url 
                );
            }

            // destroy existing GEOM label
            super_script('geom_label_destroy', $disk);

            // sanity check on label - this check should happen AFTER formatting!
            usleep(50000);
            clearstatcache();
            if (file_exists('/dev/gpt/' . $label) ) {
                friendlyerror(
                    'another disk exists with the name ' . $label
                    . ' - please choose another pool name!', $url 
                );
            }

            // bootcode (use from webinterface files directory unless not present)
            $fd = $guru[ 'docroot' ] . '/files/bootcode/';
            if (file_exists($fd . 'pmbr') ) {
                $pmbr = $fd . 'pmbr';
            } else {
                $pmbr = '/boot/pmbr';
                page_feedback(
                    'could not use <b>pmbr</b> from webinterface - '
                    . 'using system image version', 'c_notice' 
                );
            }
            if (file_exists($fd . 'gptzfsboot') ) {
                $gptzfsboot = $fd . 'gptzfsboot';
            } else {
                $gptzfsboot = '/boot/gptzfsboot';
                page_feedback(
                    'could not use <b>gptzfsboot</b> from webinterface'
                    . ' - using system image version', 'c_notice' 
                );
            }

            // create GPT partition scheme and redirect
            $result = super_script(
                'create_gpt_partitions', $disk . ' "' . $label . '" '
                . ( int )$data_size . ' ' . $pmbr . ' ' . $gptzfsboot 
            );
            if ($result[ 'rv' ] == 0 ) {
                $member_disks[] = 'gpt/' . $label;
            } else {
                friendlyerror(
                    'could not format disk <b>' . $disk . '</b> - '
                    . 'perhaps it is already in use?', $url 
                );
            }
        }
        $member_count = ( int )count($member_disks);

        // assemble member_str
        $member_str = '';
        foreach ( $member_disks as $disklabel ) {
            $member_str .= $disklabel . ' ';
        }

        // extract redundancy
        $redundancy = zfs_extractsubmittedredundancy(
            $_POST[ 'new_zpool_redundancy' ],
            $member_count, $url 
        );

        // mountpoint (same as / + poolname)
        $mountpoint = '/' . $poolname;

        // determine search path
        /*$searchpath = '-d /dev';
        if (is_dir('/dev/label') ) {
            $searchpath .= ' -d /dev/label';
        }
        if (is_dir('/dev/gpt') ) {
            $searchpath .= ' -d /dev/gpt';
        }*/

        // handle sectorsize override
        $old_ashift_min = @trim(shell_exec('/sbin/sysctl -n vfs.zfs.min_auto_ashift'));
        $old_ashift_max = @trim(shell_exec('/sbin/sysctl -n vfs.zfs.max_auto_ashift'));
        $new_ashift = 9;
        for ( $new_ashift = 9; $new_ashift <= 17; $new_ashift++ ) {
            if ((2 ** $new_ashift) == $sectorsize ) {
                break;
            }
        }
        if ($new_ashift > 16 ) {
            error('unable to find correct ashift number for sectorsize override');
        }

        // command array
        $commands = array();

        // force specific ashift setting to be used during pool creation
        if (is_numeric($sectorsize) ) {
            $commands[] = '/sbin/sysctl vfs.zfs.min_auto_ashift=' .$new_ashift;
            $commands[] = '/sbin/sysctl vfs.zfs.max_auto_ashift=' .$new_ashift;
        }

        // create pool
        // TODO: SECURITY
        $commands[] = '/sbin/zpool create ' . trim($options_str) . ' '
        . escapeshellarg($poolname) . ' ' . $redundancy . ' ' . $member_str;

        // restore original min/max_auto_ashift setting
        if (is_numeric($sectorsize) ) {
            $commands[] = '/sbin/sysctl vfs.zfs.min_auto_ashift=' . ( int )$old_ashift_min;
            $commands[] = '/sbin/sysctl vfs.zfs.max_auto_ashift=' . ( int )$old_ashift_max;
        }

        // additional commands after creating pool
        $commands[] = '/sbin/zfs create ' . escapeshellarg($poolname . '/share');
        $commands[] = '/bin/chmod 777 ' . escapeshellarg($mountpoint . '/share');
        $commands[] = '/usr/sbin/chown -R 1000:1000 ' . escapeshellarg($mountpoint);

        // execute commands
        foreach ( $commands as $command ) {
            $result = super_execute($command);
            if ($result[ 'rv' ] != 0 ) {
                friendlyerror(
                    'failure trying to create pool (command = '
                    . htmlentities($command) . ')<br />'
                    . 'Command output: ' . nl2br(htmlentities($result[ 'output_str' ])), $url 
                );
            }
        }

        // finish
        page_feedback(
            'a new pool has been created with the name <b>' . $poolname
            . '</b>!', 'b_success' 
        );
        redirect_url('?welcome3');
    }

    // required library
    activate_library('zfs');

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
    } elseif (( @strlen($poolid) > 0 )) {
        friendlyerror('failed importing pool', $url);
    }
}

function submit_welcome_submit_4() 
{
    if (@isset($_POST[ 'goback3' ]) ) {
        redirect_url('?welcome3');
    } elseif (@isset($_POST[ 'submit4' ]) ) {
        // required library
        activate_library('activation');
        // save settings
        if (( ( int )@$_POST[ 'activation' ] != 3 )AND( ( int )@$_POST[ 'activation' ] > 0 ) ) {
            $_SESSION[ 'welcomewizard' ][ 'activation' ] = ( int )$_POST[ 'activation' ];
            $_SESSION[ 'welcomewizard' ][ 'feedback' ] = ( int )$_POST[ 'feedback' ];
            $_SESSION[ 'welcomewizard' ][ 'feedback_text' ] = $_POST[ 'feedback_text' ];
            // activate now
            $_SESSION[ 'welcomewizard' ][ 'uuid' ] = activation_submit(
                $_POST[ 'activation' ],
                ( int )$_POST[ 'feedback' ], $_POST[ 'feedback_text' ] 
            );
        } else {
            $_SESSION[ 'welcomewizard' ][ 'activation' ] = 3;
        }
        // redirect to next step
        redirect_url('?welcome5');
    }
    elseif (@isset($_POST[ 'skip_activation' ]) ) {
        // skip activation
        $_SESSION[ 'welcomewizard' ][ 'activation' ] = 3;
        $_SESSION[ 'welcomewizard' ][ 'feedback' ] =
        @$_SESSION[ 'welcomewizard' ][ 'feedback' ];
        $_SESSION[ 'welcomewizard' ][ 'feedback_text' ] =
        @$_SESSION[ 'welcomewizard' ][ 'feedback_text' ];
        // redirect to step 5 (finish)
        redirect_url('?welcome5');
    }
    else {
        error('wrong submit option on step4');
    }
}

function submit_welcome_submit_5() 
{
    if (@isset($_POST[ 'goback4' ]) ) {
        redirect_url('?welcome4');
    } elseif (@isset($_POST[ 'submit5' ]) ) {
        redirect_url('status.php');
    }
}

function finish_welcomewizard()
{
    global $guru;

    // fetch current preferences
    if (@is_array($guru[ 'preferences' ]) ) {
        $pref = $guru[ 'preferences' ];
    } else {
        $pref = $guru[ 'default_preferences' ];
    }

    // restore old preferences (when user clicked Run welcome wizard again button)
    if (@isset($_SESSION[ 'welcomewizard' ][ 'oldpreferences' ]) ) {
        foreach ( $pref as $var => $value ) {
            if (@isset($_SESSION[ 'welcomewizard' ][ 'oldpreferences' ][ $var ]) ) {
                $pref[ $var ] = $_SESSION[ 'welcomewizard' ][ 'oldpreferences' ][ $var ];
            }
        }
    }

    // access control
    $pref[ 'access_control' ] = ( int )@$_SESSION[ 'welcomewizard' ][ 'access_control' ];
    $pref[ 'access_whitelist' ] = @$_SESSION[ 'welcomewizard' ][ 'access_whitelist' ];

    // authentication
    $pref[ 'authentication' ] = @$_SESSION[ 'welcomewizard' ][ 'authentication' ];

    // activation status
    $pref[ 'uuid' ] = @$_SESSION[ 'welcomewizard' ][ 'uuid' ];

    // write preferences
    procedure_writepreferences($pref);

    // activate preferences for this pageview
    $guru[ 'preferences' ] = $pref;

    // reset SESSION welcomewizard data
    unset($_SESSION[ 'welcomewizard' ]);
}

function skip_welcomewizard()
{
    global $guru;

    // fetch current preferences
    if (@is_array($guru[ 'preferences' ]) ) {
        $pref = $guru[ 'preferences' ];
    } else {
        $pref = $guru[ 'default_preferences' ];
    }

    // restore old preferences (when user clicked Run welcome wizard again button)
    if (@!isset($_SESSION[ 'welcomewizard' ][ 'oldpreferences' ]) ) {
        page_feedback(
            'created new configuration file containing default '
            . 'preferences', 'b_success' 
        );
    } else {
        foreach ( $pref as $var => $value ) {
            if (@isset($_SESSION[ 'welcomewizard' ][ 'oldpreferences' ][ $var ]) ) {
                $pref[ $var ] = $_SESSION[ 'welcomewizard' ][ 'oldpreferences' ][ $var ];
            }
        }
    }

    // write preferences
    procedure_writepreferences($pref);

    // reset SESSION welcomewizard data
    unset($_SESSION[ 'welcomewizard' ]);
}
