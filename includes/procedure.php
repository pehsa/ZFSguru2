<?php

/*
 ** ZFSguru procedures (executed every pageview)
 */

// start session (requires php5-session extension)
timerstart('session', 'init');
if (!function_exists('session_start') ) {
    die('ZFSguru web-interface broken: requires php5-session extension!');
}

session_start();
timerend('session');

// check for error function
if (!function_exists('error') ) {
    die('ZFSguru web-interface broken: no error function found!');
}

// read preferences and store in $guru['preferences']
timerstart('preferences', 'init');
$guru[ 'preferences' ] = procedure_readpreferences();
timerend('preferences');
//viewarray($guru['preferences']);

// set visual theme tag (visual theme may be changed by preferences)
timerstart('theme', 'init');
page_themepath();
timerend('theme');

// authentication
timerstart('authentication', 'init');
$authorized = procedure_authenticate($guru[ 'preferences' ]);
timerend('authentication');
if (!$authorized ) {
    page_rawfile('pages/internal/accessdenied.page');
    die();
}

// sanitize
if (!@$_SESSION[ 'sanitized' ] ) {
    timerstart('sanitize', 'init');
    procedure_sanitize();
    $_SESSION[ 'sanitized' ] = true;
    timerend('sanitize');
}

// set timezone - after reading preferences
timerstart('timezone', 'init');
$tz_map = procedure_timezone_map($guru[ 'preferences' ][ 'timezone' ]);
date_default_timezone_set($tz_map[ 'tz_php' ]);
timerend('timezone');

// set socket timeout
if (@$guru[ 'preferences' ][ 'connect_timeout' ] > 0 ) {
    ini_set('default_socket_timeout', $guru[ 'preferences' ][ 'connect_timeout' ]);
}

// update database if needed
if (!$guru['preferences']['offline_mode'] && ($guru['preferences']['refresh_lastcheck'] + $guru['preferences']['refresh_rate']) <= time(
    )) {
        timerstart('updatedatabase', 'init');
        activate_library('gurudb');
        gurudb_update();
        timerend('updatedatabase');
    }

timerend('init');


/* functions */

function procedure_readpreferences()
{
    global $guru;
    if (!@isset($guru[ 'default_preferences' ]) ) {
        die('ZFSguru web-interface broken: no default preferences found!');
    }
    $preferences = $guru[ 'default_preferences' ];
    $filename = $guru[ 'configuration_file' ];
    if (strlen($filename) < 2 ) {
        error('HARD ERROR: configuration filename unknown!');
    }
    // check if configuration file exists and create if not
    if (!is_readable($filename) ) {
        // start welcome wizzard
        $content = content_handle('internal', 'welcome');
        page_handle($content);
        die();
        // write preferences file containing default preferences
        /*procedure_writepreferences($preferences);
        // set notify to inform user we created a new file
        page_feedback(
            'A new configuration file has been created at '
            . '<b>' . htmlentities(realpath($filename)) . '</b>' 
        );
        // and return default preferences
        return $preferences;*/
    }
    $file = @file_get_contents($filename);
    // NOTE: == operator used and not ===
    // 0 bytes written or failure writing files
    if ($file === false ) {
        page_feedback(
            'failed reading configuration file '
            . '<b>' . $filename . '</b> - check permissions!', 'a_warning' 
        );
        return $preferences;
    }
    // unserialize binary file contents into array
    $ser = @unserialize($file);
    if (is_array($ser) ) {
        $preferences = array_merge($preferences, $ser);
        if (@strlen($preferences[ 'timezone' ]) < 1 ) {
            $preferences[ 'timezone' ] = $guru[ 'default_preferences' ][ 'timezone' ];
        }
        return $preferences;
    }

    page_feedback(
        'Failed extracting configuration file '
        . '"' . $filename . '" - corrupt? Try deleting the file.', 'a_error'
    );

    return $preferences;
}

function procedure_writepreferences( $preferences )
{
    global $guru;

    // activate superman
    activate_library('super');

    // serialize preferences array into string
    $ser = serialize($preferences);
    if (@!is_string($ser) ) {
        error('HARD ERROR: serialize preferences did not return a string');
    }
    if (strlen($ser) < 2 ) {
        error('HARD ERROR: serialize preferences returned malformed string');
    }

    // set correct privileges for file using root access
    $filename = $guru[ 'configuration_file' ];
    super_execute('/usr/bin/touch ' . $filename);
    super_execute('/usr/sbin/chown 888:888 ' . $filename);
    super_execute('/bin/chmod 640 ' . $filename);

    // now write preferences to file
    if (strlen($filename) < 2 ) {
        error('HARD ERROR: configuration filename unknown!');
    }
    $result = file_put_contents($filename, $ser);

    // NOTE: == operator used and not ===
    // meaning 0 bytes written or failure writing files
    if ($result == false ) {
        page_feedback(
            'Failed writing to <b>' . $filename . '</b> - check '
            . 'permissions!', 'a_error' 
        );
        return false;
    }

    return true;
}

