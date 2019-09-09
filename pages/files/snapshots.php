<?php

function content_files_snapshots() 
{
    global $sort, $invertedsort;

    // snapshot list
    $snaplist = htmlentities(trim(`zfs list -t snapshot`));

    // required library
    activate_library('zfs');

    // fetch data
    $snapshots = zfs_filesystem_list('', '-t snapshot');
    $snaptotal = 0;

    // queried snapshot
    $query = @$_GET[ 'snapshots' ];
    $queryfs = substr($query, 0, strpos($query, '@'));
    if ($query ) {
        $prop = zfs_filesystem_properties($queryfs);
    }
    $query_hidden = 'hidden';
    $query_visible = 'hidden';
    if (@$prop[ $queryfs ][ 'snapdir' ][ 'value' ] == 'visible' ) {
        $query_visible = 'normal';
    } else {
        $query_hidden = 'normal';
    }

    // sorting
    $sort = @$_GET[ 'sort' ];
    $invertedsort = ( @isset($_GET[ 'inverted' ]) ) ? true : false;
    $sorted = ( is_array($snapshots) ) ? $snapshots : array();
    $sortsuffix = array();
    if (strlen($sort) > 0 ) {
        uasort($sorted, 'sort_snapshots');
    }
    if (!$invertedsort AND $sort ) {
        $sortsuffix[ $sort ] = '&inverted';
    }

    // construct snapshots table
    // TODO: promote and clone options display/hide
    $table_snapshots = array();
    foreach ( $sorted as $snapshot ) {
        $snaptotal += convertsuffix(@$snapshot[ 'used' ]);
        $active = ( $query == $snapshot[ 'name' ] ) ? 'activerow' : 'normal';
        $snap_fs = substr($snapshot[ 'name' ], 0, strpos($snapshot[ 'name' ], '@'));
        $snap_name = substr($snapshot[ 'name' ], strpos($snapshot[ 'name' ], '@') + 1);
        $table_snapshots[] = @array(
        'SNAP_ACTIVE' => $active,
        'SNAP_FS' => htmlentities($snap_fs),
        'SNAP_NAME' => htmlentities($snap_name),
        'SNAP_FSB64' => base64_encode($snap_fs),
        'SNAP_NAMEB64' => base64_encode($snap_name),
        'SNAP_USED' => $snapshot[ 'used' ],
        'SNAP_REFER' => $snapshot[ 'refer' ],
        'SNAP_PROMOTE' => 'hidden'
        );
    }

    // total snapshot usage
    $snap_totalusage = sizebinary($snaptotal, 1);

    // snapshot browse filesystem location
    $snap_browsefs = @$prop[ $queryfs ][ 'mountpoint' ][ 'value' ];

    // export new tags
    return @array(
    'PAGE_ACTIVETAB' => 'Snapshots',
    'PAGE_TITLE' => 'Snapshots',
    'TABLE_SNAPSHOTS' => $table_snapshots,
    'CLASS_QUERY' => ( strlen($query) > 0 ) ? 'normal' : 'hidden',
    'CLASS_NOQUERY' => ( strlen($query) > 0 ) ? 'hidden' : 'normal',
    'CLASS_SNAPHIDDEN' => $query_hidden,
    'CLASS_SNAPVISIBLE' => $query_visible,
    'SORT_FS' => @$sortsuffix[ 'fs' ],
    'SORT_NAME' => @$sortsuffix[ 'name' ],
    'SORT_USED' => @$sortsuffix[ 'used' ],
    'SORT_REFER' => @$sortsuffix[ 'refer' ],
    'SNAP_TOTALUSAGE' => $snap_totalusage,
    'SNAP_BROWSEFS' => $snap_browsefs,
    'QUERY_NAME' => $query
    );
}

function convertsuffix( $size_string )
{
    $sizeunits = array( 'B', 'K', 'M', 'G', 'T', 'E' );
    foreach ( $sizeunits as $index => $unit ) {
        if (strpos($size_string, $unit) > 0 ) {
            $indexfactor = pow(1000, $index);
            return ( ( int )$size_string * $indexfactor );
        }
    }
    return 0;
}

