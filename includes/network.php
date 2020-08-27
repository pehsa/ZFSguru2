<?php

function network_interfaces()
{
    // fetch dmesg.boot
    $dmesg = file_get_contents('/var/run/dmesg.boot');
    $dmesg .= chr(10) .shell_exec("dmesg");

    // fetch ifconfig raw output
    exec('/sbin/ifconfig', $ifconfig);

    // first split raw output into chunks (one chunk per interface)
    $chunks = array();
    $ifconfig_str = '';
    if (@is_array($ifconfig) ) {
        foreach ( $ifconfig as $line ) {
            $ifconfig_str .= $line . chr(10);
        }
        $arr = preg_split(
            '/^([a-zA-Z0-9]*): /m', $ifconfig_str,
            null, PREG_SPLIT_NO_EMPTY + PREG_SPLIT_DELIM_CAPTURE 
        );
        // for every even array ID we process two array IDs at once
        foreach ( $arr as $id => $chunk ) {
            if (!( ( int )$id & 1 )AND( strlen($chunk) <= 8 ) ) {
                $if_name = trim($chunk);
                $chunks[ trim($chunk) ] = trim($arr[ ( int )$id + 1 ]);
            }
        }
    }

    // process chunks into detailed arrays
    $detailed = array();
    foreach ( $chunks as $ifname => $ifdata ) {
        // process flags= line
        $preg1 = '/^flags\=([0-9]+)\<([a-zA-Z0-9,_]+)\> metric ([0-9]+) mtu ([0-9]+)/';
        preg_match($preg1, $ifdata, $matches);
        $flags = @$matches[ 1 ];
        $flags_str = @$matches[ 2 ];
        $metric = @$matches[ 3 ];
        $mtu = @$matches[ 4 ];

        // process options= line
        $preg2 = '/^[\s]*options\=([a-f0-9]+)\<([a-zA-Z0-9,_]+)\>/m';
        preg_match($preg2, $ifdata, $matches2);
        $options = @$matches2[ 1 ];
        $options_str = @$matches2[ 2 ];

        // process ether line
        $preg3 = '/^[\s]*ether (([a-f0-9]{2}\:){5}[a-f0-9]{2})/m';
        preg_match($preg3, $ifdata, $matches3);
        $ether = @$matches3[ 1 ];

        // process inet lines
        $preg4 = '/^[\s]*inet (([0-9]{1,3}\.){3}[0-9]{1,3}) '
        . 'netmask (0x[0-9a-f]{8})( broadcast (([0-9]{1,3}\.){3}[0-9]{1,3}))?/m';
        preg_match_all($preg4, $ifdata, $matches4);

        // construct inet array
        $inet = array();
        if (is_array($matches4[ 1 ]) ) {
            foreach ( $matches4[ 1 ] as $id => $ipaddress ) {
                $inet[] = array(
                'ip' => $ipaddress,
                'netmask' => @$matches4[ 3 ][ $id ],
                'broadcast' => @$matches4[ 5 ][ $id ]
                );
            }
        }

        // process inet6 lines
        $preg5 = '/^[\s]*inet6 ([a-z0-9:%]+) prefixlen ([0-9]+)( scopeid (.*))?/m';
        preg_match_all($preg5, $ifdata, $matches5);

        // construct inet6 array
        $inet6 = array();
        if (is_array($matches5[ 1 ]) ) {
            foreach ( $matches5[ 1 ] as $id => $ipaddress ) {
                $inet6[] = array(
                'ip' => $ipaddress,
                'prefixlen' => @$matches5[ 2 ][ $id ],
                'scopeid' => @trim($matches5[ 4 ][ $id ])
                );
            }
        }

        // determine IP address based on either inet or inet6 configuration
        $ip = '';
        foreach ( $inet6 as $id => $inetdata ) {
            if (@strlen($inetdata[ 'ip' ]) > 0 ) {
                $ip = $inetdata[ 'ip' ];
            }
        }
        foreach ( $inet as $id => $inetdata ) {
            if (@strlen($inetdata[ 'ip' ]) > 0 ) {
                $ip = $inetdata[ 'ip' ];
            }
        }

        // process media line
        $preg6 = '/^[\s]*media\: ([^()]+) \(([^)]+)\)/m';
        preg_match($preg6, $ifdata, $matches6);
        $media = @$matches6[ 1 ];
        $linkspeed = @$matches6[ 2 ];
        // TODO: duplex (fetch from linkspeed)
        $duplex = '';

        // process status line
        $preg7 = '/^[\s]*status\: (.*)/m';
        preg_match($preg7, $ifdata, $matches7);
        $status = @$matches7[ 1 ];

        // add dmesg ident string
        $preg8 = '/^' . $ifname . '\: \<(.+)\>/m';
        preg_match($preg8, $dmesg, $matches8);
        $ident = @$matches8[ 1 ];

        // add interface to detailed array
        $detailed[ $ifname ] = array(
        'ifname' => $ifname,
        'ident' => $ident,
        'flags' => $flags,
        'flags_str' => $flags_str,
        'metric' => $metric,
        'mtu' => $mtu,
        'options' => $options,
        'options_str' => $options_str,
        'ether' => $ether,
        'inet' => $inet,
        'inet6' => $inet6,
        'ip' => $ip,
        'media' => $media,
        'linkspeed' => $linkspeed,
        'duplex' => $duplex,
        'status' => $status
        );
    }

    // return detailed array
    return $detailed;
}