function procedure_authenticate( $preferences, $ip_auth_only = false )
{
    $result = false;
    $client_ip = long2ip(ip2long($_SERVER[ 'REMOTE_ADDR' ]));
    if (!@isset($preferences[ 'access_control' ]) ) {
        error('NO ACCESS CONTROL SET!');
    }
    if ($preferences[ 'access_control' ] == 1 ) {
        $result = true;
    } elseif ($preferences[ 'access_control' ] == 2 ) {
        // check if client comes from LAN
        if (strpos($client_ip, '10.') === 0) {
            $result = true;
        } elseif (strpos($client_ip, '192.168.') === 0) {
            $result = true;
        } elseif (strpos($client_ip, '172.') === 0) {
            // between 172.16.x.x and 172.31.x.x
            $secondblock = ( int )@substr($client_ip, strlen('172.'), 2);
            if (( $secondblock >= 16 )AND( $secondblock <= 31 ) ) {
                $result = true;
            }
        }
        elseif (strpos($client_ip, '127.0.0.') === 0) {
            $result = true;
        }
    }
    elseif ($preferences[ 'access_control' ] == 3 ) {
        // only allow connections from whitelisted IP addresses
        if (@isset($preferences[ 'access_whitelist' ][ $_SERVER[ 'REMOTE_ADDR' ] ]) ) {
            $result = true;
        } elseif (strpos($client_ip, '127.0.0.') === 0) {
            $result = true;
        } else {
            foreach ( @$preferences[ 'access_whitelist' ] as $cidr ) {
                if (strpos($cidr, '/') !== false ) {
                    list( $baseip, $prefix ) = explode('/', $cidr);
                    $result = ( ip2long($client_ip) &
                    ~( ( 1 << ( 32 - $prefix ) ) - 1 ) ) == ip2long($baseip);
                }
            }
        }
    }

    // return result if false
    if (!$result) {
        return false;
    }

    if ($ip_auth_only) {
        return true;
    }

    // check for submitted authentication
    if (@strlen($_POST[ 'zfsguru_authenticate' ]) > 0 ) {
        if ($_POST[ 'zfsguru_authenticate' ] == $preferences[ 'authentication' ] ) {
            // valid auth
            $md5hash = md5($preferences[ 'authentication' ]);
            $_SESSION[ 'authenticated' ] = 'AUTH' . $md5hash;
        } else {
            // invalid auth
            die('INVALID AUTHENTICATION');
        }
    }

    // password authentication
    if (@strlen($preferences[ 'authentication' ]) > 0 ) {
        $md5hash = md5($preferences[ 'authentication' ]);
        if (@$_SESSION[ 'authenticated' ] != 'AUTH' . $md5hash ) {
            // re-authenticate
            // TODO: this should be improved as well as impose a retry limit
            page_rawfile('pages/internal/authenticate.page');
            die();
        }
    }
    return true;
}

function procedure_sanitize( $check_sudo = true )
{
    global $guru;
    // check for required binaries
    foreach ( $guru[ 'required_binaries' ] as $bin ) {
        if (!is_executable($bin) ) {
            error('Required binary "' . $bin . '" not found or not executable; aborting');
        }
    }
    // test command execution
    if (trim(shell_exec("echo test")) !== 'test' ) {
        error('Command execution test failed; aborting');
    }
    // test if we are zfsguru-web user
    if (trim(shell_exec("whoami")) !== 'zfsguru-web' ) {
        error('PHP script is not running as the "zfsguru-web" user; aborting');
    }
    // test if we have sudo root access
    if ($check_sudo === true ) {
        $sudo_cmd = '/usr/local/bin/sudo whoami';
        if (trim(shell_exec("\$sudo_cmd")) !== 'root' ) {
            error('No SUDO access; aborting');
        }
    }
}

function procedure_timezone_map( $timezone )
{
    $arr = array( 'tz_php' => $timezone, 'tz_system' => $timezone );
    switch ( $timezone ) {
    case "UTC":
        $arr[ 'tz_system' ] = 'Etc/UTC';
        break;
    case "Australia/ACT":
        $arr[ 'tz_system' ] = 'Australia/Sydney';
        break;
    }
    return $arr;
}

function netMatch( $cidr, $ip ) 
{
    list( $net, $mask ) = explode('/', $cidr);
    return ( ip2long($ip) & ~( ( 1 << ( 32 - $mask ) ) - 1 ) ) == ip2long($net);
}

function clientInSameSubnet( $client_ip = false, $server_ip = false ) 
{
    if (!$client_ip ) {
        $client_ip = $_SERVER[ 'REMOTE_ADDR' ];
    }
    if (!$server_ip ) {
        $server_ip = $_SERVER[ 'SERVER_ADDR' ];
    }
    // Extract broadcast and netmask from ifconfig
    if (!( $p = popen("ifconfig", "r") ) ) { return false;
    }
    $out = "";
    while ( !feof($p) ) {
        $out .= fread($p, 1024);
    }
    fclose($p);
    $match = "/^.*" . $server_ip;
    $match .= ".*Bcast:(\d{1,3}\.\d{1,3}i\.\d{1,3}\.\d{1,3}).*";
    $match .= "Mask:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/im";
    if (!preg_match($match, $out, $regs) ) {
        return false;
    }
    $bcast = ip2long($regs[ 1 ]);
    $smask = ip2long($regs[ 2 ]);
    $ipadr = ip2long($client_ip);
    $nmask = $bcast & $smask;
    return ( ( $ipadr & $smask ) == ( $nmask & $smask ) );
}
