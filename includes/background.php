<?php

/**
 * @param $tag
 * @param $data
 */
function background_register( $tag, $data )
{
    global $guru;

    // requires elevated privileges
    activate_library('super');

    // query background task
    $query = background_query($tag);

    // sanity checks
    if (!is_string($tag)||($tag === '') ) {
        error('cannot register background job: invalid tag provided');
    }
    if ($query[ 'exists' ] ) {
        error(
            'cannot register background job: <b>'
            . htmlentities($tag) . '</b> already exists!' 
        );
    }
    if (!is_array($data[ 'commands' ])|| empty($data[ 'commands' ]) ) {
        error('cannot register background job: no command array provided');
    }

    // option: execution type [serial/parallel] (default: serial execution)
    $execution = ( ( @$data[ 'execution' ] === 'unprotected' )OR( @$data[ 'execution' ] === 'parallel' ) ) ? $data[ 'execution' ] : 'protected';

    // option: execute with super privileges [true/false] (false = php user)
    $super = ( @$data[ 'super' ] ) ? true : false;

    // option: combined output [true/FALSE]
    $combinedoutput = ( @$data[ 'combinedoutput' ] === true );

    // variables
    $dir = $guru[ 'tempdir' ] . '/zfsguru-bg/bg-' . base64_encode($tag);
    $file_master = $dir . '/master.sh';
    $file_storage = $dir . '/storage.ser';

    // initialise directory
    // TODO: drop caches only for specific path
    clearstatcache();
    if (is_dir($dir) ) {
        // remove stale directory
        super_execute('/bin/rm -R ' . escapeshellarg($dir));
        clearstatcache();
        error('background directory already exists, and removing it has failed!');
    }
    $r = super_execute('/bin/mkdir -p ' . escapeshellarg($dir));
    if ($r[ 'rv' ] !== 0 ) {
        error('could not create directory: ' . htmlentities($dir));
    }
    super_execute('/usr/sbin/chown root:888 ' . escapeshellarg($dir));
    // TODO: SECURITY: change chmod back to 770
    super_execute('/bin/chmod 777 ' . escapeshellarg($dir));

    // write master script
    $master = '#!/bin/sh' . chr(10) . '# ZFSguru background tag: '
    . str_replace(chr(10), '', $tag) . chr(10) . chr(10) . chr(10);
    foreach ( $data[ 'commands' ] as $ctag => $command ) {
        // variables
        $suffix = base64_encode($ctag);
        $file_cmd = $dir . '/cmd-' . $suffix;
        $file_stdout = $dir . '/stdout-' . $suffix;
        $file_stderr = $dir . '/stderr-' . $suffix;
        $file_output = $dir . '/output-' . $suffix;
        $file_rv = $dir . '/rv-' . $suffix;
        $redirect = ( $combinedoutput ) ?
        '>' . $file_output . ' 2>&1': '>' . $file_stdout . ' 2>' . $file_stderr;
        if ($execution === 'parallel' ) {
            $redirect .= ' &';
        }

        // sanity
        if (!@is_string($command)||( @strlen($command) < 2 ) ) {
            error('background: command should be a string of at least 2 characters');
        }
        // TODO: why? has to do with array sorting and overwriting integer keys?
        //  if (is_int($ctag))
        //   error('background: ctag cannot be of type integer');

        // append master script
        $master .= '# ' . str_replace(chr(10), '', $ctag) . chr(10)
        . 'cat <<\'EOFBGCMD\' >' . $file_cmd . chr(10) .
        $command . chr(10)
        . 'RV=${?}; echo -n "${RV}" > ' . $file_rv . '; exit ${RV}' . chr(10)
        . 'EOFBGCMD' . chr(10)
        . '/bin/sh ' . $file_cmd . ' ' . $redirect . chr(10);
        if ($execution === 'protected' ) {
            $master .= 'RV=${?}; if [ "${RV}" -ne "0" ]; then exit ${RV}; fi' . chr(10);
        }
        $master .= chr(10) . chr(10);
    }

    $r = super_execute('/usr/bin/touch ' . escapeshellarg($file_master));
    if ($r[ 'rv' ] != 0 ) {
        error('could not create master file: ' . $file_master);
    }
    super_execute('/usr/sbin/chown root:888 ' . escapeshellarg($file_master));
    // TODO: security: change chmod back to 770
    super_execute('/bin/chmod 777 ' . escapeshellarg($file_master));
    clearstatcache();
    if (!file_put_contents($file_master, $master) ) {
        error(
            'could not write master file: ' . htmlentities($file_master)
            . ' - possibly a problem with permissions or disk space!' 
        );
    }

    // write storage data
    $storage = ( @is_array($data[ 'storage' ]) ) ? $data[ 'storage' ] : [];
    $storage[ 'commands' ] = $data[ 'commands' ];
    $storage[ 'options' ] = compact('execution', 'super', 'combinedoutput');
    if (!file_put_contents($file_storage, serialize($storage)) ) {
        error('could not write storage file: ' . htmlentities($file_storage));
    }

    // execute
    $commandstr = $file_master . ' > /dev/null &';
    if ($super ) {
        super_execute($commandstr);
    } else {
        exec($commandstr);
    }
}

