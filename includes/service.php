<?php

// TODO: removed size from service_list, make dedicated function for it

/* new service code */

function service_list()
{
    global $guru;

    // check for cached list
    if (@is_array($guru[ 'cache' ][ 'servicelist' ]) ) {
        return $guru[ 'cache' ][ 'servicelist' ];
    }

    // required library
    timerstart('servicelist');
    activate_library('gurudb');

    // initialise service directory
    $dirs = common_dirs();

    // fetch service database
    $servicedb = gurudb_service();

    // assemble and return services array
    $services = array();
    exec('/bin/ls -1 ' . escapeshellarg($dirs[ 'services' ]), $result, $rv);
    if ($rv != 0 ) {
        // no services, return empty array
        return array();
    }
    foreach ( $result as $line ) {
        // service path
        $spath = '/services/' . trim($line);

        // name
        $name = trim($line);
        $longname = ( @$servicedb[ $name ][ 'longname' ] ) ?: htmlentities($name);

        // description
        $desc = @$servicedb[ $name ][ 'desc' ];

        // derive CAT from service database
        $cat = @$servicedb[ $name ][ 'cat' ];
        if ($cat == '') {
            $cat = '???';
        }

        // required: VERSION file
        $version = @trim(file_get_contents($spath . '/VERSION'));
        // ignore service if no VERSION file present
        if (@strlen($version) < 1 ) {
            continue;
        }

        // expected: SERIAL
        $serial = @trim(file_get_contents($spath . '/SERIAL'));

        // expected: SYSVER
        $sysver = @trim(file_get_contents($spath . '/SYSVER'));

        // expected: PLATFORM
        $platform = @trim(file_get_contents($spath . '/PLATFORM'));

        // expected: LICENSE
        $license = @trim(file_get_contents($spath . '/LICENSE'));

        /* optional components */

        // DEPEND
        $depend = @trim(file_get_contents($spath . '/DEPEND'));

        // CONFLICT
        $conflict = @trim(file_get_contents($spath . '/CONFLICT'));

        // AUTOSTART
        // PROCESSNAMES
        // KMOD

        // JAILED
        $jailed = @trim(file_get_contents($spath . '/JAILED')) == '1';

        // CHROOTED
        $chrooted = @trim(file_get_contents($spath . '/CHROOTED')) == '1';

        // security model
        if ($jailed ) {
            $security = 'jail';
        } elseif ($chrooted ) {
            $security = 'chroot';
        } else {
            $security = 'none';
        }

        // can be started
        $canstart = @fileowner($spath . '/service_start.sh') === 0;

        // can be stopped
        $canstop = @fileowner($spath . '/service_stop.sh') === 0;

        // check if passive
        $passive = @file_exists($spath . '/PASSIVE');

        // run status
        $status = service_runstatus($name);

        // size (du -sk reports in units of 1K so multiply 1024)
        // TODO: make separate function for querying size
        //  $size = `/usr/bin/du -Ask ${spath}/data`;
        //  $size = (int)@substr($size, 0, strpos($size, '    '));
        //  $size = $size * 1024;

        // panel path (at least .php file has to exist)
        $panelpath = $spath . '/panel/' . $name;
        if (@is_file($panelpath . '.php') ) {
            $path_panel = $panelpath;
        } else {
            $path_panel = false;
        }

        // add to services array
        $services[ $name ] = array(
        'name' => $name,
        'shortname' => $name,
        'longname' => $longname,
        'cat' => $cat,
        'version' => $version,
        'serial' => $serial,
        'sysver' => $sysver,
        'platform' => $platform,
        'license' => $license,
        //   'desc'    => $desc,
        'depend' => $depend,
        'conflict' => $conflict,
        'jailed' => $jailed,
        'chrooted' => $chrooted,
        'security' => $security,
        'can_start' => $canstart,
        'can_stop' => $canstop,
        'status' => $status,
        //   'size'    => $size,
        'path_panel' => $path_panel
        );
    }

    // cache list in guru variable and return value
    $guru[ 'cache' ][ 'servicelist' ] = $services;
    timerend('servicelist');
    return $services;
}

