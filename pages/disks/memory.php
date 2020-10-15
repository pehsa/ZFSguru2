<?php

/**
 * @return array
 */
function content_disks_memory()
{
    global $tags;

    // required library
    activate_library('disk');

    // call functions
    $disks = disk_detect_physical();
    $dmesg = disk_detect_dmesg();
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();
    $gnop = disk_detect_gnop();

    // variables
    $querydisk = @$_GET[ 'query' ];

    // memory disk array
    $mdarr = [];

    // list each disk (partition)
    $physdisks = [];
    if (@is_array($disks) ) {
        foreach ( $disks as $diskname => $data ) {
            // skip if not a memory disk
            if (strncmp($diskname, 'md', 2) !== 0) {
                continue;
            }

            $mdarr[ ( int )substr($diskname, strlen('md')) ] = true;
            // active row (unused)
            $class_activerow = ( $querydisk == $diskname ) ? 'activerow' : 'normal';
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
                $sectorclass = 'network_sector_normal';
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
                if ($labelstr !== '') {
                    $labelstr .= '<br />';
                }
                $labelstr .= 'GPT: ' . @htmlentities($gpart[ $diskname ][ 'label' ]);
            }

            // add new row to table array
            $physdisks[] = [
            'CLASS_ACTIVEROW' => $class_activerow,
            'DISK_NAME' => htmlentities($diskname),
            'DISK_LABEL' => $labelstr,
            'DISK_SIZE_LEGACY' => @sizehuman($data[ 'mediasize' ], 1),
            'DISK_SIZE_BINARY' => @sizebinary($data[ 'mediasize' ], 1),
            'DISK_CLASS_SECTOR' => $sectorclass,
            'DISK_SIZE_SECTOR' => $sectorsize,
            'DISK_BACKING' => @$dmesg[ $diskname ]
            ];
        }
    }

    // number of memory disks
    $diskcount = @( int )count($mdarr);
    // memory disk unit table
    $table_mdunits = [];
    for ( $i = 1; $i < 256; $i++ ) {
        if (!@isset($mdarr[ $i ]) ) {
            $table_mdunits[] = [
                'MD_UNIT_NAME' => $i,
                'MD_UNIT_VALUE' => $i
            ];
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

    // display/hide vnode destroy div
    $class_vnode_destroy = ( @isset($tags[ 'CLASS_VNODE_DESTROY' ]) ) ?
    'normal' : 'hidden';
    // display extra row when no memory disks have been configured
    $class_nomemdisks = ( empty($physdisks) ) ? 'normal' : 'hidden';

    // export new tags
    return [
    'PAGE_ACTIVETAB' => 'Memory disks',
    'PAGE_TITLE' => 'Memory disks',
    'TABLE_MEMDISKS' => $physdisks,
    'TABLE_MD_UNITS' => $table_mdunits,
    'CLASS_NOMEMDISKS' => $class_nomemdisks,
    'CLASS_VNODE_DESTROY' => $class_vnode_destroy,
    'DISKS_DISKCOUNT' => $diskcount,
    'QUERY_DISKNAME' => $querydisk,
    'FORMAT_CLASS' => $formatclass,
    'FORMAT_GPTCHECKED' => @$gptchecked,
    'FORMAT_GEOMCHECKED' => @$geomchecked,
    'FORMAT_GPTLABEL' => @$gptlabel,
    'FORMAT_GEOMLABEL' => @$geomlabel
    ];
}

/**
 * @return array
 */
function submit_disks_memory()
{
    // required library
    activate_library('disk');

    // redirect URL
    $url = 'disks.php?mem';

    // destroy memory disk
    foreach ( $_POST as $name => $value ) {
        if (strncmp($name, 'md_destroy_md', 13) === 0) {
            $mdunit = substr($name, strlen('md_destroy_md'));
            $data = disk_detect_memorydisk($mdunit);
            if (@$data[ $mdunit ][ 'backing' ] === 'vnode' ) {
                // handle vnode (file backed) memory disks differently
                $file = $data[ $mdunit ][ 'file' ];
                if (file_exists($file) ) {
                    return [
                    'CLASS_VNODE_DESTROY' => 'normal',
                    'VNODE_MDUNIT' => $mdunit,
                    'VNODE_FILE' => htmlentities($file)
                    ];
                }
                // nonexistent file
                page_feedback(
                    'the file that memory disk md' . $mdunit . ' is referring to ('
                    . htmlentities($file) . ') no longer exists', 'a_warning' 
                );
            }
            // normal memory disk or vnode memory disk with nonexistent file
            if (is_numeric($mdunit) ) {
                dangerouscommand('/sbin/mdconfig -d -u ' . ( int )$mdunit, $url);
            }
        }
    }

    // destroy memory disk with vnode backing and existing file
    if (@isset($_POST[ 'md_destroy_vnode' ]) ) {
        $mdunit = ( int )@$_POST[ 'md_destroy_unit' ];
        $file = @$_POST[ 'md_destroy_file' ];
        $data = disk_detect_memorydisk($mdunit);
        if ($file != $data[ $mdunit ][ 'file' ] ) {
            error('HARD ERROR: cannot destroy memory disk with conflicting file!');
        }
        // remove file
        if (file_exists($file) ) {
            $commands = [
            '/sbin/mdconfig -d -u ' .$mdunit,
            '/bin/rm -f ' . $file
            ];
            dangerouscommand($commands, $url);
        } else {
            error('file ' . $file . ' does not exists - cannot destroy vnode backed md');
        }
        // destroy memory disk
        if (is_numeric($mdunit) ) {
            dangerouscommand('/sbin/mdconfig -d -u ' . ( int )$mdunit, $url);
        }
    } elseif (@isset($_POST[ 'md_destroy_vnode_keepfile' ]) ) {
        // destroy vnode with existing file, but keep the file
        $mdunit = ( int )@$_POST[ 'md_destroy_unit' ];
        // destroy memory disk
        if (is_numeric($mdunit) ) {
            dangerouscommand('/sbin/mdconfig -d -u ' . ( int )$mdunit, $url);
        }
    }

    // create memory disk
    if (@isset($_POST[ 'md_create' ]) ) {
        // unit
        $unit = '';
        if (( @$_POST[ 'md_unit' ] !== 'auto' )AND(@$_POST['md_unit'] != '') ) {
            $unit = ' -u ' . ( int )$_POST[ 'md_unit' ];
        }
        // type (malloc, swap or vnode)
        $type = @$_POST[ 'md_type' ];
        // size
        $size = @$_POST[ 'md_size' ] . @$_POST[ 'md_size_unit' ];
        // options
        $options = '';
        if (@$_POST[ 'md_type' ] === 'vnode' ) {
            // sanity checks
            $file = @$_POST[ 'md_file' ];
            if ($file == '') {
                friendlyerror('please provide a file name when using file backing', $url);
            }
            if (!is_dir(dirname($file)) ) {
                friendlyerror(
                    'the path you provided is incorrect; the directory "'
                    . dirname($file) . '" does not exist', $url 
                );
            }
            if ($file {           0          } !== '/'
            ) {
                friendlyerror('please use a full path starting with "/" character', $url);
            }
            if (file_exists($file) && !is_file($file)) {
                friendlyerror(
                    'the file you provided is either a directory or special '
                    . 'file (symbolic link, device)', $url
                );
            }
            // determine real size in bytes
            if (@$_POST[ 'md_size_unit' ] === 't' ) {
                $realsize = @$_POST[ 'md_size' ] * 1024 * 1024 * 1024 * 1024;
            } elseif (@$_POST[ 'md_size_unit' ] === 'g' ) {
                $realsize = @$_POST[ 'md_size' ] * 1024 * 1024 * 1024;
            } elseif (@$_POST[ 'md_size_unit' ] === 'm' ) {
                $realsize = @$_POST[ 'md_size' ] * 1024 * 1024;
            } elseif (@$_POST[ 'md_size_unit' ] === 'k' ) {
                $realsize = @$_POST[ 'md_size' ] * 1024;
            } else {
                $realsize = @$_POST[ 'md_size' ] * @$_POST[ 'md_sectorsize' ];
            }
            // create file if nonexistent
            if (!file_exists($file) ) {
                $dd_seek = ( $realsize / 512 ) - 1;
                $command = '/bin/dd if=/dev/zero of=' . $file . ' bs=512 oseek='
                . $dd_seek . ' count=1';
                // create file with super privileges
                activate_library('super');
                $result = super_execute($command);
                if ($result[ 'rv' ] != 0 ) {
                    friendlyerror('could not create file ' . htmlentities($file), $url);
                } elseif (!file_exists($file) ) {
                    friendlyerror('file creation not successful', $url);
                }
            }
            // finally, add file to options string
            $options = '-f ' . $file . ' ';
        }
        if (@$_POST[ 'md_opt_reserve' ] === 'on' ) {
            $options .= '-o reserve ';
        }
        if (@$_POST[ 'md_opt_compress' ] === 'on' ) {
            $options .= '-o compress ';
        }
        if (@$_POST[ 'md_opt_readonly' ] === 'on' ) {
            $options .= '-o readonly ';
        }
        // sector size 
        if ((@$_POST['md_sectorsize'] != 512) && ( int )@$_POST['md_sectorsize'] > 0) {
            $options .= '-S ' . ( int )$_POST[ 'md_sectorsize' ];
        }
        // defer to dangerouscommand function
        if ($type === 'vnode' ) {
            $command = '/sbin/mdconfig -a -t ' . $type . ' ' . $options . $unit;
        } else {
            $command = '/sbin/mdconfig -a -t ' . $type . ' -s ' . $size . ' ' . $options . $unit;
        }
        dangerouscommand($command, $url);
    }

    // default redirect
    redirect_url($url);
}
