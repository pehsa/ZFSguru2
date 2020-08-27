<?php

function content_services_install() 
{
    global $guru;

    // required libraries
    activate_library('gurudb');
    activate_library('service');

    // call functions
    $services = gurudb_service();
    $categories = gurudb_category();
    $dirs = common_dirs();

    // hide all classes at first
    $class_cat = 'hidden';
    $class_services = 'hidden';
    $class_infopage = 'hidden';

    // navigation
    $nav_catshort = trim(@$_GET[ 'cat' ]);
    $nav_catlong = @$categories[ $nav_catshort ][ 'longname' ];
    $nav_svcshort = trim(@$_GET[ 'service' ]);
    $nav_svclong = @$services[ $nav_svcshort ][ 'longname' ];
    $class_hascat = ( $nav_catshort ) ? 'normal' : 'hidden';
    $class_hassvc = ( $nav_svcshort ) ? 'normal' : 'hidden';

    // tables
    $table_categories = array();
    $table_services = array();
    $table_infopage = array();

    // check which table to display (1: categories, 2: services, 3: infopage)
    if (@isset($_GET[ 'service' ]) ) {
        // infopage
        $class_infopage = 'normal';

        // set category navigation
        $svc = trim($_GET[ 'service' ]);
        $nav_catshort = @$services[ $svc ][ 'cat' ];
        $nav_catlong = @$categories[ $nav_catshort ][ 'longname' ];
        $class_hascat = ( $nav_catshort ) ? 'normal' : 'hidden';

        // infopage description
        $infopage_notes = @$services[ $svc ][ 'notes' ];

        // call functions
        $slist = service_list();
        $upgradeavailable = ( @isset($slist[ $svc ]) ) ?
        service_checkupgrade($slist[ $svc ], $upgradever) : false;
        $curver = common_systemversion();
        $platform = common_systemplatform();
        $system = gurudb_system();
        $dist = gurudb_distribution($curver[ 'sysver' ], $platform);

        // system image availability
        $sysimg_str = ( @isset($slist[ $svc ][ 'sysver' ]) ) ?
        $slist[ $svc ][ 'sysver' ] : @$curver[ 'sysver' ];
        $avail = strlen(@$dist[ $svc ][ 'version' ]) > 0;
        $infopage_downsize = sizebinary(@$dist[ $svc ][ 'filesize' ], 1);

        // download in progress (DIP) - boolean
        $dip_pct = service_download_progress($svc);
        $dip = is_int($dip_pct);

        // install in progress (IIP) - string: notrunning, running, finished
        $iip_progress = service_install_progress($svc);
        if ($iip_progress === 'finished' ) {
            // process post installing tasks
            service_install_postprocess($svc);
            // redirect back to normal URL (without &installing suffix)
            redirect_url('services.php?install&service=' . $svc);
        }
        $iip = $iip_progress == 'running';

        // page refresh
        $page_refresh = 2;
        if ($dip OR $iip ) {
            page_refreshinterval($page_refresh);
        }

        // current installation status
        $installed = ( @isset($slist[ $svc ])AND!$iip );

        // show warning when service installed but not available
        if ($installed AND!$avail ) {
            page_feedback(
                'service is installed but database says this service '
                . 'is not available for your system version!', 'a_warning' 
            );
        }

        // check file availability
        $filepath = $dirs[ 'download' ] . '/' . @$dist[ $svc ][ 'filename' ];
        $fileavail = ( @is_file($filepath)AND @is_readable($filepath) );

        // check dependencies
        $requiresdep = false;
        $deplist = '';
        if ($fileavail AND!$installed ) {
            // read DEPEND file inside tarball
            exec('/usr/bin/tar xf ' . $filepath . ' -O DEPEND', $output, $rv);
            // call function to check dependencies
            $checkdeps = serviceinstall_checkdependencies($output, $slist, $services);
            $requiresdep = $checkdeps[ 'requiresdep' ];
            $deplist = $checkdeps[ 'deplist' ];
        }

        // check dependencies of already installed service
        if ($installed ) {
            $dependfile_path = $dirs[ 'services' ] . '/' . $svc . '/DEPEND';
            if (@is_readable($dependfile_path) ) {
                $dependfile = @file_get_contents($dependfile_path);
                // call function to check dependencies
                $checkdeps = serviceinstall_checkdependencies($dependfile, $slist, $services);
                $requiresdep = $checkdeps[ 'requiresdep' ];
                $deplist = $checkdeps[ 'deplist' ];
            }
        }

        // check compatibility with ZFSguru web-interface
        $compatible = true;
        // TODO - REWRITE
        //  if (@is_numeric($dist[$svc]['compat']))
        //   $compatible = guru_checkcompatibility($dist[$svc]['compat']);

        // classes
        $ip_class_installed = ( $installed ) ? 'normal' : 'hidden';
        $ip_class_notinstalled = ( !$installed ) ? 'normal' : 'hidden';
        $ip_class_installedunavail = ( $installed AND!$avail ) ? 'normal' : 'hidden';
        $ip_class_downloading = ( $dip ) ? 'normal' : 'hidden';
        $ip_class_installing = ( $iip ) ? 'normal' : 'hidden';
        $ip_class_notinstalled1 = ( !$installed AND!$dip AND!$iip AND!$fileavail AND $avail ) ? 'normal' : 'hidden';
        $ip_class_notinstalled2 = ( !$installed AND!$dip AND!$iip AND $fileavail AND $avail AND $requiresdep ) ? 'normal' : 'hidden';
        $ip_class_notinstalled3 = ( !$installed AND!$dip AND!$iip AND $fileavail AND $avail AND!$requiresdep ) ? 'normal' : 'hidden';
        $ip_class_notinstalledunavail = ( !$installed AND!$avail ) ?
        'normal' : 'hidden';
        $ip_class_needdepend_already_installed = ( $requiresdep AND $installed ) ?
        'normal' : 'hidden';
        $ip_class_notcompatible = ( !$compatible ) ? 'normal' : 'hidden';
        $ip_class_sysvertable = ( !$compatible OR( !$installed AND!$avail ) ) ?
        'normal' : 'hidden';
        $ip_class_upgrade1 = 'hidden';
        $ip_class_upgrade2 = 'hidden';
        if (@$upgradeavailable AND!$dip AND!$iip ) {
            if ($fileavail ) {
                $ip_class_upgrade2 = 'normal';
            } else {
                $ip_class_upgrade1 = 'normal';
            }
        }

        // availability chart
        $ac_sys = '';
        $ac_version = '';
        $ac_size = '';
        $ac_quality = '';
        foreach ( $system as $sysname => $sys ) {
            $sdist = gurudb_distribution($sysname, $platform);
            $ac_sys .= '<td class="bold">' . htmlentities($sysname) . '</td>';
            if (( int )@$sdist[ $svc ][ 'filesize' ] > 0 ) {
                $ac_version .= '<td>' . htmlentities($sdist[ $svc ][ 'version' ]) . '</td>';
                $ac_size .= '<td>' . sizebinary(( int )$sdist[ $svc ][ 'filesize' ], 1) . '</td>';
                $ac_quality .= '<td>' . str_repeat('&#9733;', ( int )$sdist[ $svc ][ 'quality' ]) . '</td>';
            } else {
                $ac_version .= '<td>-</td>';
                $ac_size .= '<td>-</td>';
                $ac_quality .= '<td>-</td>';
            }
        }
        $availabilitychart = '<tr><th class="bold">System image</th>' . $ac_sys . '</tr>'
        . '<tr><th class="bold">Product version</th>' . $ac_version . '</tr>'
        . '<tr><th class="bold">Size</th>' . $ac_size . '</tr>'
        . '<tr><th class="bold">Quality</th>' . $ac_quality . '</tr>';
    } elseif (@isset($_GET[ 'cat' ]) ) {
        // list services inside category
        $class_services = 'normal';

        // variables
        $cat = trim($_GET[ 'cat' ]);
        $catlongname = htmlentities(@$categories[ $cat ][ 'longname' ]);

        // handle services table
        foreach ( @$services as $service ) {
            if ($service[ 'cat' ] == $cat ) {
                $table_services[] = @array(
                'SVC_SHORTNAME' => htmlentities($service[ 'shortname' ]),
                'SVC_LONGNAME' => ( @$service[ 'longname' ] ) ?
                $service[ 'longname' ] : $service[ 'shortname' ],
                'SVC_DESCRIPTION' => htmlentities($service[ 'desc' ])
                );
            }
        }
    }
    else {
        // categories list
        $class_cat = 'normal';

        // handle category table
        foreach ( @$categories as $cat ) {
            if ((strlen($cat['shortname']) > 0) && $cat['shortname'] != 'restricted') {
                // determine servicecount
                $servicecount = 0;
                foreach ( $services as $servicename => $servicedata ) {
                    if ($servicedata[ 'cat' ] == $cat[ 'shortname' ] ) {
                        $servicecount++;
                    }
                }
                $table_categories[] = @array(
                'CAT_SHORTNAME' => $cat[ 'shortname' ],
                'CAT_LONGNAME' => $cat[ 'longname' ],
                'CAT_SERVICECOUNT' => $servicecount,
                'CAT_DESCRIPTION' => $cat[ 'desc' ]
                );
            }
        }
    }

    // export new tags
    return @array(
    'PAGE_ACTIVETAB' => 'Manage',
    'PAGE_TITLE' => 'Install services',
    'TABLE_CATEGORIES' => $table_categories,
    'TABLE_SERVICES' => $table_services,
    'TABLE_INFOPAGE' => $table_infopage,
    'NAV_CATSHORT' => $nav_catshort,
    'NAV_CATLONG' => $nav_catlong,
    'NAV_SVCSHORT' => $nav_svcshort,
    'NAV_SVCLONG' => $nav_svclong,
    'CLASS_NAV_HASCAT' => $class_hascat,
    'CLASS_NAV_HASSVC' => $class_hassvc,
    'CLASS_CATEGORIES' => $class_cat,
    'CLASS_SERVICES' => $class_services,
    'CLASS_INFOPAGE' => $class_infopage,
    'INFOPAGE_NOTES' => $infopage_notes,
    'INFOPAGE_DOWNSIZE' => $infopage_downsize,
    'INFOPAGE_DOWNPCT' => $dip_pct,
    'INFOPAGE_SYSVER' => $sysimg_str,
    'INFOPAGE_PLATFORM' => $platform,
    'INFOPAGE_DEPLIST' => $deplist,
    'INFOPAGE_AVAILCHART' => $availabilitychart,
    'CLASS_INSTALLED' => $ip_class_installed,
    'CLASS_NOTINSTALLED' => $ip_class_notinstalled,
    'CLASS_INSTALLEDUNAVAIL' => $ip_class_installedunavail,
    'CLASS_DOWNLOADING' => $ip_class_downloading,
    'CLASS_INSTALLING' => $ip_class_installing,
    'CLASS_NOTINSTALLED1' => $ip_class_notinstalled1,
    'CLASS_NOTINSTALLED2' => $ip_class_notinstalled2,
    'CLASS_NOTINSTALLED3' => $ip_class_notinstalled3,
    'CLASS_NOTINSTALLEDUNAVAIL' => $ip_class_notinstalledunavail,
    'CLASS_NEEDDEPENDINS' => $ip_class_needdepend_already_installed,
    'CLASS_NOTCOMPATIBLE' => $ip_class_notcompatible,
    'CLASS_SYSVERTABLE' => $ip_class_sysvertable,
    'CLASS_UPGRADE1' => $ip_class_upgrade1,
    'CLASS_UPGRADE2' => $ip_class_upgrade2,
    'UPGRADE_VER' => @$upgradever[ 'available' ],
    );
}

