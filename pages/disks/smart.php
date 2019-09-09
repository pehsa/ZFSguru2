<?php

function content_disks_smart() 
{
    global $sort, $invertedsort, $smart;

    // required library
    activate_library('disk');

    // threshold values
    disk_smartinfo();
    $thres = @$_SESSION[ 'smart' ][ 'threshold' ];

    // call function
    $disks = disk_detect_physical();

    // queried disk
    $query = ( strlen(@$_GET[ 'query' ]) > 0 ) ? $_GET[ 'query' ] : false;

    // query all button (stores in $_SESSION['smart'])
    if (@isset($_GET[ 'queryall' ]) ) {
        foreach ( $disks as $diskname => $diskdata ) {
            disk_smartinfo($diskname);
        }
        redirect_url('disks.php?smart');
    }

    // retrieve SMART information for queried disk
    if (@strlen($query) > 0 ) {
        $smart = disk_smartinfo($query);
    }

    // classes
    $class_querydisk = ( $query ) ? 'normal' : 'hidden';
    $class_advice_activesect = 'hidden';
    $class_advice_cableerrors = 'hidden';
    $class_advice_criticaltemp = 'hidden';
    $class_advice_hightemp = 'hidden';
    $class_advice_passivesect = 'hidden';
    $class_advice_highlccrate = 'hidden';
    $class_advice_inthepast = 'hidden';
    $class_advice_unknownfailure = 'hidden';
    $class_advice_noproblems = 'hidden';
    $class_advice_needscan = 'hidden';

    // LCC rate (highest rate of load cycles relative to lifetime/power-on-hours)
    $lccrate = false;

    // sorting
    $sorted = $disks;
    $sortsuffix = array();
    $sort = @$_GET[ 'sort' ];
    $invertedsort = ( @isset($_GET[ 'inverted' ]) ) ? true : false;
    if (strlen($sort) > 0 ) {
        uasort($sorted, 'sort_disks');
    }
    if (!$invertedsort AND $sort ) {
        $sortsuffix[ $sort ] = '&inverted';
    }

    // disk list
    $disklist = array();
    foreach ( $sorted as $diskname => $diskdata ) {
        // detect disk type
        $disktype = disk_detect_type($diskname);

        // SMART overall status
        $status = @$_SESSION[ 'smart' ][ $diskname ][ 'status' ];

        // classes
        $class_activerow = ( $diskname == $query ) ? 'activerow' : 'normal';
        $class_hdd = ( $disktype == 'hdd' ) ? 'normal' : 'hidden';
        $class_ssd = ( $disktype == 'ssd' ) ? 'normal' : 'hidden';
        $class_flash = ( $disktype == 'flash' ) ? 'normal' : 'hidden';
        $class_memdisk = ( $disktype == 'memdisk' ) ? 'normal' : 'hidden';
        $class_usbstick = ( $disktype == 'usbstick' ) ? 'normal' : 'hidden';
        $class_network = ( $disktype == 'network' ) ? 'normal' : 'hidden';
        $class_status = ( @$_SESSION[ 'smart' ][ $diskname ][ 'status' ] == 'Healthy' ) ?
        'green smart_status_healthy' : 'red smart_status_nothealthy';
        $class_showtempsect = ( @isset($_SESSION[ 'smart' ][ $diskname ]) ) ?
        'normal' : 'hidden';

        // check for stale data
        $tolerance = 1 * 60 * 60;
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'timestamp' ] < time() - $tolerance ) {
            $status = 'Stale - rescan disk';
            $class_status = 'smart_status_incapable';
        }

        // no SMART data present
        if ($class_showtempsect == 'hidden' ) {
            $status = 'Unknown';
            $class_status = 'smart_status_incapable';
        }
        if ($status == 'SMART incapable' ) {
            $class_status = 'grey smart_status_nothealthy';
        }

        // add row
        $disklist[] = array(
        'CLASS_ACTIVEROW' => $class_activerow,
        'CLASS_HDD' => $class_hdd,
        'CLASS_SSD' => $class_ssd,
        'CLASS_FLASH' => $class_flash,
        'CLASS_MEMDISK' => $class_memdisk,
        'CLASS_USBSTICK' => $class_usbstick,
        'CLASS_NETWORK' => $class_network,
        'SMART_DISK' => htmlentities(trim($diskname)),
        'SMART_STATUS' => $status,
        'SMART_TEMP_C' => @$_SESSION[ 'smart' ][ $diskname ][ 'temp_c' ],
        'SMART_TEMP_F' => @$_SESSION[ 'smart' ][ $diskname ][ 'temp_f' ],
        'SMART_POWERCYCLES' => @$_SESSION[ 'smart' ][ $diskname ][ 'power_cycles' ],
        'SMART_LOADCYCLES' => @$_SESSION[ 'smart' ][ $diskname ][ 'load_cycles' ],
        'SMART_CABLEERRORS' => @$_SESSION[ 'smart' ][ $diskname ][ 'cable_errors' ],
        'SMART_PASSIVESECTORS' =>
        @$_SESSION[ 'smart' ][ $diskname ][ 'reallocated_sectors' ],
        'SMART_PENDINGSECTORS' =>
        @$_SESSION[ 'smart' ][ $diskname ][ 'pending_sectors' ],
        'SMART_LIFETIME' => @$_SESSION[ 'smart' ][ $diskname ][ 'power_on' ],
        'CLASS_STATUS' => $class_status,
        'CLASS_TEMP' => @$_SESSION[ 'smart' ][ $diskname ][ 'class_temp' ],
        'CLASS_POWERCYCLES' => @$_SESSION[ 'smart' ][ $diskname ][ 'class_powercycles' ],
        'CLASS_LOADCYCLES' => @$_SESSION[ 'smart' ][ $diskname ][ 'class_loadcycles' ],
        'CLASS_CABLEERRORS' => @$_SESSION[ 'smart' ][ $diskname ][ 'class_cableerrors' ],
        'CLASS_BADSECTORS' => @$_SESSION[ 'smart' ][ $diskname ][ 'class_badsectors' ],
        'CLASS_LIFETIME' => @$_SESSION[ 'smart' ][ $diskname ][ 'class_lifetime' ],
        'CLASS_SHOWTEMP' => $class_showtempsect,
        'CLASS_SHOWSECT' => $class_showtempsect
        );
        // set highest LCC rate (highest = lowest number = most cycles per timeunit)
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'power_on_hours' ] > 24 ) {
            $current_lccrate = @( $_SESSION[ 'smart' ][ $diskname ][ 'power_on_hours' ] /
            $_SESSION[ 'smart' ][ $diskname ][ 'load_cycles' ] ) * 3600;
            if ($current_lccrate > 0 ) {
                if (( $lccrate === false )OR( $current_lccrate < $lccrate ) ) {
                    $lccrate = round($current_lccrate, 1);
                }
            }
        }
        // set advice level
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'pending_sectors' ] >=        $thres[ 'sect_active' ] 
        ) {
            $class_advice_activesect = 'normal';
        }
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'cable_errors' ] >= $thres[ 'cable' ] ) {
            $class_advice_cableerrors = 'normal';
        }
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'temp_c' ] >= $thres[ 'temp_crit' ] ) {
            $class_advice_criticaltemp = 'normal';
        } elseif (@$_SESSION[ 'smart' ][ $diskname ][ 'temp_c' ] >= $thres[ 'temp_high' ] ) {
            $class_advice_hightemp = 'normal';
        }
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'reallocated_sectors' ] >=        $thres[ 'sect_pas' ] 
        ) {
            $class_advice_passivesect = 'normal';
        }
        if (is_numeric($lccrate)AND( $lccrate <= $thres[ 'lcc_rate' ] ) ) {
            $class_advice_highlccrate = 'normal';
        }
        if (@strtoupper($_SESSION[ 'smart' ][ $diskname ][ 'status' ]) == 'IN_THE_PAST' ) {
            $class_advice_inthepast = 'normal';
        }
        if (@$_SESSION[ 'smart' ][ $diskname ][ 'status' ] == 'Failure' ) {
            $class_advice_unknownfailure = 'normal';
        }
        if (( $class_advice_activesect == 'hidden' )AND( $class_advice_cableerrors == 'hidden' )AND( $class_advice_criticaltemp == 'hidden' )AND( $class_advice_hightemp == 'hidden' )AND( $class_advice_passivesect == 'hidden' )AND( $class_advice_inthepast == 'hidden' )AND( @strtoupper($_SESSION[ 'smart' ][ $diskname ][ 'status' ]) == 'FAILURE' ) ) {
            $class_advice_unknownfailure = 'normal';
        }
        if (@!isset($_SESSION[ 'smart' ][ $diskname ]) ) {
            $class_advice_needscan = 'normal';
        }
    }

    // set no problems advice if applicable
    if (( $class_advice_activesect == 'hidden' )AND( $class_advice_cableerrors == 'hidden' )AND( $class_advice_criticaltemp == 'hidden' )AND( $class_advice_hightemp == 'hidden' )AND( $class_advice_passivesect == 'hidden' )AND( $class_advice_highlccrate == 'hidden' )AND( $class_advice_inthepast == 'hidden' )AND( $class_advice_unknownfailure == 'hidden' )AND( $class_advice_needscan == 'hidden' ) ) {
        $class_advice_noproblems = 'normal';
    }

    // smart list when querying disk
    $smartlist = array();
    if ($query AND is_array(@$smart[ 'data' ]) ) {
        // query disk smart list
        foreach ( $smart[ 'data' ] as $id => $data ) {
            // TODO: clean this up
            if (( $id == 5 OR $id == 192 OR $id == 196 OR $id == 198 )AND( $data[ 'raw' ] != 0 ) ) {
                $activerow = 'activerow';
            } elseif (( $id == 197 OR $id == 199 )AND( $data[ 'raw' ] != 0 ) ) {
                $activerow = 'failurerow';
            } elseif ($data[ 'failed' ] != '-' ) {
                $activerow = 'failurerow';
            } else {
                $activerow = '';
            }
            $smartlist[] = array(
            'SMART_ACTIVEROW' => $activerow,
            'SMART_ID' => ( int )$id,
            'SMART_ATTR' => htmlentities($data[ 'attribute' ]),
            'SMART_FLAG' => htmlentities($data[ 'flag' ]),
            'SMART_VALUE' => htmlentities($data[ 'value' ]),
            'SMART_WORST' => htmlentities($data[ 'worst' ]),
            'SMART_THRESHOLD' => htmlentities($data[ 'threshold' ]),
            'SMART_FAILED' => htmlentities($data[ 'failed' ]),
            'SMART_RAW' => htmlentities($data[ 'raw' ])
            );
        }
    }

    // export new tags
    $newtags = @array(
    'PAGE_ACTIVETAB' => 'SMART',
    'PAGE_TITLE' => 'SMART monitor',
    'TABLE_SMART_DISKLIST' => $disklist,
    'TABLE_QUERY_SMARTLIST' => $smartlist,
    'CLASS_QUERYDISK' => $class_querydisk,
    'CLASS_ADVICE_ACTIVESECT' => $class_advice_activesect,
    'CLASS_ADVICE_CABLEERRORS' => $class_advice_cableerrors,
    'CLASS_ADVICE_CRITICALTEMP' => $class_advice_criticaltemp,
    'CLASS_ADVICE_HIGHTEMP' => $class_advice_hightemp,
    'CLASS_ADVICE_PASSIVESECT' => $class_advice_passivesect,
    'CLASS_ADVICE_HIGHLCCRATE' => $class_advice_highlccrate,
    'CLASS_ADVICE_INTHEPAST' => $class_advice_inthepast,
    'CLASS_ADVICE_UNKNOWNFAILURE' => $class_advice_unknownfailure,
    'CLASS_ADVICE_NOPROBLEMS' => $class_advice_noproblems,
    'CLASS_ADVICE_NEEDSCAN' => $class_advice_needscan,
    'SORT_DISK' => $sortsuffix[ 'disk' ],
    'SORT_STATUS' => $sortsuffix[ 'status' ],
    'SORT_TEMP' => $sortsuffix[ 'temp' ],
    'SORT_POWER' => $sortsuffix[ 'power' ],
    'SORT_LCC' => $sortsuffix[ 'lcc' ],
    'SORT_CABLE' => $sortsuffix[ 'cable' ],
    'SORT_LIFETIME' => $sortsuffix[ 'lifetime' ],
    'ADVICE_LCC_RATE' => $lccrate,
    'QUERY_DISK' => $query,
    );
    return $newtags;
}