function service_runstatus( $svc )
{
    // service path
    $spath = '/services/' . trim($svc);
    // check if passive
    if (@file_exists($spath . '/PASSIVE') ) {
        return 'passive';
    }

    // retrieve runstatus by looking at process name in ps auxw output
    $status = 'unknown';
    $processnames = @trim(file_get_contents($spath . '/PROCESSNAMES'));
    if ($processnames !== '') {
        $procarr = explode(chr(10), $processnames);
        if (is_array($procarr) ) {
            foreach ( $procarr as $process ) {
                if ($process != '') {
                    if (service_isprocessrunning($process)AND( $status !== 'partial' ) ) {
                        $status = 'running';
                    } elseif ($status === 'running' ) {
                        $status = 'partial';
                        break;
                    }
                    elseif ($status !== 'partial' ) {
                        $status = 'stopped';
                    }
                }
            }
        }
    }

    // KMOD
    $kmod = @trim(file_get_contents($spath . '/KMOD'));
    if ($kmod !== '') {
        $kmod_arr = array();
        $procarr = explode(chr(10), $kmod);
        if (is_array($procarr) ) {
            foreach ( $procarr as $kernelmodule ) {
                if ($kernelmodule != '') {
                    exec('/sbin/kldstat -n ' . $kernelmodule . '.ko', $output, $rv);
                    $kmodloaded = $rv == 0;
                    if ($kmodloaded AND $status === 'stopped' ) {
                        $status = 'partial';
                    } elseif ($kmodloaded AND $status === 'unknown' ) {
                        $status = 'running';
                    } elseif (!$kmodloaded AND $status === 'unknown' ) {
                        $status = 'stopped';
                    } elseif (!$kmodloaded AND $status === 'running' ) {
                        $status = 'partial';
                    }
                    $kmod_arr[ $kernelmodule ] = $rv == 0;
                }
            }
        }
    }
    return $status;
}

function service_start( $svc, $silent = false )
{
    // check if already running
    $status = service_runstatus($svc);
    if (( $status === 'running' )OR( $status === 'partial' ) ) {
        if ($silent ) {
            return true;
        }

        page_feedback('service <b>' . $svc . '</b> is already started!', 'a_warning');

        return true;
    }
    // start service by script
    $result = service_script($svc, 'start');
    // sleep to allow proper detection on next pageview
    sleep(1);
    // return result
    if ($result ) {
        return true;
    }

    return false;
}

function service_start_blocking( $svc, $timeout_sec = 15, $runagain_sec = 10 )
{
    // tunables (sleep is in microseconds; 100 000 = 0.1sec)
    $sleep = 100000;
    $runagaincycles = ( int )$runagain_sec * 10;
    $count = 0;
    $maxcount = ( int )$timeout_sec * 10;

    // start service if required, wait until the service is started
    $status = '';
    while ( $status !== 'running' ) {
        $status = service_runstatus($svc);
        if ($count > $maxcount) {
            return false;
        }

        if ($status == 'running') {
            return true;
        }
        if ($count++ % $runagaincycles == 0 ) {
            service_start($svc, true);
        }
        usleep($sleep);
    }
}

function service_stop( $svc, $silent = false )
{
    // check if already stopped
    $status = service_runstatus($svc);
    if ($status === 'stopped' ) {
        if ($silent ) {
            return true;
        }

        page_feedback('service <b>' . $svc . '</b> is already stopped!', 'a_warning');

        return true;
    }
    // stop service by script
    $result = service_script($svc, 'stop');
    // sleep to allow proper detection on next pageview
    sleep(2);
    // return result
    if ($result ) {
        return true;
    }

    return false;
}