/**
 * @param $tag
 *
 * @return array
 */
function background_query( $tag )
{
    global $guru;

    // sanity
    if ($tag == '') {
        error('no tag provided');
    }

    // variables
    $dir = $guru[ 'tempdir' ] . '/zfsguru-bg/bg-' . base64_encode($tag);
    $file_master = $dir . '/master.sh';
    $file_storage = $dir . '/storage.ser';

    // read storage array
    $storage = @unserialize(file_get_contents($file_storage));
    if (!is_array($storage) ) {
        $storage = [];
    }

    // default query array
    $query = [
    'exists' => false,
    'running' => false,
    'error' => false,
    'ctag' => [],
    'storage' => $storage,
    ];

    // verify directory and master/storage files exist and commands are provided
    if (!is_dir($dir) ) {
        return $query;
    }
    if (!file_exists($file_master)||!file_exists($file_storage) ) {
        return $query;
    }
    if (!@is_array($storage[ 'commands' ]) ) {
        return $query;
    }

    // after passing above sanity checks, the background job counts are existing
    $query[ 'exists' ] = true;

    // traverse each command to read all command files
    foreach ( $storage[ 'commands' ] as $ctag => $command ) {
        // variables
        $suffix = base64_encode($ctag);
        $file_cmd = $dir . '/cmd-' . $suffix;
        $file_stdout = $dir . '/stdout-' . $suffix;
        $file_stderr = $dir . '/stderr-' . $suffix;
        $file_output = $dir . '/output-' . $suffix;
        $file_rv = $dir . '/rv-' . $suffix;

        // read files and store in ctag array
        $query[ 'ctag' ][ $ctag ] = [
        'stdout' => ( file_exists($file_stdout) ) ?
        trim(file_get_contents($file_stdout)) : '',
        'stderr' => ( file_exists($file_stderr) ) ?
        trim(file_get_contents($file_stderr)) : '',
        'output' => ( file_exists($file_output) ) ?
        trim(file_get_contents($file_output)) : '',
        'rv' => ( file_exists($file_rv) ) ?
        trim(file_get_contents($file_rv)) : '',
        ];
        if (( int )$query[ 'ctag' ][ $ctag ][ 'rv' ] != 0 ) {
            $query[ 'error' ] = true;
        }
    }

    // check whether background job is running
    if ($query[ 'exists' ] ) {
        foreach ( $storage[ 'commands' ] as $ctag => $command ) {
            if (!@is_numeric($query[ 'ctag' ][ $ctag ][ 'rv' ]) ) {
                $query[ 'running' ] = true;
            }
        }
    }
    if ($query[ 'error' ]&&( $storage[ 'options' ][ 'execution' ] === 'protected' ) ) {
        $query[ 'running' ] = false;
    }

    // return array
    return $query;
}

/**
 * @param $tag
 *
 * @return bool
 */
function background_isrunning( $tag )
{
    $query = background_query($tag);
    return ( @$query[ 'running' ] === true );
}

/**
 * @param $tag
 *
 * @return bool
 */
function background_remove( $tag )
{
    global $guru;

    // query background job and perform sanity checks
    $query = background_query($tag);
    if (!$query[ 'exists' ]) {
        return false;
    }

    if ($query[ 'running' ]) {
        page_feedback(
            'can not remove a background job which is still running',
            'a_warning'
        );
        return false;
    }

    // remove temporary files
    activate_library('super');
    $dir = $guru[ 'tempdir' ] . '/zfsguru-bg/bg-' . base64_encode($tag);
    $r = super_execute('/bin/rm -R ' . escapeshellarg($dir));
    return ( $r[ 'rv' ] == 0 );
}

function background_kill()
{
    error('background_kill function is not yet implemented');
}
