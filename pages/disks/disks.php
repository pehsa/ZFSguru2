<?php

function content_disks_disks() 
{
    global $sort, $invertedsort, $labels, $dmesg, $gpart;

    // required library
    activate_library('disk');

    // call functions
    $disks = disk_detect_physical();
    $dmesg = disk_detect_dmesg();
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();
    $gnop = disk_detect_gnop();

    // variables
    $diskcount = @( int )count($disks);
    $querydisk = @$_GET[ 'query' ];

    // sorting
    $sort = @$_GET[ 'sort' ];
    $invertedsort = @isset($_GET[ 'inverted' ]);
    $sorted = $disks;
    $sortsuffix = array();
    if ($sort != '') {
        uasort($sorted, 'sort_disks');
    }
    if (!$invertedsort AND $sort ) {
        $sortsuffix[ $sort ] = '&inverted';
    }

    // store disk labels to identify a label conflict where two disks share 1 label
    $labelconflict = false;
    $labelconflicts = array();
    $nodevnode = false;
    $nolabeldev = false;
    $nolabeldev_arr = array();

    // list each disk (partition)
    $physdisks = array();
    if (@is_array($sorted) ) {
        foreach ( $sorted as $diskname => $data ) {
            // detect disk type
            $disktype = disk_detect_type($diskname);

            // classes
            $class_activerow = ( $querydisk == $diskname ) ? 'activerow' : 'normal';
            $class_hdd = ( $disktype === 'hdd' ) ? 'normal' : 'hidden';
            $class_ssd = ( $disktype === 'ssd' ) ? 'normal' : 'hidden';
            $class_flash = ( $disktype === 'flash' ) ? 'normal' : 'hidden';
            $class_memdisk = ( $disktype === 'memdisk' ) ? 'normal' : 'hidden';
            $class_usbstick = ( $disktype === 'usbstick' ) ? 'normal' : 'hidden';
            $class_network = ( $disktype === 'network' ) ? 'normal' : 'hidden';

            // acquire GNOP sector size (for sectorsize override)
            $gnop_sect = ( int )@$gnop[ 'label/' . $labels[ $diskname ] ][ 'sectorsize' ];
            if ($gnop_sect < 512 ) {
                $gnop_sect = ( int )@$gnop[ 'gpt/' . $gpart[ $diskname ][ 'label' ] ][ 'sectorsize' ];
            }
            if (@$gnop_sect > 0 ) {
                // GNOP is active
                $sectorsize = @sizebinary($gnop_sect);
                $sectorclass = 'high';
            } elseif ($data[ 'sectorsize' ] == '512' ) {
                // standard sector size
                $sectorsize = '512 B';
                $sectorclass = 'disk_sector_normal';
            }
            else {
                // native high sector size
                $sectorsize = @sizebinary($data[ 'sectorsize' ]);
                $sectorclass = 'high';
            }

            // check for labelconflicts
            if (!file_exists('/dev/' . $diskname) ) {
                $nodevnode = true;
            }
            if (@strlen($labels[ $diskname ]) > 0 ) {
                if (@isset($labelconflicts[ 'geom' ][ $labels[ $diskname ] ]) ) {
                    $labelconflict = true;
                } else {
                    $labelconflicts[ 'geom' ][ $labels[ $diskname ] ] = true;
                    if (file_exists('/dev/' . $diskname)AND!file_exists('/dev/label/' . $labels[ $diskname ]) ) {
                        $nolabeldev = true;
                        $nolabeldev_arr[] = $labels[ $diskname ];
                    }
                }
            }
            if (@strlen($gpart[ $diskname ][ 'label' ]) > 0 ) {
                if (@isset($labelconflicts[ 'gpt' ][ $gpart[ $diskname ][ 'label' ] ]) ) {
                    $labelconflict = true;
                } else {
                    $labelconflicts[ 'gpt' ][ ( string )$gpart[ $diskname ][ 'label' ] ] = true;
                    if (file_exists('/dev/' . $diskname)AND!file_exists('/dev/gpt/' . $gpart[ $diskname ][ 'label' ]) ) {
                        $nolabeldev = true;
                        $nolabeldev_arr[] = $gpart[ $diskname ][ 'label' ];
                    }
                }
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
            'CLASS_ACTIVEROW' => $class_activerow,
            'CLASS_HDD' => $class_hdd,
            'CLASS_SSD' => $class_ssd,
            'CLASS_FLASH' => $class_flash,
            'CLASS_MEMDISK' => $class_memdisk,
            'CLASS_USBSTICK' => $class_usbstick,
            'CLASS_NETWORK' => $class_network,
            'CLASS_SECTOR' => $sectorclass,
            'DISK_NAME' => htmlentities($diskname),
            'DISK_LABEL' => $labelstr,
            'DISK_SIZE_LEGACY' => @sizehuman($data[ 'mediasize' ], 1),
            'DISK_SIZE_BINARY' => @sizebinary($data[ 'mediasize' ], 1),
            'DISK_SIZE_SECTOR' => $sectorsize,
            'DISK_IDENTIFY' => @$dmesg[ $diskname ]
            );
        }
    }

    // warn when a label conflict was detected
    $class_labelconflict = ( $labelconflict ) ? 'normal' : 'hidden';
    $class_nodevnode = ( $nodevnode ) ? 'normal' : 'hidden';
    $class_nolabeldev = ( $nolabeldev ) ? 'normal' : 'hidden';
    $nolabeldev_labels = @implode(', ', $nolabeldev_arr);

    // process queried disk (for format box)
    if ($querydisk ) {
        $formatclass = 'normal';
        if (@strlen($gpart[ $querydisk ][ 'label' ]) > 0 ) {
            $gptchecked = 'checked="checked"';
            $gptlabel = htmlentities($gpart[ $querydisk ][ 'label' ]);
            $geomchecked = '';
            $geomlabel = '';
        } elseif (@strlen($labels[ $querydisk ]) > 0 ) {
            $gptchecked = '';
            $gptlabel = '';
            $geomchecked = 'checked="checked"';
            $geomlabel = htmlentities($labels[ $querydisk ]);
        }
    } else {
        $formatclass = 'hidden';
    }

    // massprocess count
    $mp_count = '';
    for ( $i = 0; $i <= 48; $i++ ) {
        if ($i == 1 ) {
            $mp_count .= "   <option value=\"$i\" selected=\"selected\">$i</option>\n";
        } else {
            $mp_count .= "   <option value=\"$i\">$i</option>\n";
        }
    }

    // export new tags
    return @array(
    'PAGE_TITLE' => 'Formatting',
    'PAGE_ACTIVETAB' => 'Formatting',
    'TABLE_PHYSDISKS' => $physdisks,
    'CLASS_LABELCONFLICT' => $class_labelconflict,
    'CLASS_NODEVNODE' => $class_nodevnode,
    'CLASS_NOLABELDEV' => $class_nolabeldev,
    'NOLABELDEV_LABELS' => $nolabeldev_labels,
    'SORT_DISK' => $sortsuffix[ 'disk' ],
    'SORT_LABEL' => $sortsuffix[ 'label' ],
    'SORT_SIZE' => $sortsuffix[ 'size' ],
    'SORT_SECTOR' => $sortsuffix[ 'sector' ],
    'SORT_IDENT' => $sortsuffix[ 'ident' ],
    'DISKS_DISKCOUNT' => $diskcount,
    'MASSPROCESS_COUNT' => $mp_count,
    'QUERY_DISKNAME' => $querydisk,
    'FORMAT_CLASS' => $formatclass,
    'FORMAT_GPTCHECKED' => @$gptchecked,
    'FORMAT_GEOMCHECKED' => @$geomchecked,
    'FORMAT_GPTLABEL' => @$gptlabel,
    'FORMAT_GEOMLABEL' => @$geomlabel
    );
}