function service_stop_blocking( $svc, $timeout_sec = 15, $runagain_sec = 6 )
{
    // tunables (sleep is in microseconds; 100 000 = 0.1sec)
    $sleep = 100000;
    $runagaincycles = ( int )$runagain_sec * 10;
    $count = 0;
    $maxcount = ( int )$timeout_sec * 10;

    // start service if required, wait until the service is started
    $status = '';
    while ( $status !== 'stopped' ) {
        $status = service_runstatus($svc);
        if ($count > $maxcount) {
            return false;
        }

        if ($status == 'stopped') {
            return true;
        }
        if ($count++ % $runagaincycles == 0 ) {
            service_stop($svc, true);
        }
        usleep($sleep);
    }
}

function service_autostart( $svc, $autostart = true )
{
    $param = ( $autostart ) ? 'REGISTER' : 'UNREGISTER';
    return service_script($svc, 'register', $param);
}

function service_queryautostart( $svc )
{
    // exec('/services/'.$svc.'/service_register.sh QUERY', $output, $rv);
    // first try some less time consuming checks
    if (!@file_exists('/services/' . $svc . '/service_register.sh') ) {
        return null;
    }
    // elseif (@is_executable('/services/'.$svc.'/service_register.sh'))
    //  exec('/services/'.$svc.'/service_register.sh QUERY', $output, $rv);

    $result = service_script($svc, 'register', 'QUERY', $rv);
    // we expect return value 10 when the service is registered, rv 5 if not
    if ($rv == 10) {
        return true;
    }

    if ($rv == 5) {
        return false;
    } elseif ($rv == 6 OR $rv === null ) {
        return null;
    } else {
        page_feedback(
            'the service_register.sh script returned a strange '
            . 'return value: ' . $rv, 'a_warning'
        );
        return null;
    }
}

function service_purge( $svc )
{
    return service_script($svc, 'purge');
}

function service_download( $svc )
{
    // required library
    activate_library('gurudb');
    activate_library('server');

    // call functions
    $curver = common_systemversion();
    $platform = common_systemplatform();
    $dist = gurudb_distribution($curver[ 'sysver' ], $platform);

    // sanity
    if (@$dist[ $svc ][ 'filesize' ] < 1 ) {
        error('No download available for your system image / platform');
    }

    // download file on background
    $uri = server_uri('service', $dist[ $svc ][ 'filename' ]);
    server_download_bg($uri, $dist[ $svc ][ 'filesize' ], $dist[ $svc ][ 'sha512' ]);

    // sleep for download to be detected
    sleep(1);
}

function service_download_progress( $svc )
{
    // required library
    activate_library('gurudb');
    activate_library('server');

    // call functions
    $dirs = common_dirs();
    $curver = common_systemversion();
    $platform = common_systemplatform();
    $dist = gurudb_distribution($curver[ 'sysver' ], $platform);

    // sanity
    if (!@isset($dist[ $svc ][ 'filename' ]) ) {
        return false;
    }

    // URI and file path
    $uri = server_uri('service', $dist[ $svc ][ 'filename' ]);
    $targetfile = $dirs[ 'download' ] . '/' . $dist[ $svc ][ 'filename' ];

    // retrieve status of background download
    $status = server_download_bg_query(
        $uri, $dist[ $svc ][ 'filesize' ],
        $dist[ $svc ][ 'sha512' ] 
    );

    // return status
    return $status;
}

