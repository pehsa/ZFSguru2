<?php

/**
 * @return array
 */
function content_disks_advanced()
{
    // required library
    activate_library('disk');

    // call function
    $disks = disk_detect_physical();

    // queried disk
    $query = (@$_GET['query'] != '') ? $_GET[ 'query' ] : false;
    $cap = null;

    // detailed information when querying disk
    if ($query ) {
        // retrieve data via 'camcontrol identify' command
        $cap = disk_identify($query);

        // apply some fixes to the data format
        if ($cap[ 'detail' ][ 'overlap' ][ 'support' ] === 'no' ) {
            $cap[ 'detail' ][ 'overlap' ][ 'enabled' ] = 'no';
        }
        if ($cap[ 'detail' ][ 'Native Command Queuing (NCQ)' ][ 'support' ] === 'no' ) {
            $cap[ 'detail' ][ 'Native Command Queuing (NCQ)' ][ 'enabled' ] = 'no';
        }
        if (@is_numeric(
            $cap[ 'detail' ][ 'Native Command Queuing (NCQ)' ][ 'value' ] {
            0
            } 
        ) 
        ) {
            $cap[ 'detail' ][ 'Native Command Queuing (NCQ)' ][ 'enabled' ] = 'yes';
        }
        if (@$cap[ 'detail' ][ 'data set management (TRIM)' ][ 'support' ] === 'yes' ) {
            $cap[ 'detail' ][ 'data set management (TRIM)' ][ 'enabled' ] = 'yes';
        }
        if (@$cap[ 'detail' ][ 'data set management (TRIM)' ][ 'support' ] === 'no' ) {
            $cap[ 'detail' ][ 'data set management (TRIM)' ][ 'enabled' ] = 'no';
        }

        // APM - Advanced Power Management
        if ($cap[ 'detail' ][ 'advanced power management' ][ 'support' ] === 'yes' ) {
            $class_apm = 'normal';
            $rawapm = trim($cap[ 'detail' ][ 'advanced power management' ][ 'value' ]);
            $apm_dec = decode_raw_apmsetting($rawapm);
            $apm_current = ( $apm_dec ) ?:
            '<span class="minortext">unknown</span>';
            // cache APM enabled status
            $_SESSION[ 'disk_advanced' ][ $query ][ 'apm_enabled' ] =
            $cap[ 'detail' ][ 'advanced power management' ][ 'enabled' ];
            // cache the decoded value of APM in SESSION array for the disk table
            $_SESSION[ 'disk_advanced' ][ $query ][ 'apm' ] = $apm_dec;
            // display APM settings only when supported by disk
            $class_apm_enabled = ( $cap[ 'detail' ][ 'advanced power management' ][ 'enabled' ] ===
            'yes' ) ? 'normal' : 'hidden';
            $class_apm_disabled = ( $cap[ 'detail' ][ 'advanced power management' ][ 'enabled' ] !==
            'yes' ) ? 'normal' : 'hidden';
            // table apm_settinglist
            $table_apm_settinglist = [];
            $apm_settings = [
            '255' => 'Disable',
            '1' => '1 (maximum power savings with spindown)',
            '32' => '32 (high power savings with spindown)',
            '64' => '64 (medium power savings with spindown)',
            '96' => '96 (low power savings with spindown)',
            '127' => '127 (lowest power savings with spindown)',
            '128' => '128 (maximum power savings without spindown)',
            '254' => '254 (maximum performance without spindown)'
            ];
            foreach ( $apm_settings as $id => $text ) {
                if ($apm_dec == $id ) {
                    $table_apm_settinglist[] = [
                    'APM_ACTIVE' => 'selected="selected"',
                    'APM_ID' => ( int )$id,
                    'APM_NAME' => htmlentities($text)
                    ];
                } else {
                    $table_apm_settinglist[] = [
                    'APM_ACTIVE' => '',
                    'APM_ID' => ( int )$id,
                    'APM_NAME' => htmlentities($text)
                    ];
                }
            }
        } else {
            $class_apm = 'hidden';
        }

        // information list for queried disk
        $infolist = [];
        if (is_array(@$cap[ 'main' ]) ) {
            foreach ( @$cap[ 'main' ] as $property => $value ) {
                // add 'rpm' suffix to the "media RPM" property value
                if (( $property === 'media RPM' )&&( is_numeric($value) ) ) {
                    $value .= 'rpm';
                }
                // add new row
                $infolist[] = [
                'INFO_PROPERTY' => htmlentities(ucwords($property)),
                'INFO_VALUE' => htmlentities($value)
                ];
            }
        }

        // capability information for queried disk
        $caplist = [];
        if (is_array(@$cap[ 'detail' ]) ) {
            foreach ( @$cap[ 'detail' ] as $feature => $data ) {
                // support
                if ($data[ 'support' ] === 'yes' ) {
                    $support = '';
                    $support_yes = 'normal';
                    $support_no = 'hidden';
                } elseif ($data[ 'support' ] === 'no' ) {
                    $support = '';
                    $support_yes = 'hidden';
                    $support_no = 'normal';
                }
                else {
                    $support = htmlentities($data[ 'support' ]);
                    $support_yes = 'hidden';
                    $support_no = 'hidden';
                }
                // enabled
                if ($data[ 'enabled' ] === 'yes' ) {
                    $enabled = '';
                    $enabled_yes = 'normal';
                    $enabled_no = 'hidden';
                } elseif ($data[ 'enabled' ] === 'no' ) {
                    $enabled = '';
                    $enabled_yes = 'hidden';
                    $enabled_no = 'normal';
                }
                else {
                    $enabled = htmlentities($data[ 'enabled' ]);
                    $enabled_yes = 'hidden';
                    $enabled_no = 'hidden';
                }

                $caplist[] = [
                'CAP_FEATURE' => htmlentities(ucwords($feature)),
                'CAP_SUPPORT' => $support,
                'CAP_SUPPORT_YES' => $support_yes,
                'CAP_SUPPORT_NO' => $support_no,
                'CAP_ENABLED' => $enabled,
                'CAP_ENABLED_YES' => $enabled_yes,
                'CAP_ENABLED_NO' => $enabled_no,
                'CAP_VALUE' => htmlentities($data[ 'value' ]),
                'CAP_VENDOR' => htmlentities($data[ 'vendor' ])
                ];
            }
        }
    }

    // disk power setting table
    $powertable = [];
    foreach ( @$disks as $diskname => $diskdata ) {
        // detect disk type
        $disktype = disk_detect_type($diskname);

        // classes
        $class_activerow = ( $diskname == $query ) ? 'activerow' : 'normal';
        $class_hdd = ( $disktype === 'hdd' ) ? 'normal' : 'hidden';
        $class_ssd = ( $disktype === 'ssd' ) ? 'normal' : 'hidden';
        $class_flash = ( $disktype === 'flash' ) ? 'normal' : 'hidden';
        $class_memdisk = ( $disktype === 'memdisk' ) ? 'normal' : 'hidden';
        $class_usbstick = ( $disktype === 'usbstick' ) ? 'normal' : 'hidden';
        $class_network = ( $disktype === 'network' ) ? 'normal' : 'hidden';

        // spinning status
        $spinning = disk_isspinning($diskname);
        $spinning_text = ( $spinning ) ? 'ready' : 'sleeping';
        $class_spinning_yes = ( $spinning ) ? 'normal' : 'hidden';
        $class_spinning_no = ( $spinning ) ? 'hidden' : 'normal';

        // APM status
        $apm_enabled = @$_SESSION[ 'disk_advanced' ][ $diskname ][ 'apm_enabled' ];
        $apm_setting = @$_SESSION[ 'disk_advanced' ][ $diskname ][ 'apm' ];
        if ($apm_setting == '') {
            if (@isset($_SESSION[ 'disk_advanced' ][ $diskname ]) ) {
                $apm_setting = '<span class="minortext">unsupported</span>';
            } else {
                $apm_setting = '<span class="minortext">unknown</span>';
            }
        } else {
            $apm_setting = '(' . $apm_setting . ')';
        }
        $class_apm_yes = ( $apm_enabled === 'yes' ) ? 'normal' : 'hidden';
        $class_apm_no = ( $apm_enabled === 'no' ) ? 'normal' : 'hidden';

        // TODO: AAM status
        $aam_setting = '<span class="minortext">unknown</span>';

        // add row to array
        $powertable[] = [
        'CLASS_ACTIVEROW' => $class_activerow,
        'CLASS_HDD' => $class_hdd,
        'CLASS_SSD' => $class_ssd,
        'CLASS_FLASH' => $class_flash,
        'CLASS_MEMDISK' => $class_memdisk,
        'CLASS_USBSTICK' => $class_usbstick,
        'CLASS_NETWORK' => $class_network,
        'CLASS_SPINNING_YES' => $class_spinning_yes,
        'CLASS_SPINNING_NO' => $class_spinning_no,
        'CLASS_APM_YES' => $class_apm_yes,
        'CLASS_APM_NO' => $class_apm_no,
        'POWER_DISK' => htmlentities(trim($diskname)),
        'POWER_SPINNING' => $spinning_text,
        'POWER_APM' => $apm_setting,
        'POWER_AAM' => $aam_setting
        ];
    }

    // classes
    $class_query = ( $query ) ? 'normal' : 'hidden';
    $class_noquery = ( !$query ) ? 'normal' : 'hidden';
    $class_details = ( $query AND $cap ) ? 'normal' : 'hidden';
    $class_nodetails = ( $query AND $cap ) ? 'hidden' : 'normal';

    // export new tags
    return @[
    'PAGE_ACTIVETAB' => 'Advanced',
    'PAGE_TITLE' => 'Advanced disk settings',
    'TABLE_POWERLIST' => $powertable,
    'TABLE_QUERY_INFOLIST' => $infolist,
    'TABLE_QUERY_CAPABILITYLIST' => $caplist,
    'TABLE_APM_SETTINGLIST' => $table_apm_settinglist,
    'CLASS_QUERY' => $class_query,
    'CLASS_NOQUERY' => $class_noquery,
    'CLASS_DETAILS' => $class_details,
    'CLASS_NODETAILS' => $class_nodetails,
    'CLASS_APM' => $class_apm,
    'CLASS_APM_ENABLED' => $class_apm_enabled,
    'CLASS_APM_DISABLED' => $class_apm_disabled,
    'APM_CURRENT' => $apm_current,
    'QUERY_DISK' => $query
    ];
}

