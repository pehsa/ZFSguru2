<?php

function content_services_manage() 
{
    global $categories, $installedservices, $servicedb;

    // required library
    activate_library('gurudb');
    activate_library('service');

    // tabbar
    $tabbar = array(
    '' => 'ZFSguru services',
    'system' => 'System services',
    'install' => 'Install services'
    );
    $url = 'services.php?manage';

    // select tab
    $tab_system = @isset($_GET[ 'system' ]);
    $tab_install = @isset($_GET[ 'install' ]);
    if ($tab_system OR $tab_install ) {
        page_injecttag(
            array(
            'PAGE_TABBAR' => $tabbar,
            'PAGE_TABBAR_URL' => $url
            ) 
        );
        if ($tab_system ) {
            $content = content_handle('services', 'system');
        } else {
            $content = content_handle('services', 'install');
        }
        page_handle($content);
        die();
    }

    // gather data
    $panels = service_panels();
    $installedservices = service_list();
    uksort($installedservices, 'strnatcasecmp');
    $servicedb = gurudb_service();
    $categories = gurudb_category();

    // tables
    timerstart('table_servicepanels', 'content');
    $table_servicepanels = table_servicepanels($panels);
    timerstart('table_servicepanels');
    timerstart('table_servicelist', 'content');
    $table_servicelist = table_servicelist($installedservices, $servicedb, $categories);
    timerend('table_servicelist');

    // hide noservices div when services are present
    $class_services = ( @empty($installedservices) ) ? 'hidden' : 'normal';
    $class_noservices = ( @empty($installedservices) ) ? 'normal' : 'hidden';

    // export new tags
    return array(
    'PAGE_ACTIVETAB' => 'Manage',
    'PAGE_TITLE' => 'Manage',
    'PAGE_TABBAR' => $tabbar,
    'PAGE_TABBAR_URL' => $url,
    'TABLE_SERVICEPANELS' => $table_servicepanels,
    'TABLE_SERVICELIST' => $table_servicelist,
    'CLASS_SERVICES' => $class_services,
    'CLASS_NOSERVICES' => $class_noservices,
    );
}

function table_servicepanels( $panels ) 
{
    // process panels table
    $ptable = array();
    if (@is_array($panels) ) {
        foreach ( $panels as $cat => $data ) {
            $loop = true;
            while ( $loop ) {
                // grab data (3 at a time for a complete row)
                $a1 = @each($data);
                $a2 = @each($data);
                $a3 = @each($data);

                // loop protection
                if (!is_array($a3) ) {
                    $loop = false;
                }

                // hide columns if no data
                $hidden_one = ( is_array($a1) ) ? 'normal' : 'hidden';
                $hidden_two = ( is_array($a2) ) ? 'normal' : 'hidden';
                $hidden_three = ( is_array($a3) ) ? 'normal' : 'hidden';

                // assign panel names
                $one = @$a1[ 'key' ];
                $two = @$a2[ 'key' ];
                $three = @$a3[ 'key' ];
                $onelong = @htmlentities($panels[ $cat ][ $one ][ 'longname' ]);
                $twolong = @htmlentities($panels[ $cat ][ $two ][ 'longname' ]);
                $threelong = @htmlentities($panels[ $cat ][ $three ][ 'longname' ]);

                // add row to table array
                $ptable[] = array(
                'CLASS_HIDDEN_ONE' => $hidden_one,
                'CLASS_HIDDEN_TWO' => $hidden_two,
                'CLASS_HIDDEN_THREE' => $hidden_three,
                'PANEL_ONE' => $one,
                'PANEL_TWO' => $two,
                'PANEL_THREE' => $three,
                'PANEL_ONE_LONG' => $onelong,
                'PANEL_TWO_LONG' => $twolong,
                'PANEL_THREE_LONG' => $threelong
                );
            }
        }
    }
    return $ptable;
}

