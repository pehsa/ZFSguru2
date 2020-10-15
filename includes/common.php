<?php

/*
 ** ZFSguru Web-interface - common.php
 ** common functions part of every request
 */


/**
 * @param $library
 */
function activate_library( $library )
{
    global $guru;
    // TODO - SECURITY
    $librarypath = $guru[ 'docroot' ] . 'includes/' . $library . '.php';
    include_once $librarypath;
}

/**
 * @param $commands
 * @param $redirect_url
 */
function dangerouscommand( $commands, $redirect_url )
{
    $data = compact('commands', 'redirect_url');
    $content = content_handle('internal', 'dangerouscommand', $data, true);
    page_handle($content);
    die();
}

/**
 * @param $message
 */
function error( $message )
{
    page_injecttag(['MESSAGE' => $message]);
    $content = content_handle('internal', 'error', false, true);
    page_handle($content);
    die();
}

/**
 * @param $message
 * @param $url
 */
function friendlyerror( $message, $url )
{
    page_feedback($message, 'a_error');
    redirect_url($url);
}

/**
 * @param $message
 * @param $url
 */
function friendlynotice( $message, $url )
{
    page_feedback($message);
    redirect_url($url);
}

/**
 * @param $url
 */
function redirect_url( $url )
{
    header('Location: ' . $url);
    die();
}

/**
 * @param     $bytes
 * @param int $precision
 *
 * @return string
 */
function sizehuman( $bytes, $precision = 0 )
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(( $bytes ? log($bytes) : 0 ) / log(1000));
    $pow = min($pow, count($units) - 1);
    $bytes /= 1000 ** $pow;
    return round($bytes, $precision) . ' ' . $units[ $pow ];
}

/**
 * @param     $bytes
 * @param int $precision
 *
 * @return string
 */
function sizebinary( $bytes, $precision = 0 )
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $bytes = max($bytes, 0);
    $pow = floor(( $bytes ? log($bytes) : 0 ) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= 1024 ** $pow;
    return round($bytes, $precision) . ' ' . $units[ $pow ];
}

/**
 * @param        $input
 * @param string $rules
 * @param null   $modify
 * @param int    $maxlen
 *
 * @return bool
 */
function sanitize( $input, $rules = 'a-zA-Z0-9_-', & $modify = null, $maxlen = 0 )
{
    if ($maxlen === 0 ) {
        $modify = preg_replace('/[^' . $rules . ']/', '', $input);
    } else {
        $modify = substr(preg_replace('/[^' . $rules . ']/', '', $input), 0, $maxlen);
    }
    if ($modify === '' ) {
        return false;
    }
    return ( $modify === $input );
}

/**
 * @return string[]
 */
function common_dirs()
{
    if (!is_dir('/download')OR!is_dir('/services') ) {
        activate_library('zfsguru');
        zfsguru_init_dirs();
    }

    return [
    'services' => '/services',
    'download' => '/download',
    'temp' => '/tmp',
    ];
}

/* power cache */

/**
 * @param false $element
 * @param false $serve_expired
 *
 * @return false|mixed
 */
function powercache_read( $element = false, $serve_expired = false )
{
    global $guru;

    // read cache.bin file to $guru['powercache'] array
    if (@is_array($guru['powercache'])) {
        $arr = $guru[ 'powercache' ];
    } else {
        // read cache.bin file
        $raw = @file_get_contents($guru[ 'docroot' ] . '/config/cache.bin');
        $arr = @unserialize($raw);
        if (!is_array($arr) ) {
            return false;
        }

        $guru[ 'powercache' ] = $arr;
    }

    // check for element
    if (@isset($arr[ $element ]) ) {
        $expired = @$arr[ $element ][ 'expiry' ] <= time();
        if (( !$expired )||( $serve_expired ) ) {
            return $arr[ $element ][ 'data' ];
        }

        return false;
    }

    return false;
}