function service_install( $svc )
{
    global $guru;

    // required libraries
    activate_library('background');
    activate_library('gurudb');
    activate_library('super');

    // call functions
    $dirs = common_dirs();
    $slist = service_list();
    $curver = common_systemversion();
    $platform = common_systemplatform();
    $dist = gurudb_distribution($curver[ 'sysver' ], $platform);

    // directory and file locations
    $filename = $dist[ $svc ][ 'filename' ];
    $filepath = $dirs[ 'download' ] . '/' . $filename;
    $serviceroot = $dirs[ 'services' ] . '/' . $svc;
    $installscript = $serviceroot . '/service_install.sh';
    $registerscript = $serviceroot . '/service_register.sh';

    // sanity checks
    if (@isset($slist[ $svc ]) ) {
        error('cannot install service ' . $svc . ' - service already installed!');
    }
    if (@strlen($dist[ $svc ][ 'filename' ]) < 1 ) {
        error('cannot install service ' . $svc . ' - not available in service database');
    }
    if (( int )@$dist[ $svc ][ 'filesize' ] < 1 ) {
        error('cannot install service ' . $svc . ' - file size is zero');
    }
    if (!@file_exists($filepath) ) {
        error('cannot install service ' . $svc . ' - downloaded file does not exist');
    }
    if (!@is_readable($filepath) ) {
        error('cannot install service ' . $svc . ' - downloaded file is not readable');
    }
    if (filesize($filepath) !== $dist[ $svc ][ 'filesize' ] ) {
        error(
            'downloaded file has incorrect size: ' . filesize($filepath)
            . ', expected: ' . ( int )$dist[ $svc ][ 'filesize' ] 
        );
    }
    if (trim(
        shell_exec(
            '/sbin/sha512 -q '
            . escapeshellarg($filepath) 
        ) 
    ) != $dist[ $svc ][ 'sha512' ] 
    ) {
        error(
            'downloaded file fails SHA512 checksum, '
            . 'it may be corrupted or compromised!' 
        );
    }

    // install service on the background
    $commands = array();

    // create directory for service (as root)
    $commands[ 'mkdir' ] = '/bin/mkdir -p ' . escapeshellarg($serviceroot);

    // extract tarball to services directory (as root)
    $commands[ 'extract' ] = '/usr/bin/tar x -C ' . escapeshellarg($serviceroot)
    . ' -f ' . escapeshellarg($filepath);

    // execute install script and optionally register script when present
    $commands[ 'install' ] = $installscript;
    if (@file_exists($registerscript) ) {
        $commands[ 'register' ] = $registerscript . ' REGISTER';
    }

    // register background job
    $btag = 'service_install_' . $svc;
    background_register(
        $btag, array(
        'commands' => $commands,
        'super' => true,
        'combinedoutput' => true,
        ) 
    );
}

function service_install_progress( $svc )
{
    // required library
    activate_library('background');

    // query background job status
    $btag = 'service_install_' . $svc;
    $query = background_query($btag);

    // return string value
    if (@$query[ 'exists' ] !== true) {
        return 'notrunning';
    }

    if (@$query[ 'running' ] === true) {
        return 'running';
    } else {
        return 'finished';
    }
}

function service_install_postprocess( $svc )
{
    // required libraries
    activate_library('background');
    activate_library('super');

    // query directory locations
    $dirs = common_dirs();

    // query background job
    $btag = 'service_install_' . $svc;
    $query = background_query($btag);
    if (@$query[ 'running' ] === false ) {
        background_remove($btag);
    }

    // check for errors
    // 0 = unused/invalid
    // 1 = general error
    // 2 = success
    // 3 = success, requires reboot before operation
    // 4 = success, requires reboot of webserver
    // 5 = failed, requires internet access
    foreach ( $query[ 'ctag' ] as $name => $data ) {
        if (( $name != 'install' )AND( $data[ 'rv' ] != 0 )) {
            page_feedback(
                'service ' . htmlentities($svc) . ' could not be installed - '
                . htmlentities($name) . '-phase failed with code ' . $data[ 'rv' ], 'a_error'
            );
            page_feedback('output: ' . htmlentities($data[ 'output' ]), 'c_notice');
            return false;
        }

        if ($name == 'install') {
            if ($data['rv'] == 1) {
                page_feedback(
                    'service '.htmlentities($svc).' could not be installed - '
                    .htmlentities($name).'-phase failed with code '.$data['rv'],
                    'a_error'
                );
                page_feedback('output: '.htmlentities($data['output']), 'c_notice');

                return false;
            }

            if ($data[ 'rv' ] == 2) {
                page_feedback(
                    'service <b>' . $svc . '</b> installed and ready for use!',
                    'b_success'
                );
            } elseif ($data[ 'rv' ] == 3 ) {
                page_feedback(
                    'service <b>' . $svc . '</b> installed - '
                    . 'requires a <b>reboot</b> before operation!', 'b_success'
                );
            } elseif ($data[ 'rv' ] == 4 ) {
                // restart webserver on background
                service_restartwebserver();
                // give feedback of this happening
                page_feedback(
                    'service <b>' . $svc . '</b> installed - requires a <b>restart</b> '
                    . 'of the webserver; restarting now!', 'b_success'
                );
            }
            elseif ($data[ 'rv' ] == 5 ) {
                page_feedback(
                    'service <b>' . $svc . '</b> failed to install - '
                    . 'requires internet access during installation!', 'a_failure'
                );
                return false;
            }
            else {
                page_feedback(
                    'service <b>' . $svc . '</b> failed to install - '
                    . 'installation script returned invalid rv; aborting!', 'a_error'
                );
                page_feedback('output: ' . htmlentities($data[ 'output' ]), 'c_notice');
                return false;
            }
        }
    }

    // installation succeeded - remove package directory from service directory
    super_execute(
        '/bin/rm -Rf '
        . escapeshellarg($dirs[ 'services' ] . '/' . $svc . '/install-pkg') 
    );
    return true;
}