function table_servicelist( $installedservices, $servicedb, $categories ) 
{
    $table = array();
    foreach ( $installedservices as $service => $data ) {
        $activerow = ( @$_GET[ 'query' ] == $service ) ? 'activerow' : 'normal';
        if ($data[ 'status' ] == 'passive' ) {
            $status = 'PASSIVE';
            $class_status = 'grey';
        } elseif ($data[ 'status' ] == 'running' ) {
            $status = 'RUNNING';
            $class_status = 'green';
        }
        elseif ($data[ 'status' ] == 'stopped' ) {
            $status = 'STOPPED';
            $class_status = 'red';
        }
        else {
            $status = @htmlentities(strtoupper($data[ 'status' ]));
            $class_status = 'blue';
        }

        // icon
        $icon = 'internal.php?serviceicon=' . $service;

        // size
        //  $servicesize = ((int)$data['size'] > 0) ? sizebinary($data['size'], 1) : '-';

        // upgrade status
        timerstart('service_checkupgrade (' . $service . ')', 'content');
        $newerversion = service_checkupgrade($data);
        timerend('service_checkupgrade (' . $service . ')');

        // classes
        $class_upgrade = ( $newerversion ) ? 'normal' : 'hidden';
        $class_noupgrade = ( !$newerversion ) ? 'normal' : 'hidden';
        $class_startbutton = @( ( $data[ 'status' ] != 'running' )AND( $data[ 'status' ] != 'passive' )AND $data[ 'can_start' ] ) ?
        'normal' : 'hidden';
        $class_stopbutton = @( $data[ 'can_stop' ]AND( $data[ 'status' ] == 'running'
        OR $data[ 'status' ] == 'unknown' ) ) ? 'normal' : 'hidden';

        // autostart
        timerstart('service_queryautostart (' . $service . ')', 'content');
        $autostart = service_queryautostart($service);
        timerend('service_queryautostart (' . $service . ')');
        $class_autostart_y = ( $autostart === true ) ? 'normal' : 'hidden';
        $class_autostart_n = ( $autostart === false ) ? 'normal' : 'hidden';
        $class_autostart_p = ( $autostart === null ) ? 'normal' : 'hidden';

        // long name (if unknown use shortname instead)
        $longname = ( @strlen($servicedb[ $service ][ 'longname' ]) > 0 ) ?
        htmlentities($servicedb[ $service ][ 'longname' ]) : $service;

        // long cat (if unknown use shortcat instead)
        $longcat = ( @strlen($categories[ $data[ 'cat' ] ][ 'longname' ]) > 0 ) ?
        htmlentities($categories[ $data[ 'cat' ] ][ 'longname' ]) : $data[ 'cat' ];

        // add row to table
        $table[] = @array(
        'CLASS_ACTIVEROW' => $activerow,
        'SERVICE_NAME' => htmlentities($service),
        'SERVICE_ICON' => $icon,
        'SERVICE_LONGNAME' => $longname,
        'SERVICE_CAT' => $longcat,
        'SERVICE_VER_EXT' => $data[ 'ver_ext' ],
        'SERVICE_VER_PROD' => $data[ 'ver_prod' ],
        //   'SERVICE_SIZE'       => $servicesize,
        'CLASS_STATUS' => $class_status,
        'SERVICE_STATUS' => $status,
        'CLASS_UPGRADE' => $class_upgrade,
        'CLASS_NOUPGRADE' => $class_noupgrade,
        'CLASS_STOPBUTTON' => $class_stopbutton,
        'CLASS_STARTBUTTON' => $class_startbutton,
        'CLASS_AUTOSTART_Y' => $class_autostart_y,
        'CLASS_AUTOSTART_N' => $class_autostart_n,
        'CLASS_AUTOSTART_P' => $class_autostart_p,
        'SERVICE_AUTOSTART' => $autostart
        );
    }
    return $table;
}

function submit_services_manage() 
{
    global $guru;

    // required library
    activate_library('service');

    // redirect url
    $url = 'services.php?manage';

    foreach ( $_POST as $name => $value ) {

        if (substr($name, 0, strlen('svc_start_')) == 'svc_start_' ) {
            // start service
            $svc = trim(substr($name, strlen('svc_start_'), -2));
            $result = service_start_blocking($svc);
            if ($result ) {
                page_feedback('service ' . htmlentities($svc) . ' started!', 'b_success');
                redirect_url($url);
            } else {
                friendlyerror('could not start service ' . htmlentities($svc) . '!', $url);
            }
        }

        if (substr($name, 0, strlen('svc_stop_')) == 'svc_stop_' ) {
            // stop service
            $svc = trim(substr($name, strlen('svc_stop_'), -2));
            $result = service_stop_blocking($svc);
            if ($result ) {
                friendlynotice('service ' . htmlentities($svc) . ' stopped!', $url);
            } else {
                friendlyerror('could not stop service ' . htmlentities($svc) . '!', $url);
            }
        }

        if (substr($name, 0, strlen('svc_autostart_y_')) == 'svc_autostart_y_' ) {
            // autostart
            $svc = trim(substr(substr($name, strlen('svc_autostart_y_')), 0, -2));
            $result = service_autostart($svc, true);
            if (!$result ) {
                friendlyerror('could not automatically start ' . htmlentities($svc), $url);
            }
        }

        if (substr($name, 0, strlen('svc_autostart_n_')) == 'svc_autostart_n_' ) {
            // do not autostart
            $svc = trim(substr(substr($name, strlen('svc_autostart_n_')), 0, -2));
            $result = service_autostart($svc, false);
            if (!$result ) {
                friendlyerror('could not disable autostart of ' . htmlentities($svc), $url);
            }
        }

    }

    redirect_url($url);
}