function sort_disks( $a, $b ) 
{
    global $sort, $invertedsort, $labels, $dmesg, $gpart;
    $attr = false;

    // set easy to search attributes
    if ($sort === 'disk' ) {
        $attr = 'disk_name';
    } elseif ($sort === 'size' ) {
        $attr = 'mediasize';
    } elseif ($sort === 'sector' ) {
        $attr = 'sectorsize';
    }

    if ($attr ) {
        $aa = @$a[ $attr ];
        $bb = @$b[ $attr ];
    } elseif ($sort === 'label' ) {
        $aa = 'label/' . @$labels[ $a[ 'disk_name' ] ];
        if ($aa === 'label/' ) {
            $aa = 'gpt/' . @$gpart[ $a[ 'disk_name' ] ][ 'label' ];
        }
        $bb = 'label/' . @$labels[ $b[ 'disk_name' ] ];
        if ($bb === 'label/' ) {
            $bb = 'gpt/' . @$gpart[ $b[ 'disk_name' ] ][ 'label' ];
        }
    }
    elseif ($sort === 'ident' ) {
        $aa = @$dmesg[ $a[ 'disk_name' ] ];
        $bb = @$dmesg[ $b[ 'disk_name' ] ];
    }

    // compare aa to bb
    if ($aa == $bb) {
        return 0;
    }

    if ($invertedsort) {
        return ( $aa < $bb ) ? 1 : -1;
    } else {
        return ( $aa < $bb ) ? -1 : 1;
    }
}