/**
 * @param false $element
 * @param false $data
 * @param int   $expiry
 */
function powercache_store( $element = false, $data = false, $expiry = 5 )
{
    global $guru;

    // check if powercache has been read first
    if (!@isset($guru[ 'powercache' ]) ) {
        powercache_read();
    }

    // store value in $guru['powercache']
    if (( $element !== false )AND( $data !== false ) ) {
        $guru[ 'powercache' ][ $element ] = [
        'expiry' => time() + $expiry,
        'data' => $data
        ];
    }

    // check if cache.bin exists, if not create with root user
    if (!@file_exists($guru[ 'docroot' ] . '/config/cache.bin') ) {
        activate_library('super');
        super_execute('/usr/bin/touch ' . $guru[ 'docroot' ] . '/config/cache.bin');
        super_execute('/usr/sbin/chown 888:888 ' . $guru[ 'docroot' ] . '/config/cache.bin');
    }

    // now store powercache to cache.bin file
    $ser = serialize($guru[ 'powercache' ]);
    file_put_contents($guru[ 'docroot' ] . '/config/cache.bin', $ser);
}

/**
 * @param false $element
 *
 * @return bool
 */
function powercache_purge( $element = false )
{
    global $guru;

    if ($element === false ) {
        // purge all
        @unlink($guru[ 'docroot' ] . '/config/cache.bin');
        unset($guru[ 'powercache' ]);
        return true;
    }

    // purge specific element

    // check if powercache has been read first
    if (!@isset($guru[ 'powercache' ]) ) {
        powercache_read();
    }

    // unset element and store cache.bin
    unset($guru[ 'powercache' ][ $element ]);
    powercache_store();
}

/* query functions */

/**
 * @param $sysctl_var
 *
 * @return string
 */
function common_sysctl( $sysctl_var )
{
    return @trim(shell_exec('/sbin/sysctl -n ' . escapeshellarg($sysctl_var)));
}

/**
 * @return string
 */
function common_systemplatform()
{
    return common_sysctl('hw.machine_arch');
}

/**
 * @return array|string[]
 */
function common_systemversion()
{
    activate_library('gurudb');

    $system = gurudb_system();
    $platform = common_systemplatform();
    $dist = common_distribution_type();
    $sha512file = '/zfsguru.sha512';
    if (@file_exists($sha512file)AND @is_readable($sha512file) ) {
        $sha512 = @trim(file_get_contents($sha512file));
        foreach ( $system as $sysver => $platforms ) {
            if ($platforms[ $platform ][ 'sha512' ] == $sha512 ) {
                return compact('dist', 'sysver', 'sha512');
            }
        }
        // current running system not known by remote system version list
        return ['dist' => $dist, 'sysver' => 'unknown', 'sha512' => $sha512];
    }
    return ['dist' => $dist, 'sysver' => 'unknown', 'sha512' => '0'];
}

/**
 * @return string
 */
function common_distribution_type()
{
    global $guru;
    $dist = @trim(file_get_contents('/' . strtolower($guru[ 'product_name' ]) . '.dist'));
    if ($dist ) {
        return $dist;
    }
    return 'unknown';
}

/**
 * @param $type
 *
 * @return string
 */
function common_distribution_name( $type )
{
    $distnames = [
    'RoZ' => 'Root-on-ZFS',
    'RoR' => 'Root-on-RAM',
    'RoR+union' => 'Root-on-RAM + Union',
    'RoM' => 'Root-on-Media',
    ];
    if (@isset($distnames[ $type ]) ) {
        return $distnames[ $type ];
    }
    return 'Unknown';
}

/* timekeeper */

/**
 * @param       $name
 * @param false $parent
 */