function network_checkinterface( $ifname )
{
    if (strpos($ifname, 'lo') === 0) {
        return 'loopback';
    }
    $interfaces = network_interfaces();
    if (stripos(@$interfaces[ $ifname ][ 'media' ], 'Wireless') !== false ) {
        return 'wireless';
    }

    return 'wired';
}

function network_sockstat() 
{
    exec('/usr/bin/sockstat -4l', $output);
    $sockstat = array();
    $desc = array();
    $i = -1;
    if (is_array($output) ) {
        foreach ( $output as $line ) {
            $split = preg_split('/\s/', $line, -1, PREG_SPLIT_NO_EMPTY);
            if ($split[ 0 ] === 'USER' ) {
                foreach ( $split as $id => $description ) {
                    $desc[ $id ] = strtolower($description);
                } 
            } else {
                foreach ( $split as $id => $value ) {
                    if (@isset($desc[ $id ]) ) {
                        $sockstat[ $i ][ $desc[ $id ] ] = trim($value);
                    }
                }
            }
            $i++;
        }
    }
    return $sockstat;
}

function network_firewall_newconfig( $config ) 
{
    // required library
    activate_library('super');

    // strip windows carriage return characters - those are from printer-age ;-)
    $config = str_replace(chr(13), '', $config);

    // create temporary file
    $timestamp = time();
    $cmd = array(
    '/bin/mkdir -p /etc/backup',
    '/usr/bin/touch /etc/pf.conf',
    '/bin/cp -p /etc/pf.conf /etc/backup/pf.conf-' . $timestamp,
    '/bin/rm -f /tmp/zfsguru-pf.conf',
    '/usr/bin/touch /tmp/zfsguru-pf.conf',
    '/usr/sbin/chown 888:888 /tmp/zfsguru-pf.conf',
    '/bin/chmod 644 /tmp/zfsguru-pf.conf',
    );
    foreach ( $cmd as $cmdline ) {
        $r = super_execute($cmdline);
        if ($r[ 'rv' ] != 0 ) {
            error('could not create temporary zfsguru-pf.conf file!');
        }
    }

    // write data to temporary file
    $r = file_put_contents('/tmp/zfsguru-pf.conf', $config);
    if (!$r ) {
        error('could not write configuration to temporary zfsguru-pf.conf file!');
    }

    // rename temporary file and set owner to root
    super_execute('/usr/sbin/chown root:wheel /tmp/zfsguru-pf.conf');
    $r = super_execute('/bin/mv /tmp/zfsguru-pf.conf /etc/pf.conf');
    if ($se[ 'rv' ] != 0 ) {
        if (@file_exists('/etc/backup/pf.conf-' . $timestamp) ) {
            super_execute('/bin/cp -p /etc/backup/pf.conf-' . $timestamp . ' /etc/pf.conf');
        }
        error('could not overwrite pf.conf file!');
    }
}

function network_firewall_activate()
{
    // required library
    activate_library('super');

    // check configuration file for errors
    if (!network_firewall_checkconfig() ) {
        return false;
    }

    // restart pf service
    $r = super_execute('/usr/sbin/service pf onerestart');

    // return boolean
    return ( $r[ 'rv' ] == 0 );
}

function network_firewall_checkconfig( & $errorline = 0 )
{
    // required library
    activate_library('super');

    // check firewall configuration for errors
    $r = super_execute('/usr/sbin/service pf check');
    if ($r[ 'rv' ] == 0 ) {
        return true;
    }
    if (preg_match(
        '~' . preg_quote('/etc/pf.conf:') . '([0-9]+)'
        . preg_quote(': syntax error') . '~', $r[ 'output_str' ], $matches 
    ) 
    ) {
        $errorline = ( int )$matches[ 1 ];
    } else {
        page_feedback(
            'cannot activate pf firewall due to an error:'
            . '<br />' . nl2br($r[ 'output_str' ]), 'a_warning' 
        );
    }
    return false;
}
