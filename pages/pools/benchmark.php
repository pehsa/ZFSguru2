<?php

function content_pools_benchmark() 
{
    global $tags;

    // required library
    activate_library('zfs');

    // fetch data
    $pools = zfs_pool_list();

    // poollist
    $poollist = array();
    foreach ( $pools as $poolname => $data ) {
        if (( $data[ 'status' ] == 'ONLINE' )OR( $data[ 'status' ] == 'DEGRADED' ) ) {
            $poollist[] = array(
                'POOLNAME' => $poolname
            );
        }
    }

    // hide benchmark output if no form submitted
    $class_bench = ( @isset($tags[ 'POOLS_BENCHMARKOUTPUT' ]) ) ?
    'normal' : 'hidden';

    // export new tags
    $newtags = array(
    'PAGE_ACTIVETAB' => 'Benchmark',
    'PAGE_TITLE' => 'Benchmark',
    'TABLE_POOLLIST' => $poollist,
    'CLASS_BENCHMARK' => $class_bench,
    );
    return $newtags;
}

function submit_pools_benchmark() 
{
    global $guru;

    // required libraries
    activate_library('super');
    activate_library('zfs');

    // sanitize input
    sanitize(@$_POST[ 'poolname' ], null, $poolname);
    if (strlen($poolname) < 1 ) {
        error('sanity failure on pool name');
    }

    // call function
    $poolfs = zfs_filesystem_list();
    $pool = zfs_pool_list($poolname);

    // variables
    $url = 'pools.php?benchmark';
    $size = @$_POST[ 'size' ];
    $source = '/dev/zero';
    $testfilename = 'zfsguru_benchmark.000';
    $testfilesystem = $poolname . '/zfsguru-performance-test';
    $capacity = $pool[ 'size' ];
    $capacity_pct = $pool[ 'cap' ];
    $testsize_gib = @( ( int )$size / 1024 );
    $testsize_bin = sizebinary(( ( int )$size / 1024 ) * 1024 * 1024 * 1024);

    // check whether test filesystem already exists
    if (@isset($poolfs[ $testfilesystem ]) ) {
        error(
            'test filesystem ' . $testfilesystem . ' already exists, '
            . 'please remove manually!' 
        );
    }

    // create test filesystem
    super_execute('/sbin/zfs create ' . $testfilesystem);
    super_execute('/sbin/zfs set dedup=off ' . $testfilesystem);

    // check for creation
    // CACHE contamination!
    $poolfs = zfs_filesystem_list($testfilesystem);
    if (!@isset($poolfs[ $testfilesystem ]) ) {
        error('test filesystem ' . $testfilesystem . ' could not be created!');
    }

    // set testfile location
    $mountpoint = @$poolfs[ $testfilesystem ][ 'mountpoint' ];
    if (( @strlen($mountpoint) < 2 )OR( $mountpoint {        0        } != '/' ) 
    ) {
        error('Invalid mountpoint "' . $mountpoint . '"');
    }
    $testfile = $mountpoint . '/' . $testfilename;

    // create score arrays
    $score = array();
    $score_rv = array();

    if (@$_POST[ 'cb_normal' ] == 'on' ) {
        // dd write
        super_execute('/sbin/zfs set compression=off ' . $testfilesystem);
        $command = '/bin/dd if=' . $source . ' of=' . $testfile . ' bs=1m '
        . 'count=' . ( int )$size . ' 2>&1';
        $result = super_execute($command);
        $score_rv[ 'normal' ][ 'write' ] = @$result[ 'rv' ];
        $score[ 'normal' ][ 'write' ] = @$result[ 'output_arr' ][ 2 ];
        // cooldown
        super_execute('/bin/sync');
        sleep(10);
        // dd read
        $command = '/bin/dd if=' . $testfile . ' of=/dev/null bs=1m 2>&1';
        $result = super_execute($command);
        $score_rv[ 'normal' ][ 'read' ] = @$result[ 'rv' ];
        $score[ 'normal' ][ 'read' ] = @$result[ 'output_arr' ][ 2 ];
        // remove test file
        super_execute('/bin/rm ' . $testfile);
        // cooldown
        super_execute('/bin/sync');
        sleep(10);
    }

    if (@$_POST[ 'cb_lzjb' ] == 'on' ) {
        // dd write - LZJB compression
        super_execute('/sbin/zfs set compression=lzjb ' . $testfilesystem);
        $command = '/bin/dd if=' . $source . ' of=' . $testfile . ' bs=1m '
        . 'count=' . ( int )$size . ' 2>&1';
        $result = super_execute($command);
        $score_rv[ 'lzjb' ][ 'write' ] = @$result[ 'rv' ];
        $score[ 'lzjb' ][ 'write' ] = @$result[ 'output_arr' ][ 2 ];
        // cooldown
        super_execute('/bin/sync');
        sleep(10);
        // dd read - LZJB compression
        $command = '/bin/dd if=' . $testfile . ' of=/dev/null bs=1m 2>&1';
        $result = super_execute($command);
        $score_rv[ 'lzjb' ][ 'read' ] = @$result[ 'rv' ];
        $score[ 'lzjb' ][ 'read' ] = @$result[ 'output_arr' ][ 2 ];
        // remove test file
        super_execute('/bin/rm ' . $testfile);
        // cooldown
        super_execute('/bin/sync');
        sleep(10);
    }

    if (@$_POST[ 'cb_gzip' ] == 'on' ) {
        // dd write - GZIP compression
        super_execute('/bin/rm ' . $testfile);
        super_execute('/bin/sync');
        sleep(10);
        super_execute('/sbin/zfs set compression=gzip ' . $testfilesystem);
        $command = '/bin/dd if=' . $source . ' of=' . $testfile . ' bs=1m '
        . 'count=' . ( int )$size . ' 2>&1';
        $result = super_execute($command);
        $score_rv[ 'gzip' ][ 'write' ] = @$result[ 'rv' ];
        $score[ 'gzip' ][ 'write' ] = @$result[ 'output_arr' ][ 2 ];
        // cooldown
        super_execute('/bin/sync');
        sleep(10);
        // dd read - GZIP compression
        $command = '/bin/dd if=' . $testfile . ' of=/dev/null bs=1m 2>&1';
        $result = super_execute($command);
        $score_rv[ 'gzip' ][ 'read' ] = @$result[ 'rv' ];
        $score[ 'gzip' ][ 'read' ] = @$result[ 'output_arr' ][ 2 ];
        // remove test file
        super_execute('/bin/rm ' . $testfile);
        // cooldown
        super_execute('/bin/sync');
        sleep(10);
    }

    if (@$_POST[ 'cb_zeronull' ] == 'on' ) {
        $command = '/bin/dd if=' . $source . ' of=/dev/null bs=1m count='
        . ( int )$size . ' 2>&1';
        $result = super_execute($command);
        $score_rv[ 'bandwidth' ][ 'read' ] = @$result[ 'rv' ];
        $score[ 'I/O' ][ 'bandwidth' ] = @$result[ 'output_arr' ][ 2 ];
    }

    // destroy test filesystem
    super_execute('/sbin/zfs destroy ' . $testfilesystem);

    // process scores
    $finalscore = array();
    foreach ( $score as $testname => $testdata ) {
        ksort($testdata);
        $score[ $testname ] = $testdata;
    }
    foreach ( $score as $testname => $testdata ) {
        foreach ( $testdata as $rw => $testoutput ) {
            $speed = array();
            preg_match('/\(([0-9]+) bytes\/sec\)$/', $testoutput, $speed);
            if (@isset($speed[ 1 ]) ) {
                $finalscore[ $testname ][ $rw ] = $speed[ 1 ];
            }
        }
    }

    // retrieve system image name
    $currentver = common_systemversion();
    $sysname = $currentver[ 'sysver' ];

    // output string
    $outputstr =
    '<b>ZFSguru</b> ' . $guru[ 'product_version_string' ] . ' (' . $sysname . ') '
    . 'pool benchmark' . chr(10)
    . 'Pool            : ' . $poolname
    . ' (' . $capacity . ', <b>' . $capacity_pct . '</b> full)' . chr(10)
    . 'Test size       : ' . $testsize_bin . chr(10);

    foreach ( $finalscore as $testname => $testdata ) {
        foreach ( $testdata as $rw => $testscore ) {
            $outputstr .=
            $testname . ' ' . $rw . '	: <b>' . sizehuman($testscore) . '/s</b>' . chr(10);
        }
    }

    // return output
    return array(
    'POOLS_BENCHMARKOUTPUT' => $outputstr
    );
}