function service_uninstall( $svc )
{
    // required library
    activate_library('super');

    // query directory location
    $dirs = common_dirs();

    // stop service
    // TODO: check whether service is actually stopped; what about timeout?
    service_stop_blocking($svc);

    // check if directory exists
    $spath = $dirs[ 'services' ] . '/' . trim($svc);
    if (!is_dir($spath) ) {
        page_feedback(
            'cannot uninstall service <b>' . $svc . '</b> - '
            . 'directory does not exist.', 'a_failure' 
        );
        return false;
    }

    // disable automatic start of this service
    service_autostart($svc, false);

    // call uninstall script
    $result = service_script($svc, 'uninstall');

    // remove VERSION file from directory
    $result2 = super_execute('/bin/rm ' . $spath . '/VERSION');

    return $result and ($result2['rv'] == 0);
}

function service_upgrade( $svc )
{
    global $guru;

    // required libraries
    activate_library('gurudb');
    activate_library('super');

    // call functions
    $dirs = common_dirs();
    $slist = service_list();
    $curver = common_systemversion();
    $platform = common_systemplatform();
    $dist = gurudb_distribution($curver[ 'sysver' ], $platform);

    // directory and file locations
    $filename = $dist[ $svc ][ 'filename' ];
    $filepath = $dirs[ 'download' ] . '/' . $filename;
    $serviceroot = $dirs[ 'services' ] . '/' . $svc;
    $installscript = $serviceroot . '/service_install.sh';
    $registerscript = $serviceroot . '/service_register.sh';

    // sanity checks
    if (@!isset($slist[ $svc ]) ) {
        error('cannot upgrade service ' . $svc . ' - service not installed!');
    }
    if (@strlen($dist[ $svc ][ 'filename' ]) < 1 ) {
        error('cannot upgrade service ' . $svc . ' - not available in service database');
    }
    if (( int )@$dist[ $svc ][ 'filesize' ] < 1 ) {
        error('cannot upgrade service ' . $svc . ' - file size is zero');
    }
    if (!@file_exists($filepath) ) {
        error('cannot upgrade service ' . $svc . ' - downloaded file does not exist');
    }
    if (!@is_readable($filepath) ) {
        error('cannot uprade service ' . $svc . ' - downloaded file is not readable');
    }
    if (filesize($filepath) !== $dist[ $svc ][ 'filesize' ] ) {
        error(
            'downloaded file has incorrect size: ' . filesize($filepath)
            . ', expected: ' . ( int )$dist[ $svc ][ 'filesize' ] 
        );
    }
    if (shell_execute(
        '/sbin/sha512 -q '
        . escapeshellarg($filepath) 
    ) != $dist[ $svc ][ 'sha512' ] 
    ) {
        error(
            'downloaded file fails SHA512 checksum, '
            . 'it may be corrupted or compromised!' 
        );
    }

    // TODO - BELOW - TODO
    error('upgrading services is disabled in this version of ZFSguru');

    // create directory for service (as root) (not really needed for upgrade)
    $sroot = $dirs[ 'services' ] . '/' . $svc;
    super_execute('/bin/mkdir -p ' . $sroot);

    // execute uninstall script (to facilitate upgrade of FreeBSD packages)
    $uninstallscript = $sroot . '/service_uninstall.sh';
    clearstatcache();
    if (@file_exists($uninstallscript) ) {
        super_execute($uninstallscript);
    }

    // remove scripts before extracting new version
    super_execute('/bin/rm -f ' . $sroot . '/service_*');

    // extract tarball to services directory (as root)
    $result = super_execute('/usr/bin/tar x -C ' . $sroot . '/ -f ' . $loc);

    // notify user of result
    if ($result[ 'rv' ] != 0 ) {
        error('could not extract to ' . $sroot . '!', 'a_failure');
    }

    // run install script of new version
    $installscript = $sroot . '/service_install.sh';
    clearstatcache();
    if (!@file_exists($installscript) ) {
        page_feedback(
            'no installation script found when upgrading service',
            'a_warning' 
        );
    } else {
        $result2 = super_execute($installscript);

        // notify of result
        if ($result2[ 'rv' ] == 1 ) {
            page_feedback(
                'installation script failed for service '
                . '<b>' . $svc . '</b>', 'a_failure' 
            );
        } elseif ($result2[ 'rv' ] == 2 ) {
            page_feedback('service <b>' . $svc . '</b> upgraded!', 'b_success');
        } elseif ($result2[ 'rv' ] == 3 ) {
            page_feedback(
                'service <b>' . $svc . '</b> upgraded - '
                . 'requires a <u>reboot</u> before operation!', 'b_success' 
            );
        } elseif ($result2[ 'rv' ] == 4 ) {
            // restart webserver on background
            service_restartwebserver();
            // give feedback of this happening
            page_feedback(
                'service <b>' . $svc . '</b> installed - requires a <b>restart</b>'
                . ' of the webserver; restarting now!', 'b_success' 
            );
        }
        else {
            page_feedback(
                'installation script invalid rv! '
                . 'Perhaps your web-interface is outdated, or file-permissions are wrong. '
                . 'Aborting!', 'a_failure' 
            );
        }
    }
}

