<?php

function content_disks_query() 
{
    global $guru;

    // required library
    activate_library('disk');

    // queried disk
    $querydisk = @$_GET[ 'query' ];
    if (!$querydisk ) {
        redirect_url('disks.php');
    }

    // call functions
    $disks = disk_detect_physical($querydisk);
    $diskcount = @( int )count($disks);
    $dmesg = disk_detect_dmesg($disks);
    if ($querydisk ) {
        $gpart = disk_detect_gpart($querydisk);
    } else {
        $gpart = disk_detect_gpart();
    }
    $labels = disk_detect_label();
    $gnop = disk_detect_gnop();

    // gpt types
    $gpttypes = array(
    'freebsd' => '516E7CB4-6ECF-11D6-8FF8-00022D09712B',
    'freebsd-boot' => '83BD6B9D-7F41-11DC-BE0B-001560B84F0F',
    'freebsd-swap' => '516E7CB5-6ECF-11D6-8FF8-00022D09712B',
    'freebsd-ufs' => '516E7CB6-6ECF-11D6-8FF8-00022D09712B',
    'freebsd-vinum' => '516E7CB8-6ECF-11D6-8FF8-00022D09712B',
    'freebsd-zfs' => '516E7CBA-6ECF-11D6-8FF8-00022D09712B',
    'linux' => '0FC63DAF-8483-4772-8E79-3D69D8477DE4',
    'linux-swap' => '0657FD6D-A4AB-43C4-84E5-0933C84B4F4F',
    'solaris' => '6A898CC3-1DD2-11B2-99A6-080020736631',
    'solaris-boot' => '6A82CB45-1DD2-11B2-99A6-080020736631',
    'solaris-home' => '6A90BA39-1DD2-11B2-99A6-080020736631',
    'solaris-root' => '6A85CF4D-1DD2-11B2-99A6-080020736631',
    'solaris-swap' => '6A87C46F-1DD2-11B2-99A6-080020736631',
    'solaris-var' => '6A8EF2E9-1DD2-11B2-99A6-080020736631'
    );
    // mbr types
    // TODO: this list may be incorrect, need feedback
    $mbrtypes = array(
    'FAT12' => '1',
    'FAT16' => '14',
    'FAT32' => '12',
    'NTFS' => '7',
    'Linux native' => '67',
    'Linux swap' => '66',
    'FreeBSD' => '165',
    'NetBSD' => '169',
    'OpenBSD' => '166',
    'Solaris' => '191'
    );

    // list only the queried disk
    if (@is_array($disks) ) {
        foreach ( $disks as $diskname => $data ) {
            // detect disk type
            $disktype = disk_detect_type($diskname);

            // classes
            $class_activerow = ( $querydisk == $diskname ) ? 'activerow' : 'normal';
            $class_hdd = ( $disktype == 'hdd' ) ? 'normal' : 'hidden';
            $class_ssd = ( $disktype == 'ssd' ) ? 'normal' : 'hidden';
            $class_flash = ( $disktype == 'flash' ) ? 'normal' : 'hidden';
            $class_memdisk = ( $disktype == 'memdisk' ) ? 'normal' : 'hidden';
            $class_usbstick = ( $disktype == 'usbstick' ) ? 'normal' : 'hidden';
            $class_network = ( $disktype == 'network' ) ? 'normal' : 'hidden';

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

            // process GPT/GEOM label string
            $labelstr = '';
            if (@strlen($labels[ $diskname ]) > 0 ) {
                $labelstr .= 'GEOM: ' . @htmlentities($labels[ $diskname ]);
            }
            if (@strlen($gpart[ $diskname ][ 'label' ]) > 0 ) {
                if (strlen($labelstr) > 0 ) {
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

    // partition map
    $pmap = disk_partitionmap($querydisk);
    $seg = ( @isset($_GET[ 'seg' ]) ) ? ( int )$_GET[ 'seg' ] : false;

    // select first section when disk is unpartitioned
    if (@$pmap[ 0 ][ 'type' ] == 'unpartitioned' ) {
        $seg = 0;
    }

    // other variables
    $segindex = @$pmap[ $seg ][ 'index' ];
    $totalseg = count($pmap);

    // partition table
    $table_partition_map = array();
    if (is_array($pmap) ) {
        foreach ( $pmap as $id => $segment ) {
            $stype = @$segment[ 'type' ];
            foreach ( $gpttypes as $gptname => $gptid ) {
                if (strtolower($gptid) == substr($stype, 1) ) {
                    $stype = $gptname;
                }
            }
            $table_partition_map[] = @array(
            'PMAP_ID' => ( int )$id,
            'PMAP_SEL' => ( $id === $seg ) ? 'pmap_selected' : '',
            'PMAP_LABEL' => $segment[ 'label' ],
            'PMAP_TYPE' => $stype,
            'PMAP_START' => $segment[ 'start' ],
            'PMAP_SIZE' => sizebinary($segment[ 'size' ], 1),
            'PMAP_PCT' => $segment[ 'pct' ]
            );
        }
    }

    // partition scheme
    $scheme = @$gpart[ $querydisk ][ 'general' ][ 'scheme' ];

    // segment options
    $class_segoptions = ( is_int($seg) ) ? 'normal' : 'hidden';
    $class_segnooptions = ( !is_int($seg) ) ? 'normal' : 'hidden';
    $segopt[ 'unpartitioned' ] = 'hidden';
    $segopt[ 'geom' ] = 'hidden';
    $segopt[ 'gptfree' ] = 'hidden';
    $segopt[ 'gptboot' ] = 'hidden';
    $segopt[ 'gptdata' ] = 'hidden';
    $segopt[ 'mbrfree' ] = 'hidden';
    $segopt[ 'mbrdata' ] = 'hidden';
    $segopt[ 'destroyscheme' ] = 'hidden';
    $segopt[ 'inuse' ] = 'hidden';
    $segopt[ 'mounted' ] = 'hidden';
    $segopt[ 'unknown' ] = 'hidden';

    if (is_int($seg) ) {
        $segopt[ 'unpartitioned' ] = ( @$pmap[ $seg ][ 'type' ] == 'unpartitioned' ) ?
        'normal' : 'hidden';
        $segopt[ 'geom' ] = ( @$pmap[ $seg ][ 'type' ] == 'geom' ) ?
        'normal' : 'hidden';
        $segopt[ 'gptfree' ] = ( $scheme == 'GPT'
        AND @$pmap[ $seg ][ 'type' ] == 'free' ) ?
        'normal' : 'hidden';
        $segopt[ 'gptboot' ] = ( $scheme == 'GPT'
        AND @$pmap[ $seg ][ 'type' ] == 'freebsd-boot' ) ? 'normal' : 'hidden';
        $segopt[ 'gptdata' ] = ( $scheme == 'GPT'
        AND @$pmap[ $seg ][ 'type' ] != 'free'
        AND @$pmap[ $seg ][ 'type' ] != 'freebsd-boot' ) ? 'normal' : 'hidden';
        $segopt[ 'mbrfree' ] = ( $scheme == 'MBR'
        AND @$pmap[ $seg ][ 'type' ] == 'free' ) ?
        'normal' : 'hidden';
        $segopt[ 'mbrdata' ] = ( $scheme == 'MBR'
        AND @$pmap[ $seg ][ 'type' ] != 'free' ) ?
        'normal' : 'hidden';
        $segopt[ 'destroyscheme' ] = ( count($pmap) == 1 ) ? 'normal' : 'hidden';
        // default unknown segment option
        $segopt[ 'unknown' ] = 'normal';
        foreach ( $segopt as $segmentid => $displaystatus ) {
            if ($displaystatus == 'normal'
                AND $segmentid != 'unknown' 
            ) {
                $segopt[ 'unknown' ] = 'hidden';
            }
        }
        // check whether partition segment is in use by ZFS
        if (strlen(@$pmap[ $seg ][ 'dev' ]) > 0 ) {
            activate_library('system');
            activate_library('zfs');
            // check in use by ZFS
            $inuse = false;
            if (( $poolname = zfs_pool_ismember(substr($pmap[ $seg ][ 'dev' ], strlen('/dev/'))) ) ) {
                $inuse = true;
            } elseif (strlen(@$pmap[ $seg ][ 'label' ]) > 0 ) {
                if (( $poolname = zfs_pool_ismember('gpt/' . @$pmap[ $seg ][ 'label' ]) ) ) {
                    $inuse = true;
                }
            }
            if ($inuse ) {
                //    foreach ($segopt as $name => $value)
                //     $segopt[$name] = 'hidden';
                $seg_submit = 'disabled="disabled"';
                $segopt[ 'inuse' ] = 'normal';
                $seg_inusebyzfs = $poolname;
            }
            // check in use by mounted (mount output)
            $mp = system_mountpoints();
            $mounted = false;
            if (system_ismounted($pmap[ $seg ][ 'dev' ], $mp) ) {
                $mounted = true;
            } elseif (strlen(@$pmap[ $seg ][ 'label' ]) > 0 ) {
                if (system_ismounted('gpt/' . $pmap[ $seg ][ 'label' ], $mp) ) {
                    $mounted = true;
                }
            }
            if ($mounted ) {
                foreach ( $segopt as $name => $value ) {
                    $segopt[ $name ] = 'hidden';
                }
                $segopt[ 'mounted' ] = 'normal';
                // table mounteddevices
                $table_mounteddevices = array();
                foreach ( $mp as $data ) {
                    $table_mounteddevices[] = array(
                    'MD_CLASS' => ( $data[ 'device' ] == $pmap[ $seg ][ 'dev' ]OR $data[ 'device' ] == 'gpt/' . @$pmap[ $seg ][ 'label' ] ) ?
                    'activerow' : 'normal',
                    'MD_DEVICE' => htmlentities($data[ 'device' ]),
                    'MD_MOUNTPOINT' => htmlentities($data[ 'mountpoint' ]),
                    'MD_OPTIONS' => htmlentities($data[ 'options' ])
                    );
                }
            }
        }
    }

    // general segment options
    if ($class_segoptions == 'normal' ) {
        // register javascript head element for slider widget
        page_register_headelement('<script src="files/slider.js"></script>');
        // segment data
        $seg_gptlabel = @htmlentities($pmap[ $seg ][ 'label' ]);
        $seg_size = ( int )$pmap[ $seg ][ 'size' ];
        $seg_size_bin = sizebinary(( int )$pmap[ $seg ][ 'size' ], 1);
        $seg_size_sect = ( int )$pmap[ $seg ][ 'size_sect' ];
        // determine maximum segment size for resize feature
        if (!@isset($pmap[ $seg + 1 ]) ) {
            $seg_size_sect_max = ( int )$pmap[ $seg ][ 'size_sect' ];
        } elseif (@$pmap[ $seg + 1 ][ 'type' ] != 'free' ) {
            $seg_size_sect_max = $pmap[ $seg + 1 ][ 'start' ] - $pmap[ $seg ][ 'start' ];
        } elseif (@isset($pmap[ $seg + 2 ]) ) {
            $seg_size_sect_max = $pmap[ $seg + 2 ][ 'start' ] - $pmap[ $seg ][ 'start' ];
        } else {
            $seg_size_sect_max = ( $pmap[ $seg + 1 ][ 'end' ] - $pmap[ $seg ][ 'start' ] ) + 1;
        }
        // determine sector size by gpart consumer data
        $seg_size_sectorsize = @$gpart[ $querydisk ][ 'consumers' ][ 'Sectorsize' ];
        // partition type tables
        $table_seggpt_types = array();
        foreach ( $gpttypes as $gptname => $gptid ) {
            $table_seggpt_types[ $gptname ] = array(
            'GPTTYPE_NAME' => htmlentities($gptname),
            'GPTTYPE_VAL' => '!' . htmlentities(strtolower($gptid)),
            'GPTTYPE_SEL' => ( strtolower($gptid) == @$pmap[ $seg ][ 'rawtype' ] ) ?
            'selected="selected"' : ''
            );
        }
        // partition type tables
        $table_segmbr_types = array();
        foreach ( $mbrtypes as $mbrname => $mbrid ) {
            $table_segmbr_types[ $mbrname ] = array(
            'MBRTYPE_NAME' => htmlentities($mbrname),
            'MBRTYPE_VAL' => '!' . ( int )$mbrid,
            'MBRTYPE_SEL' => ( $mbrid == @$pmap[ $seg ][ 'rawtype' ] ) ?
            'selected="selected"' : ''
            );
        }
    }

    // freebsd-boot partition
    if ($segopt[ 'gptboot' ] == 'normal' ) {
        // pmbr byte offset
        // TODO: detect sectorsize and use count parameter correctly on dd commands
        $pmbr_bytecap = 440;
        // default class
        $class_gptboot_ok = 'hidden';
        $class_gptboot_old = 'hidden';
        $class_gptboot_error = 'hidden';
        $class_gptboot_sysboot = 'hidden';
        // check size (max: 1MiB)
        $seg_size = ( int )$pmap[ $seg ][ 'size' ];
        if ($seg_size < 512 OR $seg_size > 1024 * 1024 ) {
            $class_gptboot_error = 'normal';
        } else {
            // requires super privileges
            activate_library('super');
            // read the disk MBR signature
            $cmd = '/bin/dd if=/dev/' . $querydisk . ' count=1';
            $cmd_mbr = super_execute($cmd, false, false);
            // read the boot partition signature
            $devicenode = @$pmap[ $seg ][ 'dev' ];
            $cmd = '/bin/dd if=/dev/' . $devicenode . ' count=80 | /sbin/md5';
            $cmd_bootcode = super_execute($cmd);
            // grab the system installed signatures as well (pmbr + gptzfsboot)
            $system_pmbr = @file_get_contents('/boot/pmbr');
            $system_gptzfsboot = @file_get_contents('/boot/gptzfsboot');
            $sig[ 'system_mbr' ] = @md5(substr($system_pmbr, 0, $pmbr_bytecap));
            $sig[ 'system_bootcode' ] = @md5(substr($system_gptzfsboot, 0, 80 * 512));
            //   $sig['system_bootcode'] = @md5(file_get_contents(
            //    '/boot/gptzfsboot', false, false, false, 80 * 512));
            // check for errors
            if ($cmd_mbr[ 'rv' ] != 0 OR $cmd_bootcode[ 'rv' ] != 0 ) {
                page_feedback('could not read bootcode signature from device', 'a_error');
                $class_gptboot_error = 'normal';
            } else {
                // signature MD5 strings
                $sig[ 'mbr' ] = md5(substr($cmd_mbr[ 'output_str' ], 0, $pmbr_bytecap));
                $sig[ 'bootcode' ] = $cmd_bootcode[ 'output_str' ];
                // fetch expected MBR and bootcode MD5 signatures from ZFSguru webinterface
                $sig[ 'expected_mbr' ] = @md5(
                    substr(
                        file_get_contents(
                            $guru[ 'docroot' ] . '/files/bootcode/pmbr' 
                        ), 0, $pmbr_bytecap 
                    ) 
                );
                $sig[ 'expected_bootcode' ] = @md5(
                    file_get_contents(
                        $guru[ 'docroot' ] . '/files/bootcode/gptzfsboot', false, false, false, 80 * 512 
                    ) 
                );
                // compare disk signatures to expected signatures
                if ($sig[ 'mbr' ] == $sig[ 'expected_mbr' ]AND $sig[ 'bootcode' ] == $sig[ 'expected_bootcode' ] ) {
                    $class_gptboot_ok = 'normal';
                } else {
                    $class_gptboot_old = 'normal';
                }
                // compare expected signatures to system signatures
                if ($sig[ 'expected_mbr' ] != $sig[ 'system_mbr' ]OR $sig[ 'expected_bootcode' ] != $sig[ 'system_bootcode' ] ) {
                    if (( strlen($sig[ 'system_mbr' ]) > 0 )AND( strlen($sig[ 'system_bootcode' ]) > 0 ) ) {
                        $class_gptboot_sysboot = 'normal';
                    }
                }
            }
        }
    }

    // new segment sectors
    // $newseg_sectors = (int)@(($pmap[$seg]['end']+1) - $pmap[$seg]['start']);
    $newseg_sectors = ( int )@$pmap[ $seg ][ 'size_sect' ];
    $newseg_mib = ( int )@floor($pmap[ $seg ][ 'size' ] / ( 1024 * 1024 ));
    $newseg_gib = ( int )@floor($pmap[ $seg ][ 'size' ] / ( 1024 * 1024 * 1024 ));
    $newseg_enable_m = ( $newseg_mib > 0 ) ? '' : 'disabled="disabled"';
    $newseg_enable_g = ( $newseg_gib > 0 ) ? '' : 'disabled="disabled"';

    // geom label name
    if ($segopt[ 'geom' ] == 'normal' ) {
        $labels = disk_detect_label();
        $seg_geomlabel = htmlentities($labels[ $querydisk ]);
    }

    // for the gpt free segment we will set default partition type
    if ($segopt[ 'gptfree' ] == 'normal' ) {
        $table_seggpt_types[ 'freebsd-zfs' ][ 'GPTTYPE_SEL' ] = 'selected="selected"';
    }

    // corrupt partition scheme detection
    if (strpos(`/sbin/gpart show $querydisk`, 'CORRUPT') === false ) {
        $segopt[ 'corrupt' ] = 'hidden';
    } else {
        // hide all segments dirst
        foreach ( $segopt as $name => $value ) {
            $segopt[ $name ] = 'hidden';
        }
        // make new segment visible
        $segopt[ 'corrupt' ] = 'normal';
        // make segment option visible
        $class_segoptions = 'normal';
        $class_segnooptions = 'hidden';
    }

    // export new tags
    return @array(
    'PAGE_ACTIVETAB' => 'Formatting',
    'PAGE_TITLE' => 'Formatting',
    'TABLE_DISKS_PHYSDISKS' => $physdisks,
    'TABLE_PARTITION_MAP' => $table_partition_map,
    'TABLE_SEGGPT_TYPES' => $table_seggpt_types,
    'TABLE_SEGMBR_TYPES' => $table_segmbr_types,
    'TABLE_MOUNTEDDEVICES' => $table_mounteddevices,
    'CLASS_SEGOPTIONS' => $class_segoptions,
    'CLASS_SEGNOOPTIONS' => $class_segnooptions,
    'CLASS_SEGUNPARTITIONED' => $segopt[ 'unpartitioned' ],
    'CLASS_SEGGEOM' => $segopt[ 'geom' ],
    'CLASS_SEGGPTFREE' => $segopt[ 'gptfree' ],
    'CLASS_SEGGPTBOOT' => $segopt[ 'gptboot' ],
    'CLASS_SEGGPTDATA' => $segopt[ 'gptdata' ],
    'CLASS_SEGMBRFREE' => $segopt[ 'mbrfree' ],
    'CLASS_SEGMBRDATA' => $segopt[ 'mbrdata' ],
    'CLASS_SEGDESTROYSCHEME' => $segopt[ 'destroyscheme' ],
    'CLASS_SEGINUSE' => $segopt[ 'inuse' ],
    'CLASS_SEGMOUNTED' => $segopt[ 'mounted' ],
    'CLASS_SEGUNKNOWN' => $segopt[ 'unknown' ],
    'CLASS_SEGCORRUPT' => $segopt[ 'corrupt' ],
    'CLASS_GPTBOOT_OK' => $class_gptboot_ok,
    'CLASS_GPTBOOT_OLD' => $class_gptboot_old,
    'CLASS_GPTBOOT_ERROR' => $class_gptboot_error,
    'CLASS_GPTBOOT_SYSBOOT' => $class_gptboot_sysboot,
    'DISKS_DISKCOUNT' => $diskcount,
    'QUERY_DISKNAME' => $querydisk,
    'QUERY_SEGMENT' => $seg,
    'QUERY_INDEX' => $segindex,
    'QUERY_TOTALSEG' => $totalseg,
    'SEG_GEOMLABEL' => $seg_geomlabel,
    'SEG_GPTLABEL' => $seg_gptlabel,
    'SEG_SIZE' => $seg_size,
    'SEG_SIZE_SECT' => $seg_size_sect,
    'SEG_SIZE_SECT_MAX' => $seg_size_sect_max,
    'SEG_SIZE_SECTORSIZE' => $seg_size_sectorsize,
    'SEG_SIZE_BIN' => $seg_size_bin,
    'SEG_INUSEBYZFS' => $seg_inusebyzfs,
    'SEG_SUBMIT' => $seg_submit,
    'NEWSEG_SECTORS' => $newseg_sectors,
    'NEWSEG_MIB' => $newseg_mib,
    'NEWSEG_GIB' => $newseg_gib,
    'NEWSEG_ENABLE_M' => $newseg_enable_m,
    'NEWSEG_ENABLE_G' => $newseg_enable_g,
    'SIG_MBR' => $sig[ 'mbr' ],
    'SIG_BOOTCODE' => $sig[ 'bootcode' ],
    'SIG_EXP_MBR' => $sig[ 'expected_mbr' ],
    'SIG_EXP_BOOTCODE' => $sig[ 'expected_bootcode' ],
    'SIG_SYS_MBR' => $sig[ 'system_mbr' ],
    'SIG_SYS_BOOTCODE' => $sig[ 'system_bootcode' ],
    'FORMAT_CLASS' => $formatclass,
    'FORMAT_GPTCHECKED' => @$gptchecked,
    'FORMAT_GEOMCHECKED' => @$geomchecked,
    'FORMAT_GPTLABEL' => @$gptlabel,
    'FORMAT_GEOMLABEL' => @$geomlabel
    );
}

function submit_disks_segmentprocess() 
{
    global $guru;

    // required library
    activate_library('disk');
    activate_library('super');

    // variables
    $diskname = @$_POST[ 'seg_diskname' ];
    $gpart = disk_detect_gpart($diskname);
    $pmap = disk_partitionmap($diskname);
    $seg = ( int )@$_POST[ 'seg_segment' ];

    // partition scheme (GPT/MBR)
    $scheme = @$gpart[ $diskname ][ 'general' ][ 'scheme' ];

    // redirect URL
    $url = 'disks.php?query=' . $diskname;
    $url2 = ( @isset($_POST[ 'seg_segment' ]) ) ? $url . '&seg=' . $seg: $url;

    // calculate number of used segments
    $segusedcount = 0;
    foreach ( $pmap as $pdata ) {
        if (strlen(@$pdata[ 'type' ]) > 0 ) {
            if ($pdata[ 'type' ] != 'free' ) {
                $segusedcount++;
            }
        }
    }

    // sanity checks
    if ($pmap == false ) {
        error('partition map returned false; disk not recognized?');
    } elseif (!@isset($pmap[ $seg ]) ) {
        error('partition segment does not exist! Disk contents changed?');
    } elseif (count($pmap) != $_POST[ 'seg_totalseg' ] ) {
        error(
            'number of partition segments does not conform to submitted form. '
            . 'Disk contents changed?' 
        );
    }

    // create partition scheme
    if (@isset($_POST[ 'seg_submit_scheme' ]) ) {
        // chosen scheme 
        $scheme = @$_POST[ 'newscheme' ];

        // GEOM label (we have to redirect and not execute create scheme below)
        if ($scheme == 'geom' ) {
            $s = sanitize(@$_POST[ 'geomlabel' ], false, $label);
            if (!$s ) {
                friendlyerror(
                    'please use only alphanumerical characters in the label name',
                    $url2 
                );
            }
            if (file_exists('/dev/label/' . $label) ) {
                friendlyerror(
                    'this label is already in use, please choose another one',
                    $url2 
                );
            }
            $result = super_execute('/sbin/glabel create ' . $label . ' ' . $diskname);
            if ($result[ 'rv' ] == 0 ) {
                page_feedback(
                    'created GEOM label "' . htmlentities($label) . '" on disk '
                    . $diskname, 'b_success' 
                );
                redirect_url($url);
            } else {
                friendlyerror('could not create GEOM label on disk ' . $diskname, $url2);
            }
        }

        // create scheme (MBR/GPT)
        super_execute('/sbin/gpart create -s ' . $scheme . ' ' . $diskname);

        // create boot partition if selected
        if (@$_POST[ 'cb_newscheme_bootpart' ] == 'on'
            AND $scheme == 'gpt' 
        ) {
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
            // create boot partition now
            $r = super_execute(
                '/sbin/gpart add -b 64 -s 1024 -t freebsd-boot '
                . $diskname 
            );
            if ($r[ 'rv' ] == 0 ) {
                // insert bootcode onto boot partition
                $cmd = '/sbin/gpart bootcode -b ' . $pmbr . ' -p ' . $gptzfsboot
                . ' -i 1 ' . $diskname;
                $r = super_execute($cmd);
                if ($r[ 'rv' ] != 0 ) {
                    page_feedback(
                        'could not insert boot code to boot partition; '
                        . 'got return value ' . $r[ 'rv' ], 'a_failure' 
                    );
                }
            } else {
                page_feedback(
                    'could not create boot partition; got return value ' . $r[ 'rv' ],
                    'a_failure' 
                );
            }
        }
        page_feedback(
            'created partition scheme <b>' . strtoupper($scheme)
            . '</b> on disk <b>' . $diskname . '</b>', 'b_success' 
        );
        // redirect
        redirect_url($url);
    }

    // geom segment
    if (@isset($_POST[ 'geom_submit' ]) ) {
        // data
        $labels = disk_detect_label();
        $action = @$_POST[ 'geom_action' ];
        $oldlabel = @$labels[ $diskname ];
        $newlabel = @$_POST[ 'geom_labelname' ];
        // sanity
        if (!$oldlabel ) {
            friendlyerror('geom label not found on disk ' . $diskname, $url);
        }
        // action to perform (radio buttons)
        if ($action == 'destroy' ) {
            // remove geom label
            $commands = '/sbin/glabel stop ' . $oldlabel;
            $r = super_execute($commands);
        } elseif ($action == 'rename' ) {
            // sanitize new geom label name
            $s = sanitize(@$_POST[ 'geom_labelname' ], false, $sanitizedlabel);
            if (!$s ) {
                friendlyerror(
                    'please use only alphanumerical characters in the label name',
                    $url2 
                );
            }
            if (file_exists('/dev/label/' . $sanitizedlabel) ) {
                friendlyerror(
                    'this label is already in use, please choose another one',
                    $url2 
                );
            }
            // rename geom label to $newlabel
            $commands = '/sbin/glabel label ' . $sanitizedlabel . ' ' . $diskname;
            $r = super_execute($commands);
        }
        else {
            page_feedback('nothing changed to GEOM labeled disk', 'c_notice');
        }
        if ($r[ 'rv' ] != 0 ) {
            friendlyerror('error while performing action on geom labeled disk', $url2);
        }
    }

    // create partition in free space segment
    if (@isset($_POST[ 'gpt_seg_submit_free' ])OR @isset($_POST[ 'mbr_seg_submit_free' ]) ) {
        // diskinfo
        $diskinfo = disk_info($diskname);

        // sanity
        if (!is_numeric(@$diskinfo[ 'sectorsize' ]) ) {
            error('unable to determine sectorsize of ' . $diskname);
        }
        if (@$pmap[ $seg ][ 'type' ] != 'free' ) {
            error('segment is not free space!');
        }

        // fetch POST data
        if (@isset($_POST[ 'gpt_seg_submit_free' ]) ) {
            if (@$_POST[ 'newseg_unit' ] == '1M' ) {
                $rawsize = ( int )@$_POST[ 'gpt_newseg_size_M' ];
            } elseif (@$_POST[ 'newseg_unit' ] == '1G' ) {
                $rawsize = ( int )@$_POST[ 'gpt_newseg_size_G' ];
            } else {
                $rawsize = ( int )@$_POST[ 'gpt_newseg_size' ];
            }
            $sizeunit = @$_POST[ 'newseg_unit' ];
            $alignment = @$_POST[ 'gpt_newseg_alignment' ];
            $location = @$_POST[ 'gpt_newseg_location' ];
            $trim = @$_POST[ 'gpt_newseg_trim' ];
        } elseif (@isset($_POST[ 'mbr_seg_submit_free' ]) ) {
            if (@$_POST[ 'newseg_unit' ] == '1M' ) {
                $rawsize = ( int )@$_POST[ 'mbr_newseg_size_M' ];
            } elseif (@$_POST[ 'newseg_unit' ] == '1G' ) {
                $rawsize = ( int )@$_POST[ 'mbr_newseg_size_G' ];
            } else {
                $rawsize = ( int )@$_POST[ 'mbr_newseg_size' ];
            }
            $sizeunit = @$_POST[ 'newseg_unit' ];
            $alignment = @$_POST[ 'mbr_newseg_alignment' ];
            $location = @$_POST[ 'mbr_newseg_location' ];
            $trim = @$_POST[ 'mbr_newseg_trim' ];
        }

        // new partition size
        if ($sizeunit == 'SECT' ) {
            $unit = @$diskinfo[ 'sectorsize' ];
        } elseif ($sizeunit == '1M' ) {
            $unit = 1024 * 1024;
        } elseif ($sizeunit == '1G' ) {
            $unit = 1024 * 1024 * 1024;
        } else {
            error('invalid unit for partition size!');
        }
        $size = $rawsize * $unit;
        $size_sect = $size / @$diskinfo[ 'sectorsize' ];

        // alignment
        if ($alignment == 'SECT' ) {
            $align = @$diskinfo[ 'sectorsize' ];
        } elseif ($alignment == '4K' ) {
            $align = max(@$diskinfo[ 'sectorsize' ], 4096);
        } elseif ($alignment == '1M' ) {
            $align = max(@$diskinfo[ 'sectorsize' ], 1024 * 1024);
        } else {
            error('invalid alignment chosen!');
        }
        $alignsect = $align / @$diskinfo[ 'sectorsize' ];

        // free space segment offset
        $minoffset = ceil($pmap[ $seg ][ 'start' ] / $alignsect) * $alignsect;
        $maxoffset = ( floor(( $pmap[ $seg ][ 'end' ] + 1 ) / $alignsect) * $alignsect ) - 1;
        $maxsize = ( $maxoffset + 1 ) - $minoffset;

        // alignment corrected intended partition size
        $size_sectaligned = floor($size_sect / $alignsect) * $alignsect;

        // sanity on intended partition size
        if ($size_sectaligned <= 0 ) {
            friendlyerror('partition is too small for chosen alignment', $url2);
        } elseif ($size_sectaligned > $maxsize ) {
            page_feedback(
                'partition size adjusted to ' . $maxsize
                . ' sectors (' . sizebinary($maxsize * $diskinfo[ 'sectorsize' ]) . ')',
                'c_notice' 
            );
            $size_sectaligned = $maxsize;
        }

        // determine startoffset by partition location choice
        if ($location == 'begin' ) {
            $startoffset = $minoffset;
        } elseif ($location == 'end' ) {
            $startoffset = ( $maxoffset + 1 ) - $size_sectaligned;
        } else {
            error('invalid location for new partition!');
        }

        // partition type (only used for GPT segments currently)
        $ptype = @$_POST[ 'gpt_newseg_type' ];
        if (strlen($ptype) > 0 ) {
            $ptype = '"' . $ptype . '"';
        } else {
            $ptype = 'freebsd-zfs';
        }

        // partition label (for GPT schemes only)
        $labelname = @$_POST[ 'gpt_newseg_label' ];
        if ($scheme == 'GPT'
            AND strlen($labelname) > 0 
        ) {
            $s = sanitize($labelname);
            if (!$s ) {
                friendlyerror(
                    'if you chose to assign a label to this disk, it must consist '
                    . 'only of alphanumerical characters, underscore (_) or dash (-)', $url2 
                );
            }
            if (file_exists('/dev/gpt/' . $labelname) ) {
                friendlyerror(
                    'this label is already in use, please choose another one',
                    $url2 
                );
            }
            $gptlabelsuffix = '-l "' . $labelname . '" ';
        } else {
            $gptlabelsuffix = '';
        }

        // create partition depending on partition scheme (GPT/MBR)
        if ($scheme == 'GPT' ) {
            $result = super_execute(
                '/sbin/gpart add -t ' . $ptype . ' -b ' . $startoffset
                . ' -s ' . $size_sectaligned . ' ' . $gptlabelsuffix . $diskname 
            );
        } elseif ($scheme == 'MBR' ) {
            $result = super_execute(
                '/sbin/gpart add -t freebsd -b ' . $startoffset
                . ' -s ' . $size_sectaligned . ' ' . $diskname 
            );
        }

        if (@$result[ 'rv' ] == 0 ) {
            page_feedback('partition created!', 'b_success');
        } else {
            page_feedback('could not create partition!', 'a_failure');
        }

        // TRIM erase after creating partition
        if ($trim == 'on' ) {
            // we need to find the device node of the newly created partition
            $pmap = disk_partitionmap($diskname);
            $devnode = false;
            foreach ( $pmap as $id => $pdata ) {
                if ($pdata[ 'size_sect' ] == $size_sectaligned ) {
                    if ($devnode === false ) {
                        $devnode = @$pdata[ 'dev' ];
                    } else {
                        friendlyerror(
                            'could not TRIM erase newly created partition because '
                            . 'device node could not be found. This should only happen if another '
                            . 'partition exists with the exact same size.', $url2 
                        );
                    }
                }
            }
            // we should not have found our device node; now strip the /dev/ prefix
            $devnodesuffix = substr($devnode, strlen('/dev/'));
            if (strlen($devnodesuffix) > 0 ) {
                $result = super_script('secure_erase', $devnodesuffix);
                if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
                    page_feedback(
                        'TRIM erasing disk ' . $devnodesuffix . ' failed, got return '
                        . 'value ' . ( int )$result[ 'rv' ] . '. Command output:<br /><br />'
                        . nl2br($result[ 'output_str' ]), 'a_failure' 
                    );
                }
            } else {
                page_feedback(
                    'could not TRIM erase partition; unable to detect device',
                    'a_warning' 
                );
            }
        }
    }

    // GPT update bootcode
    if (@isset($_POST[ 'seg_submit_bootcode' ]) ) {
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
        // insert bootcode onto boot partition
        $index = @$_POST[ 'seg_index' ];
        $cmd = '/sbin/gpart bootcode -b ' . $pmbr . ' -p ' . $gptzfsboot
        . ' -i ' . $index . ' ' . $diskname;
        $r = super_execute($cmd);
        if ($r[ 'rv' ] != 0 ) {
            page_feedback(
                'could not insert boot code to boot partition; '
                . 'got return value ' . $r[ 'rv' ], 'a_failure' 
            );
            page_feedback(
                'command output:<br />' . nl2br(htmlentities($r[ 'output_str' ])),
                'c_notice' 
            );
        } else {
            page_feedback(
                'updated bootcode on disk <b>' . $diskname . '</b> index ' . $index,
                'b_success' 
            );
        }
    }

    // GPT update bootcode from system
    if (@isset($_POST[ 'seg_submit_bootcode_system' ]) ) {
        // bootcode (use from webinterface files directory unless not present)
        $pmbr = '/boot/pmbr';
        $gptzfsboot = '/boot/gptzfsboot';
        // sanity
        if (!file_exists($pmbr)OR!file_exists($gptzfsboot) ) {
            friendly_error('can not update bootcode from system source', $url2);
        }
        // insert bootcode onto boot partition
        $index = @$_POST[ 'seg_index' ];
        $cmd = '/sbin/gpart bootcode -b ' . $pmbr . ' -p ' . $gptzfsboot
        . ' -i ' . $index . ' ' . $diskname;
        $r = super_execute($cmd);
        if ($r[ 'rv' ] != 0 ) {
            page_feedback(
                'could not insert boot code to boot partition; '
                . 'got return value ' . $r[ 'rv' ], 'a_failure' 
            );
            page_feedback(
                'command output:<br />' . nl2br(htmlentities($r[ 'output_str' ])),
                'c_notice' 
            );
        } else {
            page_feedback(
                'updated bootcode on disk <b>' . $diskname . '</b> index ' . $index
                . ' using system bootcode', 'b_success' 
            );
        }
    }

    // destroy bootcode partition
    if (@isset($_POST[ 'seg_submit_bootcode_destroy' ]) ) {
        // destroy partition
        $index = @$_POST[ 'seg_index' ];
        $result = super_execute('/sbin/gpart delete -i ' . $index . ' ' . $diskname);
        if ($result[ 'rv' ] == 0 ) {
            page_feedback(
                'destroyed partition with index ' . $index . ' on ' . $diskname,
                'b_success' 
            );
        } else {
            page_feedback(
                'could not destroy partition with index ' . $index
                . ' on ' . $diskname, 'a_failure' 
            );
        }
    }

    // destroy partition scheme
    if (@isset($_POST[ 'submit_destroyscheme' ]) ) {
        $result = super_execute('/sbin/gpart destroy ' . $diskname);
        if ($result[ 'rv' ] == 0 ) {
            page_feedback('destroyed partition scheme on ' . $diskname, 'b_success');
        } else {
            page_feedback(
                'could not destroy partition scheme on disk ' . $diskname,
                'a_failure' 
            );
            page_feedback(
                'command output:<br />'
                . nl2br(htmlentities($result[ 'output_str' ])), 'c_notice' 
            );
        }
    }

    // recover partition scheme
    if (@isset($_POST[ 'seg_submit_recoverscheme' ]) ) {
        $result = super_execute('/sbin/gpart recover ' . $diskname);
        if ($result[ 'rv' ] == 0 ) {
            page_feedback(
                'successfully recovered partition scheme on ' . $diskname,
                'b_success' 
            );
        } else {
            page_feedback(
                'could not recover partition scheme on disk ' . $diskname,
                'a_failure' 
            );
            page_feedback(
                'command output:<br />'
                . nl2br(htmlentities($result[ 'output_str' ])), 'c_notice' 
            );
        }
    }

    // segment processing
    if (@isset($_POST[ 'seg_submit_gpt' ])OR @isset($_POST[ 'seg_submit_mbr' ]) ) {
        // fetch POST data
        if (@isset($_POST[ 'seg_submit_gpt' ]) ) {
            $newtype = @$_POST[ 'gpt_seg_type' ];
            $operation = @$_POST[ 'gpt_seg_operation' ];
            $newsize = @$_POST[ 'gpt_seg_resize_sect' ];
        } elseif (@isset($_POST[ 'seg_submit_mbr' ]) ) {
            $newtype = @$_POST[ 'mbr_seg_type' ];
            $operation = @$_POST[ 'mbr_seg_operation' ];
            $newsize = @$_POST[ 'mbr_seg_resize_sect' ];
        }

        // segment partition index
        $index = @$_POST[ 'seg_index' ];
        if (!is_numeric($index) ) {
            error('HARD ERROR: invalid segment index type');
        }

        // command result array
        $result = array();

        // update/rename GPT partition label (not available for MBR)
        if (@isset($_POST[ 'gpt_seg_label' ]) ) {
            if (@$_POST[ 'gpt_seg_label' ] != @$pmap[ $seg ][ 'label' ] ) {
                $s = sanitize(@$_POST[ 'gpt_seg_label' ], false, $newlabel);
                if (strlen(@$_POST[ 'gpt_seg_label' ]) < 1 ) {
                    $newlabel = '';
                } elseif (!$s ) {
                    friendlyerror(
                        'if you chose to assign a label to this disk, it must '
                        . 'consist only of alphanumerical characters, underscore (_) or dash (-)',
                        $url2 
                    );
                } elseif (file_exists('/dev/gpt/' . $newlabel) ) {
                    friendlyerror(
                        'this label is already in use, please choose another one',
                        $url2 
                    );
                }
                $result[] = super_execute(
                    '/sbin/gpart modify -i ' . $index
                    . ' -l "' . $newlabel . '" ' . $diskname 
                );
                // workaround for FreeBSD bug where device changes are not always propagated
                $result[] = super_script('device_activate_changes', $diskname);
            }
        }

        // update partition type
        if ($newtype != '!' . $pmap[ $seg ][ 'rawtype' ] ) {
            $result[] = super_execute(
                '/sbin/gpart modify -i ' . $index
                . ' -t "' . $newtype . '" ' . $diskname 
            );
            // workaround for FreeBSD bug where device changes are not always propagated
            $result[] = super_script('device_activate_changes', $diskname);
            page_feedback('partition type changed to ' . $newtype, 'c_notice');
        }

        // dangerous operations
        if ($operation == 'resize' ) {
            // query diskinfo for sectorsize
            $diskinfo = disk_info($diskname);
            // sanity
            if (@$diskinfo[ 'mediasize' ] < 1 ) {
                friendlyerror('invalid disk: ' . $diskname, 'a_error');
            }
            if ($newsize < 1 ) {
                friendlyerror('cannot resize to ' . ( int )$newsize . ' sectors!', 'a_failure');
            }
            // alignment
            // TODO: preserve existing alignment by detecting it ??
            $align = @$diskinfo[ 'sectorsize' ];
            // aligned sector count (blocks of this number of sectors will be aligned)
            $alignsect = $align / @$diskinfo[ 'sectorsize' ];

            // free space segment offset
            $minoffset = ceil($pmap[ $seg ][ 'start' ] / $alignsect) * $alignsect;
            $maxoffset = ( floor(( $pmap[ $seg + 1 ][ 'end' ] + 1 ) / $alignsect) * $alignsect ) - 1;
            $maxsize = ( $maxoffset + 1 ) - $minoffset;
            // alignment corrected intended partition size
            $size_sectaligned = floor($newsize / $alignsect) * $alignsect;

            // sanity checks when enlarging segment
            if ($newsize > $pmap[ $seg ][ 'size_sect' ] ) {
                // sanity check; seg+1 must exist (free space segment) when enlarging
                if ($pmap[ $seg + 1 ][ 'type' ] != 'free' ) {
                    friendlyerror(
                        'cannot resize because no free space segment is available!',
                        $url2 
                    );
                }
                // check whether size exceeds the maximum
                if ($size_sectaligned > $maxsize ) {
                    page_feedback(
                        'intended partition size (' . $size_sectaligned . ' sectors) '
                        . 'exceeds the maximum size of ' . $maxsize . ' sectors!', 'a_warning' 
                    );
                    $size_sectaligned = $maxsize;
                }
            }

            // resize partition
            $result = super_execute(
                '/sbin/gpart resize -i ' . $index . ' -s '
                . $size_sectaligned . ' ' . $diskname 
            );
            // workaround for FreeBSD bug where device changes are not always propagated
            $result[] = super_script('device_activate_changes', $diskname);
            if ($result[ 'rv' ] == 0 ) {
                page_feedback(
                    'resized partition with index ' . $index . ' on ' . $diskname,
                    'b_success' 
                );
            } else {
                page_feedback(
                    'could not resize partition with index ' . $index
                    . ' on ' . $diskname, 'a_failure' 
                );
            }
        } elseif ($operation == 'destroy' ) {
            // destroy partition
            $result = super_execute('/sbin/gpart delete -i ' . $index . ' ' . $diskname);
            if ($result[ 'rv' ] == 0 ) {
                page_feedback(
                    'destroyed partition with index ' . $index . ' on ' . $diskname,
                    'b_success' 
                );
            } else {
                page_feedback(
                    'could not destroy partition with index ' . $index
                    . ' on ' . $diskname, 'a_failure' 
                );
            }
        }
        elseif ($operation == 'zerowrite' ) {
            // zero-write
            $disksegdevsuf = substr(@$pmap[ $seg ][ 'dev' ], strlen('/dev/'));
            $result = super_script('zero_write', $disksegdevsuf);
            if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
                error(
                    'Zero writing disk ' . $disksegdevsuf . ' failed, got return value '
                    . ( int )$result[ 'rv' ] . '. Command output:<br /><br />'
                    . nl2br($result[ 'output_str' ]) 
                );
            }
        }
        elseif ($operation == 'randomwrite' ) {
            // random write
            $disksegdevsuf = substr(@$pmap[ $seg ][ 'dev' ], strlen('/dev/'));
            $result = super_script('random_write', $disksegdevsuf);
            if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
                error(
                    'Random writing disk ' . $disksegdevsuf . ' failed, got return value '
                    . ( int )$result[ 'rv' ] . '. Command output:<br /><br />'
                    . nl2br($result[ 'output_str' ]) 
                );
            }
        }
        elseif ($operation == 'trimerase' ) {
            // TRIM erase
            $disksegdevsuf = substr(@$pmap[ $seg ][ 'dev' ], strlen('/dev/'));
            $result = super_script('secure_erase', $disksegdevsuf);
            if ($result[ 'rv' ] != 0 AND $result[ 'rv' ] != 1 ) {
                error(
                    'TRIM erasing disk ' . $disksegdevsuf . ' failed, got return value '
                    . ( int )$result[ 'rv' ] . '. Command output:<br /><br />'
                    . nl2br($result[ 'output_str' ]) 
                );
            }
        }
    }

    // redirect
    redirect_url($url);
}