/**
 * @param $rawapm
 *
 * @return false|float|int
 */
function decode_raw_apmsetting( $rawapm )
{
    if ($rawapm == '') {
        return false;
    }
    if (strncmp($rawapm, '0x', 2) === 0) {
        return @hexdec($rawapm);
    }
    if (( ( $p = strpos($rawapm, '/0x80') ) != false )&&( is_numeric(
        @$rawapm {
        $p + 5
        } 
    ) ) 
    ) {
        return @hexdec(substr($rawapm, strpos($rawapm, '/0x80') + 5));
    }
    if (( strpos($rawapm, '/0x') != false )&&( is_numeric(
        $rawapm {
        0
        } 
    ) ) 
    ) {
        return @hexdec(substr($rawapm, strpos($rawapm, '/0x') + 3));
    }
    // no luck
    return false;
}

function submit_disks_advanced() 
{
    // redirect URL
    $redir = 'disks.php?advanced';

    // required library
    activate_library('disk');

    // scan each POST variable
    foreach ( $_POST as $name => $value ) {
        if (strncmp($name, 'spindown_', 9) === 0) {
            // fetch and sanitize disk
            $disk = substr($name, strlen('spindown_'));
            // TODO - SECURITY - sanitize disk
            // spindown disk
            $result = disk_spindown($disk);
            // provide feedback to user
            if ($result == true ) {
                friendlynotice('spinning down disk <b>' . $disk . '</b>', $redir);
            } else {
                friendlywarning('failed spinning down disk ' . $disk, $redir);
            }
        } elseif (strncmp($name, 'spinup_', 7) === 0) {
            // fetch and sanitize disk
            $disk = substr($name, strlen('spinup_'));
            // TODO - SECURITY - sanitize disk
            // spinup disk
            $result = disk_spinup($disk);
            // provide feedback to user
            if ($result == true ) {
                friendlynotice('disk <b>' . $disk . '</b> is now spinned up again!', $redir);
            } else {
                friendlywarning('failed spinning up disk ' . $disk, $redir);
            }
        }
    }

    // APM setting change
    if (@isset($_POST[ 'apm_submit' ])&&( is_numeric($_POST[ 'apm_newsetting' ]) ) ) {
        if ($_POST['apm_setting_disk'] != '') {
            $redir .= '&query=' . $_POST[ 'apm_setting_disk' ];
        } else {
            error('invalid disk specification for APM setting change!');
        }
        $r = disk_set_apm($_POST[ 'apm_setting_disk' ], ( int )$_POST[ 'apm_newsetting' ]);
        if ($r ) {
            page_feedback(
                'APM setting changed for disk <b>'
                . $_POST[ 'apm_setting_disk' ] . '</b>.', 'b_success' 
            );
        } else {
            friendlyerror(
                'failed changing APM setting for disk <b>'
                . $_POST[ 'apm_setting_disk' ] . '</b>.', $redir 
            );
        }
    }

    // redirect
    redirect_url($redir);
}