function submit_disks_formatdisk() 
{
    global $guru;

    // required libraries
    activate_library('disk');
    activate_library('super');
    activate_library('zfs');

    // variables
    $url = 'disks.php';

    // sanity on disk device
    sanitize(@$_POST[ 'formatdisk_diskname' ], null, $disk);
    $disk_dev = '/dev/' . $disk;
    if (!file_exists($disk_dev) ) {
        friendlyerror('Invalid disk: "' . $disk . '"; does not exist!', $url);
    }

    // redirection url
    $url2 = 'disks.php?query=' . $disk;

    // sanity on label name
    $san_geom = sanitize(@$_POST[ 'geom_label' ], null, $geom_label, 16);
    $san_gpt = sanitize(@$_POST[ 'gpt_label' ], null, $gpt_label, 16);
    if (@$_POST[ 'format_type' ] === 'geom' ) {
        $disklabel = 'label/' . $geom_label;
        if (!$san_geom ) {
            friendlyerror(
                'please enter a valid GEOM label name for your disk '
                . '(alphanumerical + underscore + dash characters allowed', $url2 
            );
        }
    } elseif (@$_POST[ 'format_type' ] === 'gpt' ) {
        $disklabel = 'gpt/' . $gpt_label;
        if (!$san_gpt ) {
            friendlyerror(
                'please enter a valid GPT label name for your disk '
                . '(alphanumerical + underscore + dash characters allowed', $url2 
            );
        }
    }
    else {
        friendlyerror('please select a partition schedule, GPT or GEOM.', $url2);
    }

    // check whether disk is part of a pool
    $labels = disk_detect_label();
    $gpart = disk_detect_gpart();
    if (@isset($labels[ $disk ]) ) {
        $labelname = 'label/' . $labels[ $disk ];
    } elseif (@isset($gpart[ $disk ][ 'label' ]) ) {
        $labelname = 'gpt/' . $gpart[ $disk ][ 'label' ];
    } else {
        $labelname = false;
    }
    if ($labelname != false ) {
        $poolname = zfs_pool_ismember($labelname);
    } else {
        $poolname = zfs_pool_ismember($disk);
    }
    if ($poolname != false ) {
        friendlyerror(
            'disk <b>' . $disk . '</b> is a member of pool <b>' . $poolname
            . '</b> and cannot be formatted! Destroy the pool first.', $url2 
        );
    }

    // random write
    if (@$_POST[ 'random_write' ] === 'on' ) {
        $result = super_script('random_write', $disk);
        if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
            error(
                'Random writing disk ' . $disk . ' failed, got return value '
                . ( int )$result[ 'rv' ] . '. Command output:<br /><br />'
                . nl2br($result[ 'output_str' ]) 
            );
        }
    }
    // zero-write
    if (@$_POST[ 'zero_write' ] === 'on' ) {
        $result = super_script('zero_write', $disk);
        if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
            error(
                'Zero writing disk ' . $disk . ' failed, got return value '
                . ( int )$result[ 'rv' ] . '. Command output:<br /><br />'
                . nl2br($result[ 'output_str' ]) 
            );
        }
    }
    // secure erase
    if (@$_POST[ 'secure_erase' ] === 'on' ) {
        $result = super_script('secure_erase', $disk);
        if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
            error(
                'Secure Erasing disk ' . $disk . ' failed, got return value '
                . ( int )$result[ 'rv' ] . '. Command output:<br /><br />' . $result[ 'output_str' ] 
            );
        }
    }

    // format disk; cleaning any existing partitions
    $result = super_script('format_disk', $disk);
    if ($result[ 'rv' ] != 0 ) {
        error(
            'Formatting disk ' . $disk . ' failed, got return value '
            . ( int )$result[ 'rv' ] . '. Command output:<br /><br />' . $result[ 'output_str' ] 
        );
    }

    // destroy existing GEOM label
    super_script('geom_label_destroy', $disk);

    // abort if the device exists -- this check has to happen AFTER initial format
    usleep(50000);
    if (file_exists('/dev/' . $disklabel) ) {
        friendlyerror(
            'you already have a disk with the label <b>' . $disklabel
            . '</b>, please choose another name!', $url2 
        );
    }

    // GEOM formatting
    if (@$_POST[ 'format_type' ] === 'geom' ) {
        // create new GEOM label
        super_script('geom_label_create', $disk . ' ' . $geom_label);
    }

    // GPT formatting
    if (@$_POST[ 'format_type' ] === 'gpt' ) {
        // gather diskinfo
        $diskinfo = disk_info($disk);

        // reservespace is the space we leave unused at the end of GPT partition
        $reservespace = @$_POST[ 'gpt_reservespace' ];
        $reservespace = ( ( !is_numeric($reservespace) )OR( int )$reservespace < 0 ) ?
        1 : ( int )$reservespace;
        // TODO: this assumes sector size = 512 bytes!
        $reserve_sect = $reservespace * ( 1024 * 2 );
        // total sector size

        // determine size of data partition ($data_size)
        // $data_size = sectorcount minus reserve sectors + 33 for gpt + 2048 offset
        $data_size = $diskinfo[ 'sectorcount' ] - ( $reserve_sect + 33 + 2048 );
        // round $data_size down to multiple of 1MiB or 2048 sectors
        $data_size = floor($data_size / 2048) * 2048;
        // minimum 1MiB (assuming 512-byte sectors)
        if (( int )$data_size < ( 1 * 1024 * 2 ) ) {
            error(
                'The data partition needs to be at least 1MiB large; '
                . 'try reserving less space' 
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

        // create GPT partition scheme
        super_script(
            'create_gpt_partitions', $disk . ' "' . $gpt_label . '" '
            . ( int )$data_size . ' ' . $pmbr . ' ' . $gptzfsboot 
        );
    }

    // microsleep
    usleep(50);

    // redirect
    $label = ( $_POST[ 'format_type' ] === 'geom' ) ? $geom_label : $gpt_label;
    friendlynotice(
        'disk <b>' . htmlentities($disk) . '</b> has been formatted with '
        . '<b>' . @strtoupper($_POST[ 'format_type' ]) . '</b>, and will be identified by '
        . 'the label <b>' . @htmlentities($label) . '</b>', $url 
    );
    die();
}

function submit_disks_massprocess() 
{
    global $guru;

    // variables
    $url = 'disks.php';
    $action = @$_POST[ 'massprocess_action' ];

    // construct array of disks selected
    $disks = array();
    foreach ( $_POST as $name => $value ) {
        if (strpos($name, 'selectdisk_') === 0) {
            $disks[] = substr($name, strlen('selectdisk_'));
        }
    }

    // commands array
    $commands = array();

    // determine action to perform
    if ($action === 'formatgpt' ) {
        // required library
        activate_library('disk');

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

        // start counting at
        $count = ( is_numeric(@$_POST[ 'massprocess_count' ]) ) ?
        ( int )$_POST[ 'massprocess_count' ] : 1;

        // label base (prefix)
        $s = sanitize(@$_POST[ 'massprocess_label' ], false, $labelbase);
        if (!$s OR $labelbase == '') {
            friendlyerror(
                'please enter a label prefix consisting of alphanumerical, '
                . 'dash (-) or underscore (_) characters', $url 
            );
        }

        // format each disk
        foreach ( $disks as $disk ) {
            // partition labelname (incremented integer at the end)
            $labelname = $labelbase . $count++;

            // acquire disk information (exact size, sector size)
            $diskinfo = disk_info($disk);

            // reservespace is the space we leave unused at the end of data partition
            $reservespace = 1024 * 1024;
            $reserve_sect = $reservespace / $diskinfo[ 'sectorsize' ];
            // total sector size

            // determine size of data partition ($data_size)
            // $data_size = sectorcount minus reserve sectors + 33 for gpt + 2048 offset
            $data_size = $diskinfo[ 'sectorcount' ] - ( $reserve_sect + 33 + 2048 );
            // round $data_size down to multiple of 1MiB or 2048 sectors
            // TODO: assumes 512-byte sectors
            $data_size = floor($data_size / 2048) * 2048;
            // minimum 1MiB (assuming 512-byte sectors)
            if (( int )$data_size < ( 1 * 1024 * 2 ) ) {
                error(
                    'The data partition needs to be at least 1MiB large; '
                    . 'try reserving less space' 
                );
            }

            // add commands to array
            $commands[] = $guru[ 'docroot' ] . '/scripts/format_disk.sh ' . $disk;
            $commands[] = $guru[ 'docroot' ] . '/scripts/create_gpt_partitions.sh '
            . $disk . ' "' . $labelname . '" ' . $data_size . ' ' . $pmbr . ' ' . $gptzfsboot;
        }
    } elseif ($action === 'formatmbr' ) {
        // required library
        activate_library('disk');

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
            $zfsboot = $fd . 'zfsboot';
        } else {
            $zfsboot = '/boot/zfsboot';
            page_feedback(
                'could not use <b>zfsboot</b> from webinterface'
                . ' - using system image version', 'c_notice' 
            );
        }

        // create MBR partition scheme, boot partition and data partition
        // format each disk
        foreach ( $disks as $disk ) {
            // partition labelname (incremented integer at the end)
            $labelname = '';

            // acquire disk information (exact size, sector size)
            $diskinfo = disk_info($disk);

            // reservespace is the space we leave unused at the end of data partition
            $reservespace = 1024 * 1024;
            $reserve_sect = $reservespace / $diskinfo[ 'sectorsize' ];
            // total sector size

            // determine size of data partition ($data_size)
            // $data_size = sectorcount minus reserve sectors + 33 for gpt + 2048 offset
            $data_size = $diskinfo[ 'sectorcount' ] - ( $reserve_sect + 33 + 2048 );
            // round $data_size down to multiple of 1MiB or 2048 sectors
            // TODO: assumes 512-byte sectors
            $data_size = floor($data_size / 2048) * 2048;
            // minimum 1MiB (assuming 512-byte sectors)
            if (( int )$data_size < ( 1 * 1024 * 2 ) ) {
                error(
                    'The data partition needs to be at least 1MiB large; '
                    . 'try reserving less space' 
                );
            }

            // add commands to array
            $commands[] = $guru[ 'docroot' ] . '/scripts/format_disk.sh ' . $disk;
            $commands[] = $guru[ 'docroot' ] . '/scripts/create_mbr_partitions.sh '
            . $disk . ' "' . $labelname . '" ' . $data_size . ' ' . $pmbr . ' ' . $zfsboot;
        }
    }
    elseif ($action === 'delete' ) {
        foreach ( $disks as $disk ) {
            $commands[] = $guru[ 'docroot' ] . '/scripts/format_disk.sh ' . $disk;
        }
    }
    elseif ($action === 'zerowrite' ) {
        foreach ( $disks as $disk ) {
            $commands[] = $guru[ 'docroot' ] . '/scripts/zero_write.sh ' . $disk;
        }
    }
    elseif ($action === 'randomwrite' ) {
        foreach ( $disks as $disk ) {
            $commands[] = $guru[ 'docroot' ] . '/scripts/random_write.sh ' . $disk;
        }
    }
    elseif ($action === 'trimerase' ) {
        foreach ( $disks as $disk ) {
            $commands[] = $guru[ 'docroot' ] . '/scripts/secure_erase.sh ' . $disk;
        }
    }
    else {
        page_feedback('unknown action', 'a_warning');
    }

    // defer to dangerouscommand function
    if (count($commands) > 0 ) {
        dangerouscommand($commands, $url);
    } else {
        page_feedback('nothing done', 'c_notice');
    }
    redirect_url($url);
}