function service_script( $svc, $script, $arg = '', & $rv = false )
{
    global $guru;

    // required libraries
    activate_library('super');

    // destroy cache since runstatus might change
    if (@isset($guru[ 'cache' ][ 'servicelist' ]) ) {
        unset($guru[ 'cache' ][ 'servicelist' ]);
    }

    // path to service script
    $script = '/services/' . $svc . '/service_' . $script . '.sh';
    if (!file_exists($script) ) {
        return false;
    }
    if ($arg != '' ) {
        $result = super_execute('/bin/sh -c "' . $script . ' ' . $arg . '"');
    } else {
        $result = super_execute('/bin/sh -c "' . $script . '"');
    }
    $rv = $result[ 'rv' ];

    return $rv == 0;
}

/* service panels */

function service_panels()
{
    // grab services list
    $services = service_list();

    // traverse services for panels (require .php file)
    $panels = array();
    foreach ( $services as $servicename => $data ) {
        if (@is_readable($data[ 'path_panel' ] . '.php') ) {
            $panels[ $data[ 'cat' ] ][ $servicename ] = $data;
        }
    }
    return $panels;
}

function service_panel_handle( $svc ) 
{
    global $tabs;

    // grab services list
    $services = service_list();
    // determine panel path
    $panelpath = @$services[ $svc ][ 'path_panel' ];
    // determine longname
    $longname = @$services[ $svc ][ 'longname' ];
    if ($longname == '') {
        $longname = htmlentities($svc);
    }
    // process panel path
    if (@is_file($panelpath . '.php')) {
        // create new tab for panel
        $tabs[ $longname ] = 'services.php?panel=' . $svc;
        // activate the new tab
        page_injecttag(array( 'PAGE_ACTIVETAB' => $longname ));
        // process panel
        $svcalpha = preg_replace('/[^a-zA-Z0-9]/', '_', $svc);
        $content = content_handle_path($panelpath, 'panel', $svcalpha);
        // page handle
        page_handle($content);
        die();
    }

    if ($panelpath == false) {
        error('Service ' . $svc . ' does not have a panel file!');
    } else {
        error('Panel file does not exist at: ' . $panelpath);
    }
    // unhandled termination
    error('unhandled termination of panel ' . $svc);
}