function sort_snapshots( $a, $b ) 
{
    global $sort, $invertedsort;
    $attr = false;

    // set easy to search attributes
    if ($sort == 'used' ) {
        $attr = 'used';
    } elseif ($sort == 'refer' ) {
        $attr = 'refer';
    }

    if ($attr ) {
        $aa = @$a[ $attr ];
        $bb = @$b[ $attr ];
    } elseif ($sort == 'fs' ) {
        $a_fs = substr($a[ 'name' ], 0, strpos($a[ 'name' ], '@'));
        $b_fs = substr($b[ 'name' ], 0, strpos($b[ 'name' ], '@'));
        $aa = $a_fs;
        $bb = $b_fs;
    }
    elseif ($sort == 'name' ) {
        $a_name = substr($a[ 'name' ], strpos($a[ 'name' ], '@') + 1);
        $b_name = substr($b[ 'name' ], strpos($b[ 'name' ], '@') + 1);
        $aa = $a_name;
        $bb = $b_name;
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

function submit_snapshot_operation() 
{
    // variables
    $url = 'files.php?snapshots';
    $snapshots = array();
    foreach ( $_POST as $name => $value ) {
        if (substr($name, 0, strlen('snapshot_')) == 'snapshot_' ) {
            $snap = substr($name, strlen('snapshot_'));
            $snapfs = base64_decode(substr($snap, 0, strpos($snap, '@')));
            $snapname = base64_decode(substr($snap, strpos($snap, '@') + 1));
            $snapshots[ $snapfs . '@' . $snapname ] = $value;
        }
    }

    // start commands array
    $commands = array();

    // rollback
    if (@isset($_POST[ 'submit_rollback' ]) ) {
        foreach ( $snapshots as $name => $operation ) {
            if ($operation == 'rollback' ) {
                $commands[] = '/sbin/zfs rollback ' . $name;
            }
        }
    }

    // clone
    if (@isset($_POST[ 'submit_clone' ]) ) {
        foreach ( $snapshots as $name => $operation ) {
            if ($operation == 'clone' ) {
                $clonename = @$_POST[ 'clone_name' ];
                if (strlen($clonename) < 1 ) {
                    friendlyerror('please enter a new name for the cloned filesystem', $url);
                }
                // add clone command to command array
                $commands[] = '/sbin/zfs clone ' . $name . ' ' . $clonename;
                // add promote command to command array if applicable
                if (@$_POST[ 'clone_promote' ] == 'on' ) {
                    $commands[] = '/sbin/zfs promote ' . $clonename;
                }
                // do not search for other submitted filesystems to clone
                break;
            }
        }
    }

    // destroy
    if (@isset($_POST[ 'submit_destroy' ]) ) {
        foreach ( $snapshots as $name => $operation ) {
            if ($operation == 'destroy' ) {
                $commands[] = '/sbin/zfs destroy ' . $name;
            }
        }
    }

    // promote
    if (@isset($_POST[ 'submit_promote' ]) ) {
        foreach ( $snapshots as $name => $operation ) {
            if ($operation == 'promote' ) {
                $commands[] = '/sbin/zfs promote ' . $name;
            }
        }
    }

    // execute commands
    if (count($commands) > 0 ) {
        dangerouscommand($commands, $url);
    } else {
        redirect_url($url);
    }
}

function submit_snapshot_visibility() 
{
    // variables
    $snapshot = @$_POST[ 'snapshot' ];
    $fs = substr($snapshot, 0, strpos($snapshot, '@'));
    $url = 'files.php?snapshots=' . $snapshot;

    // sanity
    if (strlen($snapshot) < 1 ) {
        friendlyerror('no snapshot submitted', $url);
    }

    // display or hide snapshot directory
    if (@isset($_POST[ 'snapdir_display' ]) ) {
        dangerouscommand('/sbin/zfs set snapdir=visible ' . $fs, $url);
    } elseif (@isset($_POST[ 'snapdir_hide' ]) ) {
        dangerouscommand('/sbin/zfs set snapdir=hidden ' . $fs, $url);
    } else {
        redirect_url($url);
    }
}
