<?php

function activation_serverstatus()
{
    // host
    $host = 'activation.zfsguru.com';

    // url
    $aliveurl = 'http://' . $host . '/zfsguru_alive.txt';

    // return alive status
    return ( stripos(@file_get_contents($aliveurl), 'online') !== false );
}

function activation_submit( $activationType, $earlyFeedback, $feedbackText )
{
    global $guru;

    // retrieve dmesg.boot unless activation type is 2 (no-hw activation)
    if ($activationType !== 2 ) {
        $dmesg = file_get_contents('/var/run/dmesg.boot');
    } else {
        $dmesg = false;
    }
    $dpos = strrpos($dmesg, ' The FreeBSD Project.');
    $dmesg = substr($dmesg, ( int )$dpos);

    // set server host
    $host = 'activation.zfsguru.com';

    // set activation URL
    $url = '/zfsguru_activate.php';
    $aliveurl = '/zfsguru_alive.txt';

    // fetch current system data (sysver + dist)
    $currentver = common_systemversion();

    // construct POST data
    $postdata = @array(
    'ver' => 1,
    'magic' => '__ZFSGURU_ACTIVATION__',
    'dist' => $currentver[ 'dist' ],
    'sysver' => $currentver[ 'sysver' ],
    'webver' => $guru[ 'product_version_string' ],
    'type' => ( int )$activationType,
    'feedback' => $earlyFeedback,
    'feedback_text' => $feedbackText,
    'dmesg' => $dmesg
    );

    // set user agent
    $useragent = 'ZFSguru/' . $guru[ 'product_version_string' ];

    // send data
    $result = activation_post_request($host, $url, $postdata, $useragent);

    // retrieve UUID from header
    $regexp = '/^ZFSguru-UUID: ([a-zA-Z0-9]+)\r?$/m';
    preg_match($regexp, @$result[ 'headers' ], $matches);
    $uuid = ( @$matches[ 1 ] ) ? @$matches[ 1 ] : '';

    // return UUID or return false on failure
    if (@$result[ 'success' ]AND( strlen($uuid) > 0 ) ) {
        // remove late activation data (just in case)
        activate_library('persistent');
        persistent_remove('activation_delayed');
        // store new hardware hash (for hardware change detection)
        activation_hwchange(true);
        // return UUID
        return $uuid;
    } else {
        // display error message if provided by server
        if (@strlen($result[ 'body' ]) > 0 ) {
            page_feedback(
                'could not activate, response by server: '
                . htmlentities($result[ 'body' ]), 'a_error' 
            );
        }
        // store data for late activation
        activate_library('persistent');
        $pstore = array(
        'activation_type' => $activationType,
        'early_feedback' => $earlyFeedback,
        'feedback_text' => $feedbackText
        );
        persistent_store('activation_delayed', $pstore);
        // return failure
        return false;
    }
}

