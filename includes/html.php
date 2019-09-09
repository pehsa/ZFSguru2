<?php

function html_zfspools( $zfs_pool_list = false ) 
{
    // fetch pool list unless supplied as argument
    if (!is_array($zfs_pool_list) ) {
        activate_library('zfs');
        $zfs_pool_list = zfs_pool_list();
    }
    // craft string containing all selectbox options
    $options = '';
    foreach ( $zfs_pool_list as $poolname => $pooldata ) {
        $options .= '<option value="' . htmlentities($poolname) . '">'
        . htmlentities($poolname) . '</option>' . chr(10);
    }
    return $options;
}

function html_zfsfilesystems( $zfs_filesystem_list = false, $selectedfs = false,
    $hidesystem = true 
) {
    // fetch filesystem list unless supplied as argument
    if (!is_array($zfs_filesystem_list) ) {
        activate_library('zfs');
        $zfs_filesystem_list = zfs_filesystem_list(false, '-t filesystem');
    }
    // craft string containing all selectbox options
    $options = '';
    $notfirst = 0;
    if (is_array($zfs_filesystem_list) ) {
        foreach ( $zfs_filesystem_list as $fsname => $fsdata ) {
            if (!$hidesystem OR( $hidesystem AND!preg_match('/^.+\/zfsguru/', $fsname) ) ) {
                if (preg_match('/^[^\/]+$/', $fsname)AND $notfirst++ ) {
                    $options .= '<option value=""></option>';
                }
                if (( $fsname == $selectedfs )AND $selectedfs ) {
                    $options .= '<option value="' . htmlentities($fsname) . '" selected="selected">'
                    . htmlentities($fsname) . '</option>' . chr(10);
                } else {
                    $options .= '<option value="' . htmlentities($fsname) . '">'
                    . htmlentities($fsname) . '</option>' . chr(10);
                }
            }
        }
    }
    return $options;
}

