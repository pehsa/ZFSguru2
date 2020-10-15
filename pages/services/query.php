<?php

/**
 * @return array
 */
function content_services_query()
{
    global $categories, $installedservices, $servicedb;

    // required libraries
    activate_library('gurudb');
    activate_library('service');

    // inject tags from manage.php
    page_injecttag(content_handle('services', 'manage'));

    // include stylesheet from manage page
    // page_register_stylesheet('pages/services/manage.css');

    // queried service
    $svc = @trim($_GET[ 'query' ]);
    $svclong = ( @$servicedb[ $svc ][ 'longname' ] ) ?
    $servicedb[ $svc ][ 'longname' ] : $svc;

    // redirect on bad service
    if (!@isset($servicedb[ $svc ]) ) {
        friendlyerror(
            'the queried service <b>' . htmlentities($svc)
            . '</b> does not exist!', 'services.php?manage' 
        );
    }

    // system image availability
    $sysimg_str = $installedservices[ $svc ][ 'sysver' ];
    $sysimg_all = @$servicedb[ $svc ][ 'sysimg' ];
    $sysimg = @$sysimg_all[ $sysimg_str ][ $platform ];

    // define service
    $service = @$installedservices[ $svc ];

    // longcat
    $longcat = ( @strlen($categories[ $service[ 'cat' ] ][ 'longname' ]) > 0 ) ?
    htmlentities($categories[ $service[ 'cat' ] ][ 'longname' ]) :
    $service[ 'cat' ];

    // service path
    $dirs = common_dirs();
    $qpath = $dirs[ 'services' ] . '/' . $svc;

    // service panel
    $class_panel = 'hidden';
    if (file_exists($qpath . '/panel/' . $svc . '.php')OR file_exists($qpath . '/panel/' . $svc . '.page') ) {
        $class_panel = 'normal';
    }

    // check for upgrade
    $upgradeavailable = service_checkupgrade($service, $upgradever);
    $class_svcupgrade = ( $upgradeavailable ) ? 'normal' : 'hidden';
    $class_newver = ( $upgradeavailable ) ? 'green' : 'normal';

    // display table of supported system versions in case service is unavailable
    $table_sysverlist = [];
    if (is_array($sysimg_all) ) {
        foreach ( $sysimg_all as $sysver => $platformdata ) {
            if (is_array($platformdata) ) {
                foreach ( $platformdata as $tplatform => $data ) {
                    $ip_compat = 'ok';
                    // check whether service is compatible with web-interface
                    if (@strlen($data[ 'compat' ]) > 0 ) {
                        // TODO - REWRITE
                        //      if (!guru_checkcompatibility((int)$data['compat']))
                        //       $ip_compat = 'no';
                        // check whether system image is compatible with web-interface
                        // TODO - REWRITE
                        //     if (!guru_checkcompatibility((int)$system[$sysver]['platform']['compat']))
                        //      $ip_compat = 'no';
                        $table_sysverlist[] = [
                        'IP_CLASS' => ( $sysver == $sysimg_str ) ? 'activerow' : 'normal',
                        'IP_SVCLONG' => htmlentities($svclong),
                        'IP_VERSION' => @$data[ 'version' ],
                        'IP_COMPAT' => @$ip_compat,
                        'IP_SIZE' => sizebinary(@$data[ 'size' ], 1),
                        'IP_SYSVER' => ( $sysver == $sysimg_str ) ?
                        $sysver . ' (current)' : $sysver,
                        'IP_BRANCH' => @$system[ $sysver ][ $platform ][ 'branch' ],
                        'IP_BSDVERSION' => @$system[ $sysver ][ $platform ][ 'bsdversion' ],
                        'IP_PLATFORM' => $tplatform
                        ];
                    }
                }
            }
        }
    }
    $class_sysvertable = ( !empty($table_sysverlist) ) ? 'normal' : 'hidden';

    // export new tags
    return @[
    'PAGE_ACTIVETAB' => 'Manage',
    'PAGE_TITLE' => 'Manage',
    'TABLE_SYSVERLIST' => $table_sysverlist,
    'CLASS_PANEL' => $class_panel,
    'CLASS_SVCUPGRADE' => $class_svcupgrade,
    'CLASS_SYSVERTABLE' => $class_sysvertable,
    'CLASS_NEWVER' => $class_newver,
    'QSERVICE' => htmlentities($svc),
    'QSERVICE_LONG' => htmlentities($svclong),
    'QSERVICE_PATH' => $qpath,
    'QSERVICE_CAT' => $longcat,
    'QSERVICE_CATSHORT' => $service[ 'cat' ],
    'QSERVICE_VERSION' => $service[ 'version' ],
    'QSERVICE_SERIAL' => $service[ 'serial' ],
    'QSERVICE_NEWVER' => @$upgradever[ 'available' ],
    'QSERVICE_SYSVER' => $service[ 'sysver' ],
    'QSERVICE_PLATFORM' => $service[ 'platform' ],
    'QSERVICE_SIZE' => sizebinary($service[ 'size' ], 1),
    'QSERVICE_LICENSE' => $service[ 'license' ],
    'QSERVICE_SECURITY' => $service[ 'security' ],
    'QSERVICE_DEPEND' => nl2br($service[ 'depend' ]),
    'QSERVICE_CONFLICTS' => $service[ 'conflict' ],
    'QSERVICE_CANSTART' => $service[ 'can_start' ]
    ];
}
