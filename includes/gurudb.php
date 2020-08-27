<?php

/* GuruDB library */

function gurudb_fetch( $item, $file = false, $silent = false )
{
    if (!$file ) {
        global $guru;
        $file = $guru[ 'docroot' ] . '/config/zfsguru.gurudb';
    }
    $command = '/usr/bin/tar -xOf ' . escapeshellarg($file) . ' ' . escapeshellarg($item);
    $output = shell_exec($command);
    if (( $output === null )AND!$silent ) {
        page_feedback('tar error extracting GuruDB, item: ' . htmlentities($item), 'a_warning');
    } elseif (substr($item, -4) === '.ser' ) {
        $o = unserialize($output)or $o = array();
        if (!is_array($o) ) {
            die('DEBUG: notarray!!!');
        }
        return $o;
    }
    return $output;
}

function gurudb_general( $file = false ) 
{
    return gurudb_fetch('general/index.ser', $file);
}

function gurudb_master( $file = false ) 
{
    return gurudb_fetch('master/index.ser', $file);
}

function gurudb_slave( $file = false ) 
{
    return gurudb_fetch('slave/index.ser', $file);
}

function gurudb_interface( $file = false ) 
{
    return gurudb_fetch('interface/index.ser', $file);
}

function gurudb_system( $file = false ) 
{
    return gurudb_fetch('system/index.ser', $file);
}

function gurudb_category( $file = false ) 
{
    return gurudb_fetch('category/index.ser', $file);
}

function gurudb_service( $file = false ) 
{
    return gurudb_fetch('service/index.ser', $file);
}

function gurudb_distribution( $sysver = false, $platform = false, $file = false ) 
{
    if (!$sysver ) {
        $sysver = common_systemversion();
        $sysver = $sysver[ 'sysver' ];
    }
    if (!$platform ) {
        $platform = common_systemplatform();
    }
    $dist = gurudb_fetch("distribution/$sysver-$platform.ser", $file, true);
    if (is_array($dist) ) {
        return $dist;
    }

    return array();
}

function gurudb_bulletin( $file = false ) 
{
    return gurudb_fetch('bulletin/index.ser', $file);
}

function gurudb_bulletin_body( $nr, $file = false ) 
{
    return gurudb_fetch("bulletin/$nr", $file);
}

/* guruDB internal functions */

function gurudb_validate( $newgeneral )
{
    global $guru;
    if (!@is_array($newgeneral) ) {
        page_feedback(
            'downloaded GuruDB rejected: invalid array structure',
            'a_failure' 
        );
        return false;
    }
    if ($newgeneral[ 'ident' ] !== 'ZFSGURU:GURUDB' ) {
        page_feedback(
            'downloaded GuruDB rejected: invalid identification',
            'a_failure' 
        );
        return false;
    }
    if (!preg_match('/(\d+):(\d+)/', $newgeneral[ 'compat' ], $matches)) {
        page_feedback(
            'downloaded GuruDB rejected: unknown compatibility',
            'a_failure'
        );
        return false;
    }

    if (( int )$matches[ 1 ] > $guru[ 'compat_max' ]) {
        page_feedback(
            'downloaded GuruDB rejected: database exceeds compatibility - '
            . 'update web-interface and try again', 'a_failure'
        );
        return false;
    }

    if (( int )$matches[ 2 ] < $guru[ 'compat_min' ]) {
        page_feedback(
            'downloaded GuruDB rejected: database is too old (compat)',
            'a_failure'
        );
        return false;
    }

    // all tests positive; return success
    return true;
}

function gurudb_update()
{
    global $guru;

    // required libraries
    activate_library('bulletin');
    activate_library('server');

    // remote file (URI) - cannot use server_uri here!
    $uri = 'db/' . $guru[ 'compat_max' ] . '/zfsguru.gurudb';

    // file path to local GuruDB database
    $localdb = $guru[ 'docroot' ] . '/config/zfsguru.gurudb';
    if (!file_exists($localdb) ) {
        page_feedback('local GuruDB does not exist!', 'a_warning');
    }

    // download to temp folder (?)
    $result = server_download($uri, false, false, true, $localdb);
    if ($result === false) {
        page_feedback(
            'could not download new GuruDB database from remote servers!',
            'a_error'
        );
        page_feedback(
            'if you have no internet connection, you can disable remote '
            . 'file downloading on the System->Preferences->Advanced page', 'c_notice'
        );
        return false;
    }

    if ($result !== null) {
        // read GuruDB
        $newgeneral = gurudb_general($result);
        // validate downloaded GuruDB database
        if (!gurudb_validate($newgeneral) ) {
            return false;
        }

        // determine number of unread bulletin messages (created/modified)
        $guru[ 'preferences' ][ 'bulletin_unread' ] = bulletin_unread($result);

        // install downloaded GuruDB version
        // copy new version to web interface configuration directory
        exec(
            '/bin/mv ' . escapeshellarg($result) . ' '
            . escapeshellarg(dirname($localdb) . '/new.gurudb'), $output, $rv
        );
        if ($rv != 0 ) {
            error('error moving GuruDB file to web-interface directory (1)');
        }
        // backup old version
        exec(
            '/bin/cp -p ' . escapeshellarg($localdb) . ' '
            . escapeshellarg(dirname($localdb) . '/old.gurudb') . ' 2>&1', $output, $rv
        );
        if ($rv != 0 ) {
            error('error moving GuruDB file to web-interface directory (2)');
        }
        // replace old version with new version
        exec(
            '/bin/mv ' . escapeshellarg(dirname($localdb) . '/new.gurudb')
            . ' ' . escapeshellarg($localdb), $output, $rv
        );
        if ($rv != 0 ) {
            error('error moving GuruDB file to web-interface directory (3)');
        }

        // report about new database installed
        $newdate = date('j M Y @ H:i e', $newgeneral[ 'timestamp' ]);
        page_feedback(
            'installed new GuruDB database, created on: <b>' . $newdate . '</b>',
            'c_notice'
        );
    }

    // update preferences to reflect update status
    $guru[ 'preferences' ][ 'refresh_lastcheck' ] = time();
    procedure_writepreferences($guru[ 'preferences' ]);

    if ($result === null ) {
        // remove message
        page_feedback('database is up to date with master server', 'c_notice');
        return null;
    }
    return true;
}