function sort_disks( $a, $b ) 
{
    global $sort, $invertedsort, $smart;

    // set easy to search attributes
    $attr = false;
    if ($sort == 'disk' ) {
        $attr = 'disk_name';
    } elseif ($sort == 'status' ) {
        $attr = 'status';
    } elseif ($sort == 'temp' ) {
        $attr = 'temp_c';
    } elseif ($sort == 'power' ) {
        $attr = 'power_cycles';
    } elseif ($sort == 'lcc' ) {
        $attr = 'load_cycles';
    } elseif ($sort == 'cable' ) {
        $attr = 'cable_errors';
    } elseif ($sort == 'badsect' ) {
        $attr = 'pending_sectors';
    } elseif ($sort == 'lifetime' ) {
        $attr = 'power_on_hours';
    } else {
        error('invalid sort string');
    }

    if ($sort == 'disk' ) {
        $aa = @$a[ 'disk_name' ];
        $bb = @$b[ 'disk_name' ];
    } elseif ($attr ) {
        $diskname_a = @$a[ 'disk_name' ];
        $diskname_b = @$b[ 'disk_name' ];
        $aa = @$_SESSION[ 'smart' ][ $diskname_a ][ $attr ];
        $bb = @$_SESSION[ 'smart' ][ $diskname_b ][ $attr ];
    }
    else {
        error('cannot sort disks on smart page');
    }

    // compare aa to bb
    if ($aa == $bb ) {
        return 0;
    } elseif ($invertedsort ) {
        return ( $aa < $bb ) ? 1 : -1;
    } else {
        return ( $aa < $bb ) ? -1 : 1;
    }
}