function service_checkupgrade( $service, & $versions = false )
{
    // required library
    activate_library('gurudb');

    // accept $service as array or as string
    if (!is_array($service) ) {
        if (!is_string($service) ) {
            error('invalid service name; cannot check for upgrade');
        } else {
            // string value; fetch service data
            activate_library('service');
            $servicelist = service_list();
            if (!@isset($servicelist[ $service ]) ) {
                if ($enable_feedback ) {
                    page_feedback('service does not exist; cannot check for upgrade!');
                }
                return false;
            }

            $service = @$servicelist[ $service ];
        }
    }

    // fetch data
    $dist = gurudb_distribution($service[ 'sysver' ], $service[ 'platform' ]);

    // store version data and return boolean
    $versions = array(
    'installed' => $service[ 'serial' ],
    'available' => ( int )@$data[ $service[ 'name' ] ][ 'serial' ],
    );
    return ( $versions[ 'installed' ] < $versions[ 'available' ] );
}


/* old service code */

function service_isprocessrunning( $process_name )
{
    if (!$process_name ) {
        return null;
    }
    $cmd = '/bin/ps auxwww | /usr/bin/grep "' . $process_name
    . '" | /usr/bin/grep -v grep';
    $process = trim(shell_exec("\$cmd"));

    return @strlen($process) > 0;
}


/* service manage */

function service_manage_rc( $service, $action, $ignore_errors = false )
{
    global $guru;

    // elevated privileges
    activate_library('super');

    if (@isset($guru[ 'rc.d' ][ $service ]) ) {
        $result = super_execute($guru[ 'rc.d' ][ $service ] . ' ' . $action . ' 2>&1');
    } else {
        return false;
    }

    if (( $result[ 'rv' ] != 0 )AND( !$ignore_errors ) ) {
        error(
            'Got return value ' . ( int )$result[ 'rv' ] . ' when trying to ' . $action
            . ' service "' . $service . '" with output:<br />' . $result[ 'output_str' ] 
        );
    } else {
        return true;
    }
}

function service_start_rc( $service )
{
    global $guru;
    if (service_runcontrol_isenabled($guru[ 'runcontrol' ][ $service ]) ) {
        $result = service_manage($service, 'start');
    } else {
        $result = service_manage($service, 'onestart');
    }
    return $result;
}

function service_restart_rc( $service )
{
    return service_manage($service, 'restart');
}

function service_restartwebserver()
{
    global $guru;
    // required libraries
    activate_library('super');
    activate_library('internalservice');
    // fetch internal services
    $iservices = internalservice_fetch();
    // special script execution on background
    $script = @$iservices[ 'webserver' ][ 'bg_script' ];
    if (($script == '')OR( !@file_exists($script) ) ) {
        page_feedback(
            'could not restart webserver - please do this manually on '
            . 'the <a href="services.php?internal">Services->Internal</a> page',
            'a_warning' 
        );
    } else {
        super_execute($script . ' restart > /dev/null &');
    }
}

function service_stop_rc( $service )
{
    global $guru;
    if (service_runcontrol_isenabled($guru[ 'runcontrol' ][ $service ]) ) {
        $result = service_manage($service, 'stop');
    } else {
        $result = service_manage($service, 'onestop');
    }
    return $result;
}


/* run control */

