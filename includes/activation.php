<?php

/**
 * @return bool
 */
function activation_serverstatus()
{
    // host
    $host = 'activation.zfsguru.com';

    // url
    $aliveurl = 'http://' . $host . '/zfsguru_alive.txt';

    // return alive status
    return ( stripos(@file_get_contents($aliveurl), 'online') !== false );
}

/**
 * @param $activationType
 * @param $earlyFeedback
 * @param $feedbackText
 *
 * @return false|mixed|string
 */
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

    // fetch current system data (sysver + dist)
    $currentver = common_systemversion();

    // construct POST data
    $postdata = @[
    'ver' => 1,
    'magic' => '__ZFSGURU_ACTIVATION__',
    'dist' => $currentver[ 'dist' ],
    'sysver' => $currentver[ 'sysver' ],
    'webver' => $guru[ 'product_version_string' ],
    'type' => ( int )$activationType,
    'feedback' => $earlyFeedback,
    'feedback_text' => $feedbackText,
    'dmesg' => $dmesg
    ];

    // set user agent
    $useragent = 'ZFSguru/' . $guru[ 'product_version_string' ];

    // send data
    $result = activation_post_request($host, $url, $postdata, $useragent);

    // retrieve UUID from header
    $regexp = '/^ZFSguru-UUID: ([a-zA-Z0-9]+)\r?$/m';
    preg_match($regexp, @$result[ 'headers' ], $matches);
    $uuid = ( @$matches[ 1 ] ) ?: '';

    // return UUID or return false on failure
    if (@$result[ 'success' ]&&($uuid != '') ) {
        // remove late activation data (just in case)
        activate_library('persistent');
        persistent_remove('activation_delayed');
        // store new hardware hash (for hardware change detection)
        activation_hwchange(true);
        // return UUID
        return $uuid;
    }

    // display error message if provided by server
    if (@strlen($result[ 'body' ]) > 0 ) {
        page_feedback(
            'could not activate, response by server: '
            . htmlentities($result[ 'body' ]), 'a_error'
        );
    }
    // store data for late activation
    activate_library('persistent');
    $pstore = [
    'activation_type' => $activationType,
    'early_feedback' => $earlyFeedback,
    'feedback_text' => $feedbackText
    ];
    persistent_store('activation_delayed', $pstore);

    // return failure
    return false;
}

/**
 * @param       $host
 * @param       $url
 * @param       $postdata
 * @param false $useragent
 *
 * @return array
 */
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
    if (( $fp == false )||( $errno > 0 ) ) {
        return [
        'success' => false,
        'errno' => $errno,
        'errstr' => $errstr
        ];
    }

    $success = true;

    // start POST request
    fwrite($fp, "$http_method $url HTTP/1.1\r\n");
    fwrite($fp, "Host: $host\r\n");
    fwrite($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
    fwrite($fp, "Content-Length: $http_content_length\r\n");
    if ($useragent ) {
        fwrite($fp, "User-Agent: $useragent\r\n");
    }
    fwrite($fp, "Connection: close\r\n\r\n");

    // send POST data
    if ($http_content_length > 0 ) {
        fwrite($fp, $http_post_data);
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
    return [
    'success' => $success,
    'headers' => $headerblock,
    'body' => $body
    ];
}

/**
 * @return bool
 */
function activation_delayed()
{
    global $guru;

    // skip delayed activation when variable set (only execute function once)
    if (@isset($guru[ 'no_delayed_activation' ]) ) {
        return false;
    }

    $guru[ 'no_delayed_activation' ] = true;

    // required library
    activate_library('persistent');

    // scan for delayed activation data
    $act = persistent_read('activation_delayed');
    if ($act == false ) {
        return false;
    }

    // add message that we are trying to activate using late activation data
    page_feedback('trying to activate using delayed activation data');

    // try to activate
    $uuid = activation_submit(
        $act[ 'activation_type' ], $act[ 'early_feedback' ],
        $act[ 'feedback_text' ] 
    );

    // process result
    if (is_string($uuid)&&($uuid !== '') ) {
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
    }

    return false;
}

/**
 * @param false $storenewhash
 *
 * @return bool
 */
function activation_hwchange( $storenewhash = false )
{
    // required library
    activate_library('persistent');
    // retrieve dmesg
    $dmesg = file_get_contents('/var/run/dmesg.boot');
    $dpos = strrpos($dmesg, ' The FreeBSD Project.');
    $dmesg = substr($dmesg, ( int )$dpos);
    // search for interface MAC
    $rxp = '/^[a-z]+\d+\: Ethernet address\: (([0-9a-f]{2}\:){5}[0-9a-f]{2})/m';
    preg_match_all($rxp, $dmesg, $mac_matches);
    $mac = [];
    foreach ( $mac_matches[ 1 ] as $id => $macaddr ) {
        if ($macaddr !== '') {
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
    if (!$currenthash) {
        persistent_store('hardware_hash', $hwhash);
        return false;
    }

    if (( $currenthash !== $hwhash )&&!$storenewhash) {
        return true;
    }

    if (( $currenthash !== $hwhash )&& $storenewhash) {
        persistent_store('hardware_hash', $hwhash);
        return true;
    }

    return false;
}

/**
 * @return array|false
 */
function activation_info()
{
    global $guru;

    // activation UUID
    $uuid = @$guru[ 'preferences' ][ 'uuid' ];
    if ($uuid === '') {
        return false;
    }

    // set server host
    $host = 'activation.zfsguru.com';

    // set activation info URL
    $url = '/zfsguru_info.php';

    // construct POST data
    $postdata = [
    'ver' => 1,
    'magic' => '__ZFSGURU_ACTIVATION_INFO__',
    'uuid' => $uuid,
    ];

    // set user agent
    $useragent = 'ZFSguru/' . $guru[ 'product_version_string' ];

    // send data
    $result = activation_post_request($host, $url, $postdata, $useragent);

    // process result
    $arr = @unserialize($result[ 'body' ]);

    // return result
    if (is_array($arr)) {
        return $arr;
    }

    if (@strlen($result[ 'body' ]) === 0) {
        page_feedback(
            'could not retrieve activation details, response by server: '
            . htmlentities($result[ 'body' ]), 'a_error'
        );
    } else {
        return false;
    }
}