function html_memberdisks( $physdisks = false, $only_gpt = false ) 
{
    // required libraries
    activate_library('disk');
    activate_library('zfs');

    // call functions
    if (!is_array($physdisks) ) {
        $physdisks = disk_detect_physical();
    }
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();

    // craft member disk string
    $mdstring = '';
    foreach ( $physdisks as $disk => $diskdata ) {
        // check for usable labels
        $usablelabels = array();
        if (is_array(@$gpart[ $disk ][ 'multilabel' ]) ) {
            foreach ( @$gpart[ $disk ][ 'multilabel' ] as $labelname => $devicenode ) {
                $usablelabels[ $devicenode ] = $labelname;
            }
        } elseif (( @strlen($labels[ $disk ]) > 0 )AND( $only_gpt == false ) ) {
            $usablelabels[ $disk ] = 'label/' . $labels[ $disk ];
        }
        // add usablelabels as options
        if (count($usablelabels) > 1 ) {
            $mdstring .= 'Disk <b>' . $disk . '</b> partitions: ';
            foreach ( $usablelabels as $devicenode => $labelname ) {
                $labeldev = htmlentities('gpt/' . $labelname);
                $size_human = sizehuman(
                    ( int )@$gpart[ $disk ][ 'providers' ][ $devicenode ][ 'length' ], 1 
                );
                if (!zfs_pool_ismember('gpt/' . $labelname)AND!zfs_pool_ismember($devicenode) ) {
                    $mdstring .= '<input type="checkbox" name="addmember_' . $labeldev . '" '
                    . '/><b>' . htmlentities($labelname) . '</b> (' . $size_human . ') ' . chr(10);
                } else {
                    $mdstring .= '<s><input type="checkbox" name="addmember_' . $labeldev . '" '
                    . 'disabled="disabled" /><b>' . htmlentities($labelname) . '</b></s> ('
                    . $size_human . ') ';
                }
            }
            $mdstring .= '<br />' . chr(10);
        } else {
            // determine size in human size
            $size_human = sizehuman(( int )$diskdata[ 'mediasize' ], 1);
            // check for GPT label
            $realdisk = false;
            if (@strlen($gpart[ $disk ][ 'label' ]) > 0 ) {
                $realdisk = 'gpt/' . $gpart[ $disk ][ 'label' ];
            } elseif (( @strlen($labels[ $disk ]) > 0 )AND( $only_gpt == false ) ) {
                $realdisk = 'label/' . $labels[ $disk ];
            }
            // skip disks which have no GPT or GEOM label
            if (!$realdisk ) {
                continue;
            }
            if ($poolname = zfs_pool_ismember($realdisk) ) {
                $mdstring .= '<input type="checkbox" name="addmember_' . htmlentities($realdisk) . '" '
                . 'disabled="disabled" />' . chr(10) . '<span class="diskdisabled">'
                . 'disk <b>' . htmlentities($disk) . '</b>, identified with label '
                . '<b>' . htmlentities($realdisk) . '</b></span> (' . $size_human . ')'
                . '<span class="diskinuse">in use for pool '
                . '<b>' . htmlentities($poolname) . '</b></span><br />' . chr(10);
            } elseif ($poolname = zfs_pool_ismember($disk) ) {
                $mdstring .= '<input type="checkbox" name="addmember_' . htmlentities($realdisk) . '" '
                . 'disabled="disabled" />' . chr(10) . '<span class="diskdisabled">'
                . 'disk <b>' . htmlentities($disk) . '</b>, identified with label '
                . '<b>' . htmlentities($realdisk) . '</b></span> (' . $size_human . ')'
                . '<span class="diskinuse">in use for pool '
                . '<b>' . htmlentities($poolname) . '</b></span><br />' . chr(10);
            } else {
                $mdstring .= '<input type="checkbox" name="addmember_' . htmlentities($realdisk) . '" '
                . '/> disk <b>' . htmlentities($disk) . '</b>, identified with label '
                . '<b>' . htmlentities($realdisk) . '</b> (' . $size_human . ')<br />' . chr(10);
            }
        }
    }
    return $mdstring;
}

function OLDhtml_memberdisks( $physdisks = false, $only_gpt = false ) 
{
    // required libraries
    activate_library('disk');
    activate_library('zfs');

    // call functions
    if (!is_array($physdisks) ) {
        $physdisks = disk_detect_physical();
    }
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();

    // craft member disk string
    $mdstring = '';
    foreach ( $physdisks as $disk => $diskdata ) {
        // check for GPT label
        $realdisk = false;
        if (@strlen($gpart[ $disk ][ 'label' ]) > 0 ) {
            $realdisk = 'gpt/' . $gpart[ $disk ][ 'label' ];
        } elseif (( @strlen($labels[ $disk ]) > 0 )AND( $only_gpt == false ) ) {
            $realdisk = 'label/' . $labels[ $disk ];
        }
        // determine size in human size
        $size_human = sizehuman(( int )$diskdata[ 'mediasize' ], 1);
        // skip disks which have no GPT or GEOM label
        if (!$realdisk ) {
            continue;
        }
        if ($poolname = zfs_pool_ismember($realdisk) ) {
            $mdstring .= '<input type="checkbox" name="addmember_' . htmlentities($realdisk) . '" '
            . 'disabled="disabled" />' . chr(10) . '<span class="diskdisabled">'
            . 'disk <b>' . htmlentities($disk) . '</b>, identified with label '
            . '<b>' . htmlentities($realdisk) . '</b></span> (' . $size_human . ')'
            . '<span class="diskinuse">in use for pool '
            . '<b>' . htmlentities($poolname) . '</b></span><br />' . chr(10);
        } else {
            $mdstring .= '<input type="checkbox" name="addmember_' . htmlentities($realdisk) . '" '
            . '/> disk <b>' . htmlentities($disk) . '</b>, identified with label '
            . '<b>' . htmlentities($realdisk) . '</b> (' . $size_human . ')<br />' . chr(10);
        }
    }
    return $mdstring;
}