function timerstart( $name, $parent = false )
{
    global $timer;
    $timer[ 'p' ][ $name ][ 'start' ] = microtime(true);
    if (!$parent ) {
        $timer[ 'p' ][ $name ][ 'tier' ] = 0;
    } elseif (@is_int($timer[ 'p' ][ $parent ][ 'tier' ]) ) {
        $timer[ 'p' ][ $name ][ 'tier' ] = $timer[ 'p' ][ $parent ][ 'tier' ] + 1;
    }
    //  $timer[$name]['parent'] = $parent;
}

/**
 * @param $name
 */
function timerend( $name )
{
    global $timer;
    $timer[ 'p' ][ $name ][ 'end' ] = microtime(true);
}

/**
 * @param false $name
 *
 * @return string|void
 */
function timekeeper( $name = false )
{
    global $guru, $timer;
    if (!@$guru[ 'preferences' ][ 'timekeeper' ] ) {
        return;
    }
    if ($name) {
        $total = $timer[ 'total' ];
        $piece = @$timer[ 'p' ][ $name ][ 'end' ] - $timer[ 'p' ][ $name ][ 'start' ];
        $msec = round($piece * 1000, 1);
        if ($msec < 0 ) {
            return '';
        }
        $left = round(( ( $timer[ 'p' ][ $name ][ 'start' ] - $_SERVER[ 'REQUEST_TIME_FLOAT' ] ) / $total ) * 1000, 3);
        $width = round(max(0.1, ( $piece / $total ) * 1000), 3);
        $top = ( int )@$timer[ 'p' ][ $name ][ 'tier' ] * 10;
        $xtra = ( $top > 0 ) ? 'z-index:1;' : '';
        $colour = '666';
        $str = '<div style="top:' . $top . 'px; left:' . $left . 'px; width:' . $width . 'px; background:#' . $colour . '; ' . $xtra . '" title="' . htmlentities($name) . ': ' . $msec . ' ms"></div>';
    } else {
        $timer[ 'end' ] = microtime(true);
        $timer[ 'total' ] = $timer[ 'end' ] - $_SERVER[ 'REQUEST_TIME_FLOAT' ];
        $str = '<div id="timekeeper">';
    }
    if (is_array($timer) ) {
        foreach ( $timer[ 'p' ] as $tname => $data ) {
            if ($name AND( @$data[ 'parent' ] == $name ) ) {
                $str .= timekeeper($tname);
            } elseif (!$name AND!@$data[ 'parent' ] ) {
                $str .= timekeeper($tname);
            }
        }
    }
    if (!$name ) {
        $str .= '</div><div id="timekeeper-total">' . ( int )( ( $timer[ 'end' ] - $_SERVER[ 'REQUEST_TIME_FLOAT' ] ) * 1000 ) . ' ms</div>';
    }

    return $str;
}

/* debug functions */

/**
 * @param $arr
 */
function viewarray( $arr )
{
    echo( '<table style="padding: 0; border-collapse: collapse; border-spacing: 0; border: 1px;">' );
    foreach ( ( array )$arr as $key1 => $elem1 ) {
        echo( '<tr>' );
        echo( '<td>' . htmlentities($key1) . '&nbsp;</td>' );
        if (is_array($elem1) ) {
            extarray($elem1);
        } else {
            echo( '<td>' . nl2br(htmlentities($elem1)) . '&nbsp;</td>' );
        }
        echo( '</tr>' );
    }
    echo( '</table>' );
}

/**
 * @param $arr
 */
function extarray( $arr )
{
    echo( '<td>' );
    echo( '<table  style="padding: 0; border-collapse: collapse; border-spacing: 0; border: 1px;">' );
    foreach ( $arr as $key => $elem ) {
        echo( '<tr>' );
        echo( '<td>' . htmlentities($key) . '&nbsp;</td>' );
        if (is_array($elem) ) {
            extarray($elem);
        } else {
            echo( '<td>' . nl2br(htmlentities($elem)) . '&nbsp;</td>' );
        }
        echo( '</tr>' );
    }
    echo( '</table>' );
    echo( '</td>' );
}