function service_runcontrol_isenabled( $rc, $rcfile = false )
{
    // fetch run control configuration
    $preg = '/^[\s]*' . $rc . '\_enable[\s]*\="?([^\"]*)"?([\s]*)(\#.*)?$/m';
    if ($rcfile ) {
        $rcconf = file_get_contents($rcfile);
    } else {
        $rcconf = file_get_contents('/etc/rc.conf');
    }

    // look for non-commented out $rc line
    if (preg_match($preg, $rcconf, $matches) ) {
        if (@strtoupper($matches[ 1 ]) == 'YES') {
            return true;
        }

        if (@strtoupper($matches[ 1 ]) == 'NO') {
            return false;
        } else {
            return ( string )@$matches[ 1 ];
        }
    } elseif (!$rcfile ) {
        $rcdefaults = file_get_contents('/etc/defaults/rc.conf');
        unset($matches);
        if (preg_match($preg, $rcdefaults, $matches) ) {
            if (@strtoupper($matches[ 1 ]) == 'YES') {
                return true;
            }

            if (@strtoupper($matches[ 1 ]) == 'NO') {
                return false;
            } else {
                return ( string )@$matches[ 1 ];
            }
        } else {
            return false;
        }
    }
    else {
        // $rcfile specified but nonexistent in that file
        return false;
    }
}

function service_runcontrol_enable( $rc )
{
    global $guru;

    // elevated privileges
    activate_library('super');

    // regexp
    $preg1 = '/^[\s]*' . $rc . '\_enable[\s]*\="?([^\"]*)"?([\s]*)(\#.*)?$/m';
    $preg2 = '/^[\s]*\#' . $rc . '\_enable[\s]*\="?([^\"]*)"?([\s]*)(\#.*)?$/m';
    // read configuration
    $rcconf = file_get_contents('/etc/rc.conf');
    // look for non-commented out $rc line (already enabled)
    $enabled = service_runcontrol_isenabled($rc);
    // determine what should be done
    if ($enabled === true) {
        return true;
    }

    if (preg_match($preg1, $rcconf)) {
        // appears the rc variable exists but has other value than YES/NO
        $rcconf = preg_replace($preg1, $rc . '_enable="YES"$2$3', $rcconf);
    } elseif (preg_match($preg2, $rcconf) ) {
        // replace commented out line with non-commented version
        $rcconf = preg_replace($preg2, $rc . '_enable="YES"$2$3', $rcconf);
    } else {
        // append rc variable to end of file
        $rcconf .= chr(10) . '# added by ZFSguru web-interface' . chr(10)
        . $rc . '_enable="YES"';
    }
    // save to disk
    file_put_contents($guru[ 'tempdir' ] . '/newrc.conf', $rcconf);
    super_execute('/bin/mv ' . $guru[ 'tempdir' ] . '/newrc.conf /etc/rc.conf');
    super_execute('/usr/sbin/chown root:wheel /etc/rc.conf');
    super_execute('/bin/chmod 644 /etc/rc.conf');
}

function service_runcontrol_disable( $rc )
{
    global $guru;

    // elevated privileges
    activate_library('super');

    // read configuration
    $rcconf = file_get_contents('/etc/rc.conf');
    $rcconf_orig = $rcconf;
    $preg = '/^[\s]*' . $rc . '\_enable[\s]*\="?([^\"]*)"?([\s]*)(\#.*)?$/m';
    // replace active rc variable
    $count = 0;
    $rcconf = preg_replace($preg, $rc . '_enable="NO"$2$3', $rcconf, -1, $count);
    // add segment if no matches were found
    if ($count < 1 ) {
        $rcconf .= chr(10) . '# added by ZFSguru web-interface' . chr(10)
        . $rc . '_enable="NO"';
    }
    // save to disk
    file_put_contents($guru[ 'tempdir' ] . '/newrc.conf', $rcconf);
    super_execute('/bin/mv ' . $guru[ 'tempdir' ] . '/newrc.conf /etc/rc.conf');
    super_execute('/usr/sbin/chown root:wheel /etc/rc.conf');
    super_execute('/bin/chmod 644 /etc/rc.conf');
}