function activation_post_request( $host, $url, $postdata, $useragent = false )
{
    // set variables
    $http_port = 80;
    $http_timeout = 20;
    $http_method = 'POST';
    $http_post_data = '';
    foreach ( $postdata as $name => $value ) {
        $http_post_data .= urlencode($name) . '=' . urlencode($value) . '&';
    }
    $http_content_length = ( int )strlen($http_post_data);

    // open socket
    $fp = fsockopen($host, $http_port, $errno, $errstr, $http_timeout);
    if (( $fp == false )OR( $errno > 0 ) ) {
        return array(
        'success' => false,
        'errno' => $errno,
        'errstr' => $errstr
        );
    } else {
        $success = true;
    }

    // start POST request
    fputs($fp, "$http_method $url HTTP/1.1\r\n");
    fputs($fp, "Host: $host\r\n");
    fputs($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
    fputs($fp, "Content-Length: $http_content_length\r\n");
    if ($useragent ) {
        fputs($fp, "User-Agent: $useragent\r\n");
    }
    fputs($fp, "Connection: close\r\n\r\n");

    // send POST data
    if ($http_content_length > 0 ) {
        fputs($fp, $http_post_data);
    }

    // receive HTTP response
    $buffer = '';
    while ( !feof($fp) ) {
        $buffer .= fgets($fp, 128);
    }

    // close socket
    fclose($fp);

    // assimilate data (resistance is futile)
    $pos = strpos($buffer, "\r\n\r\n");
    $body = '';
    if ($pos == false ) {
        $headerblock = $buffer;
    } else {
        $headerblock = substr($buffer, 0, $pos);
        $body = substr($buffer, $pos + 4);
    }

    // return data array
    return array(
    'success' => $success,
    'headers' => $headerblock,
    'body' => $body
    );
}

function activation_delayed()
{
    global $guru;

    // skip delayed activation when variable set (only execute function once)
    if (@isset($guru[ 'no_delayed_activation' ]) ) {
        return false;
    } else {
        $guru[ 'no_delayed_activation' ] = true;
    }

    // required library
    activate_library('persistent');

    // scan for delayed activation data
    $act = persistent_read('activation_delayed');
    if ($act == false ) {
        return false;
    }

    // add message that we are trying to activate using late activation data
    page_feedback('trying to activate using delayed activation data', 'c_notice');

    // try to activate
    $uuid = activation_submit(
        $act[ 'activation_type' ], $act[ 'early_feedback' ],
        $act[ 'feedback_text' ] 
    );

    // process result
    if (is_string($uuid)AND( strlen($uuid) > 0 ) ) {
        // save preferences so UUID gets permanent
        $guru[ 'preferences' ][ 'uuid' ] = $uuid;
        procedure_writepreferences($guru[ 'preferences' ]);
        // remove persistent storage data
        persistent_remove('activation_delayed');
        // set page feedback message
        page_feedback(
            'your installation has been '
            . '<a href="system.php?activation">activated</a> '
            . 'using delayed activation data!', 'b_success' 
        );
        // return success
        return true;
    } else {
        return false;
    }
}

function activation_hwchange( $storenewhash = false )
{
    // required library
    activate_library('persistent');
    // retrieve dmesg
    $dmesg = file_get_contents('/var/run/dmesg.boot');
    $dpos = strrpos($dmesg, ' The FreeBSD Project.');
    $dmesg = substr($dmesg, ( int )$dpos);
    // search for interface MAC
    $rxp = '/^[a-z]+[0-9]+\: Ethernet address\: (([0-9a-f]{2}\:){5}[0-9a-f]{2})/m';
    preg_match_all($rxp, $dmesg, $mac_matches);
    $mac = array();
    foreach ( $mac_matches[ 1 ] as $id => $macaddr ) {
        if (strlen($macaddr) > 0 ) {
            $mac[] = $macaddr;
        }
    }
    if (empty($mac) ) {
        return false;
    }
    // calculate hardware hash according to MAC addr
    $salt = 'GURUS4lT';
    $hwstring = $salt . @implode($mac);
    $hwhash = md5($hwstring);
    // compare with stored hash and return result
    $currenthash = persistent_read('hardware_hash');
    if (!$currenthash ) {
        persistent_store('hardware_hash', $hwhash);
        return false;
    } elseif (( $currenthash != $hwhash )AND!$storenewhash ) {
        return true;
    } elseif (( $currenthash != $hwhash )AND $storenewhash ) {
        persistent_store('hardware_hash', $hwhash);
        return true;
    }
    else {
        return false;
    }
}

function activation_info()
{
    global $guru;

    // activation UUID
    $uuid = @$guru[ 'preferences' ][ 'uuid' ];
    if (strlen($uuid) < 1 ) {
        return false;
    }

    // set server host
    $host = 'activation.zfsguru.com';

    // set activation info URL
    $url = '/zfsguru_info.php';

    // construct POST data
    $postdata = array(
    'ver' => 1,
    'magic' => '__ZFSGURU_ACTIVATION_INFO__',
    'uuid' => $uuid,
    );

    // set user agent
    $useragent = 'ZFSguru/' . $guru[ 'product_version_string' ];

    // send data
    $result = activation_post_request($host, $url, $postdata, $useragent);

    // process result
    $arr = @unserialize($result[ 'body' ]);

    // return result
    if (is_array($arr) ) {
        return $arr;
    } elseif (@strlen($result[ 'body' ]) > 0 ) {
        page_feedback(
            'could not retrieve activation details, response by server: '
            . htmlentities($result[ 'body' ]), 'a_error' 
        );
    } else {
        return false;
    }
}