function serviceinstall_checkdependencies( $depfile_contents, $slist, $services )
{
    if (is_string($depfile_contents) ) {
        $depfile_contents = explode(chr(10), $depfile_contents);
    }

    // default
    $requiresdep = false;
    $deplist = '';

    // traverse each line of DEPEND file
    foreach ( $depfile_contents as $line ) {
        if (@strlen(trim($line)) < 1 ) {
            continue;
        }
        // $line can be name of service dependency, but also a list of services
        // where only one is necessary, like: X-server|X-server-KMS
        if (strpos($line, '|') === false ) {
            $depservice = trim($line);
            $deplong = ( @$services[ $depservice ][ 'longname' ] ) ?
            $services[ $depservice ][ 'longname' ] : $depservice;
            if (!@isset($slist[ $depservice ]) ) {
                $requiresdep = true;
                $deplist .= '<p><a onclick="window.open(this.href,\'_blank\');'
                . 'return false;" href="services.php?install&service='
                . htmlentities($depservice) . '">' . htmlentities($deplong) . '</a></p>' . chr(10);
            }
        } else {
            $depservices = explode('|', trim($line));
            $atleastone = false;
            foreach ( $depservices as $depservice ) {
                if (@isset($slist[ $depservice ]) ) {
                    $atleastone = true;
                }
            }
            if (!$atleastone ) {
                $requiresdep = true;
                $atleastone_deplist = array();
                foreach ( $depservices as $depservice ) {
                    $deplong = ( @$services[ $depservice ][ 'longname' ] ) ?
                    $services[ $depservice ][ 'longname' ] : $depservice;
                    $atleastone_deplist[] = '<a onclick="window.open(this.href,\'_blank\');'
                    . 'return false;" href="services.php?install&service='
                    . htmlentities($depservice) . '">' . htmlentities($deplong) . '</a> ';
                }
                $deplist .= '<p>' . implode(' or ', $atleastone_deplist) . '</p>' . chr(10);
            }
        }
    }
    return array(
    'requiresdep' => $requiresdep,
    'deplist' => $deplist,
    );
}

function submit_services_infopage() 
{
    global $guru;

    // required library
    activate_library('service');

    // redirect url
    $url1 = 'services.php?install';

    // service shortname
    $s = sanitize(trim(@$_POST[ 'service_name' ]), null, $svc, 32);
    if (!$s ) {
        friendlyerror('invalid service name', $url1);
    }
    $url2 = $url1 . '&service=' . $svc;

    // scan POST variables
    foreach ( $_POST as $name => $value ) {
        if ($name === 'download_svc' ) {
            service_download($svc, $url2);
        } elseif ($name === 'install_svc' ) {
            service_install($svc);
            redirect_url($url2 . '&installing');
        }
        elseif ($name === 'upgrade_svc' ) {
            service_upgrade($svc);
        } elseif ($name === 'uninstall_svc' ) {
            service_uninstall($svc);
        }
    }

    // redirect
    redirect_url($url2);
}