function html_memberdisks_select( $physdisks = false, $only_gpt = false ) 
{
    // required libraries
    activate_library('disk');
    activate_library('zfs');

    // call functions
    if (!is_array($physdisks) ) {
        $physdisks = disk_detect_physical();
    }
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();

    // craft member disk string
    $mdstring = '';
    foreach ( $physdisks as $disk => $diskdata ) {
        // check for usable labels
        $usablelabels = array();
        if (is_array(@$gpart[ $disk ][ 'multilabel' ]) ) {
            foreach ( $gpart[ $disk ][ 'multilabel' ] as $labelname => $device ) {
                $usablelabels[ $device ] = 'gpt/' . $labelname;
            } 
        } elseif (( @strlen($labels[ $disk ]) > 0 )AND( $only_gpt == false ) ) {
             $usablelabels[ $disk ] = 'label/' . $labels[ $disk ];
        }

            // add usablelabels as options
        foreach ( $usablelabels as $device => $usablelabel ) {
            if (!zfs_pool_ismember($usablelabel)AND!zfs_pool_ismember($device) ) {
                $mdstring .= '  <option value="' . $usablelabel . '">'
                . $usablelabel . '</option>' . chr(10);
            }
        }
    }
    return $mdstring;
}

function html_wholedisks( $physdisks = false, $only_gpt = false ) 
{
    // required libraries
    activate_library('disk');
    activate_library('zfs');

    // call functions
    if (!is_array($physdisks) ) {
        $physdisks = disk_detect_physical();
    }
    $gpart = disk_detect_gpart();
    $labels = disk_detect_label();

    // craft member disk string
    $htmlstring = '';
    foreach ( $physdisks as $disk => $diskdata ) {
        // check for GPT label
        $label = false;
        if (@strlen($gpart[ $disk ][ 'label' ]) > 0 ) {
            $label = 'gpt/' . $gpart[ $disk ][ 'label' ];
        } elseif (( @strlen($labels[ $disk ]) > 0 )AND( $only_gpt == false ) ) {
            $label = 'label/' . $labels[ $disk ];
        }
        // determine size in human size
        $size_human = sizehuman(( int )$diskdata[ 'mediasize' ], 1);
        // set labelname
        $labelname = '';
        if ($label ) {
            $labelname = ', identified with label <b>' . htmlentities($label) . '</b>';
            $membercheckname = $label;
        } else {
            $membercheckname = $disk;
        }
        if ($poolname = zfs_pool_ismember($membercheckname) ) {
            $htmlstring .=
            '<input type="checkbox" name="addwholedisk_' . htmlentities($disk) . '" '
            . 'disabled="disabled" />' . chr(10) . '<span class="diskdisabled">'
            . 'disk <b>' . htmlentities($disk) . '</b>' . $labelname . '</span> (' . $size_human . ')'
            . '<span class="diskinuse">in use for pool '
            . '<b>' . htmlentities($poolname) . '</b></span><br />' . chr(10);
        } elseif ($poolname = zfs_pool_ismember($disk) ) {
            $htmlstring .= '<input type="checkbox" name="addmember_'
            . htmlentities($realdisk) . '" disabled="disabled" />' . chr(10)
            . '<span class="diskdisabled">disk <b>' . htmlentities($disk) . '</b>, '
            . 'identified with label <b>' . htmlentities($realdisk) . '</b></span> ('
            . $size_human . ') <span class="diskinuse">in use for pool '
            . '<b>' . htmlentities($poolname) . '</b></span><br />' . chr(10);
        } else {
            $htmlstring .=
            '<input type="checkbox" name="addwholedisk_' . htmlentities($disk) . '" '
            . '/> disk <b>' . htmlentities($disk) . '</b>' . $labelname . ' (' . $size_human
            . ')<br />' . chr(10);
        }
    }
    return $htmlstring;
}
