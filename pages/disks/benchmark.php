<?php

function content_disks_benchmark() 
{
    global $guru;

    // required library
    activate_library('service');

    // tabbar
    $tabbar = array(
    'simplebench' => 'Simple benchmarking',
    'advancedbench' => 'Advanced benchmarking'
    );
    $tabbar_url = 'disks.php?benchmark';
    $tabbar_tab = 'simplebench';
    foreach ( $tabbar as $tab => $name ) {
        if (@isset($_GET[ $tab ]) ) {
            $tabbar_tab = $tab;
        }
    }

    // tab divs
    $class_simple = 'hidden';
    $class_advanced = 'hidden';

    // select tab and perform actions for that tab

    /* SIMPLE BENCHMARK */
    if ($tabbar_tab === 'simplebench' ) {
        // required library
        activate_library('disk');

        // simple benchmarking
        $class_simple = 'normal';
        $disk_bench = @$_GET[ 'simplebench' ];
        $disk_bench_file = $guru[ 'docroot' ]
        . '/benchmarks/simplebench_' . $disk_bench . '.png';

        // get a list of all files stored in benchmark dir related to simple_bench
        $lastdate = 0;
        exec('/usr/bin/find ' . $guru[ 'docroot' ] . '/benchmarks/ -type f', $output);
        foreach ( $output as $line ) {
            if ((substr(@basename($line), 0, strlen('simplebench_')) == 'simplebench_') && ($mtime = filemtime(
                    $line
                )) > $lastdate) {
                    $lastdate = $mtime;
                }
        }
        $curdate = time();
        $diff = $curdate - $lastdate;

        // check whether benchmark in progress
        $bip = false;
        if ($diff < 30 ) {
            $bip = true;
        }
        if (@isset($_GET[ 'benchrunning' ]) ) {
            $bip = true;
        }

        // simple benchmark in progress
        $class_simplebench = 'hidden';
        if ($bip ) {
            // set automatic refresh
            $refresh_sec = 4;
            page_refreshinterval(
                $refresh_sec,
                'disks.php?benchmark&simplebench=' . $disk_bench 
            );
            // visible classes
            $class_simplebench = 'normal';
        }

        // clear the statcache
        clearstatcache();

        // classes
        $class_nodisk = ( !$disk_bench ) ? 'normal' : 'hidden';
        $class_benchimage = ( file_exists($disk_bench_file) ) ? 'normal' : 'hidden';
        $class_benchrunning = ( $bip ) ? 'normal' : 'hidden';
        $class_benchsubmit = ( !$bip AND $disk_bench ) ? 'normal' : 'hidden';

        // disk list
        $physdisks = disk_detect_physical();

        // selected disk
        $disk_bench = @$_GET[ 'simplebench' ];

        // disk table
        $table_disks = array();
        foreach ( $physdisks as $disk ) {
            // detect disk type
            $disktype = disk_detect_type($disk[ 'disk_name' ]);

            // classes
            $class_active = ( $disk[ 'disk_name' ] == $disk_bench ) ? 'activerow' : 'normal';
            $class_hdd = ( $disktype === 'hdd' ) ? 'normal' : 'hidden';
            $class_ssd = ( $disktype === 'ssd' ) ? 'normal' : 'hidden';
            $class_flash = ( $disktype === 'flash' ) ? 'normal' : 'hidden';
            $class_memdisk = ( $disktype === 'memdisk' ) ? 'normal' : 'hidden';
            $class_usbstick = ( $disktype === 'usbstick' ) ? 'normal' : 'hidden';
            $class_network = ( $disktype === 'network' ) ? 'normal' : 'hidden';

            $table_disks[] = @array(
            'CLASS_ACTIVEROW' => $class_active,
            'CLASS_HDD' => $class_hdd,
            'CLASS_SSD' => $class_ssd,
            'CLASS_FLASH' => $class_flash,
            'CLASS_MEMDISK' => $class_memdisk,
            'CLASS_USBSTICK' => $class_usbstick,
            'CLASS_NETWORK' => $class_network,
            'DISK_NAME' => htmlentities($disk[ 'disk_name' ]),
            'DISK_SIZE' => sizebinary($disk[ 'mediasize' ], 1)
            );
        }
    }

    /* ADVANCED BENCHMARK */
    elseif ($tabbar_tab === 'advancedbench' ) {
        // advanced benchmarking
        $class_advanced = 'normal';

        // livecd performance warning
        if (common_distribution_type() === 'livecd' ) {
            page_feedback(
                'LiveCD detected, ZFS performance is severely restricted '
                . 'on the LiveCD! Consider installing Root-on-ZFS first.', 'a_warning' 
            );
        }

        // benchmark output file
        $benchpath = $guru[ 'docroot' ] . 'benchmarks/';
        $outputfile = $benchpath . 'benchmarkoutput.dat';
        $benchoutput = @htmlentities(file_get_contents($outputfile));
        $refresh_sec = 20;

        // check whether benchmark in progress
        $bip = service_isprocessrunning('advanced_benchmark.php');

        // benchmark in progress
        if ($bip ) {
            // set automatic refresh
            page_refreshinterval($refresh_sec);
            // visible classes
            $class_inprogress = 'normal';
            $class_completed = 'hidden';
            $class_interrupted = 'hidden';
            $class_newbench = 'hidden';
            // display benchmark images if applicable
            $class_running_seq = ( @file_exists($benchpath . 'running_seqread.png') ) ?
            'normal' : 'hidden';
            $class_running_random = ( @file_exists($benchpath . 'running_raidtest.read.png') ) ?
            'normal' : 'hidden';
        } else {
            // required library
            activate_library('html');

            // call external function for memberdisks
            $memberdisks = html_memberdisks();

            // check whether a benchmark was completed
            $completed = false;
            if (( @file_exists($benchpath . 'bench_seqread.png') )OR( @file_exists($benchpath . 'bench_raidtest.read.png') ) ) {
                $completed = true;
            }

            // check whether a benchmark was interrupted
            $interrupted = false;
            if (( @file_exists($benchpath . 'running_seqread.png') )OR( @file_exists($benchpath . 'running_raidtest.read.png') ) ) {
                $interrupted = true;
            } elseif (( !$completed )AND($benchoutput !== '') ) {
                $interrupted = true;
            }

            // visible classes
            $class_inprogress = 'hidden';
            $class_completed = ( $completed ) ? 'normal' : 'hidden';
            $class_interrupted = ( $interrupted ) ? 'normal' : 'hidden';
            $class_newbench = 'normal';
        }

        // display benchmark images if applicable
        $class_bench_seq = ( @file_exists($benchpath . 'bench_seqread.png') ) ?
        'normal' : 'hidden';
        $class_bench_random = ( @file_exists($benchpath . 'bench_raidtest.read.png') ) ?
        'normal' : 'hidden';
    }

    // export new tags
    return @array(
    'PAGE_ACTIVETAB' => 'Benchmark',
    'PAGE_TITLE' => 'Benchmark',
    'PAGE_TABBAR' => $tabbar,
    'PAGE_TABBAR_URL' => $tabbar_url,
    'PAGE_TABBAR_URLTAB' => $tabbar_url . '&' . $tabbar_tab,
    'TABLE_DISKS' => $table_disks,
    'CLASS_SIMPLE' => $class_simple,
    'CLASS_SIMPLEBENCH' => $class_simplebench,
    'CLASS_NODISK' => $class_nodisk,
    'CLASS_BENCHRUNNING' => $class_benchrunning,
    'CLASS_BENCHIMAGE' => $class_benchimage,
    'CLASS_BENCHSUBMIT' => $class_benchsubmit,
    'CLASS_ADVANCED' => $class_advanced,
    'CLASS_INPROGRESS' => $class_inprogress,
    'CLASS_COMPLETED' => $class_completed,
    'CLASS_INTERRUPTED' => $class_interrupted,
    'CLASS_NEWBENCHMARK' => $class_newbench,
    'CLASS_BENCH_SEQ' => $class_bench_seq,
    'CLASS_BENCH_RANDOM' => $class_bench_random,
    'CLASS_RUNNING_SEQ' => $class_running_seq,
    'CLASS_RUNNING_RANDOM' => $class_running_random,
    'DISK_BENCH' => $disk_bench,
    'BENCHMARK_OUTPUT' => $benchoutput,
    'BENCHMARK_MEMBERDISKS' => @$memberdisks
    );
}

function submit_disks_benchmark_simple() 
{
    global $guru;

    // required library
    activate_library('disk');
    activate_library('super');

    // disk to benchmark
    $disk = false;
    foreach ( $_POST as $name => $value ) {
        if (strpos($name, 'simplebench_submit_') === 0) {
            $disk = substr($name, strlen('simplebench_submit_'));
        }
    }

    // sanity
    if (strlen($disk) < 2 ) {
        error('no valid disk submitted');
    }

    // diskinfo
    $diskinfo = disk_info($disk);

    // benchmark disk
    $result = super_execute(
        $guru[ 'docroot' ] . '/scripts/benchmark_simple.php '
        . $disk . ' ' . $diskinfo[ 'mediasize' ]
        . ' > /dev/null &' 
    );
    //  );

    page_feedback(
        'benchmark is running!'
        . '<br />' . nl2br($result[ 'output_str' ]), 'c_notice' 
    );

    // redirect back again so user can view the results so far
    $url = 'disks.php?benchmark&simplebench=' . $disk . '&benchrunning';
    redirect_url($url);
}

function submit_disks_benchmark_start()
{
    global $guru;

    // required libraries
    activate_library('disk');
    activate_library('service');
    activate_library('super');

    // fetch current system version
    $currentver = common_systemversion();

    // redirect url
    $url = 'disks.php?benchmark&advancedbench';

    // construct data array
    $data = array( 'disks' => array() );
    $data[ 'magic_string' ] = $guru[ 'benchmark_magic_string' ];
    $len = strlen('addmember_');
    foreach ( @$_POST as $name => $value ) {
        if ((strpos($name, 'addmember_') === 0)AND( $value === 'on' ) ) {
            $data[ 'disks' ][] = trim(substr($name, $len));
        }
    }
    if (@$_POST[ 'test_seq' ] === 'on' ) {
        $data[ 'tests' ][ 'sequential' ] = true;
    }
    if (@$_POST[ 'test_rio' ] === 'on' ) {
        $data[ 'tests' ][ 'randomio' ] = true;
    }

    // sanity
    if (empty($data[ 'disks' ]) ) {
        friendlyerror('no disks were selected for testing!', $url);
    }
    if (( !@$data[ 'tests' ][ 'sequential' ] )AND( !@$data[ 'tests' ][ 'randomio' ] ) ) {
        friendlyerror(
            'no tests were selected, please select at least one test!',
            $url 
        );
    }

    // check disks for size and existing geom_nop providers
    foreach ( $data[ 'disks' ] as $diskname ) {
        // check disks for size (should be higher than test size + 10 megabyte margin)
        $diskinfo = disk_info($diskname);
        if ($diskinfo[ 'mediasize' ] <        ( ( ( double )$_POST[ 'testsize_gib' ] + 0.01 ) * 1024 * 1024 * 1024 ) 
        ) {
            error(
                'disk ' . $diskname . ' is too small for the chosen test size ('
                . $_POST[ 'testsize_gib' ] . ' GiB)' 
            );
        }
        // check disks for geom nop providers and destroy them if applicable
        if (file_exists('/dev/' . $diskname . '.nop') ) {
            super_execute('/sbin/gnop destroy /dev/' . $diskname . '.nop');
        }
    }

    // data array
    $data[ 'testsize_gib' ] = @$_POST[ 'testsize_gib' ];
    $data[ 'testrounds' ] = ( int )$_POST[ 'testrounds' ];
    $data[ 'cooldown' ] = ( int )$_POST[ 'cooldown' ];
    $data[ 'seq_blocksize' ] = ( int )$_POST[ 'seq_blocksize' ];
    $data[ 'rio_requests' ] = ( int )$_POST[ 'rio_requests' ];
    $data[ 'rio_scalezvol' ] = ( @$_POST[ 'rio_scalezvol' ] ) ? true : false;
    $data[ 'rio_alignment' ] = ( int )$_POST[ 'rio_alignment' ];
    $data[ 'rio_queuedepth' ] = ( int )$_POST[ 'rio_queuedepth' ];
    $data[ 'sectorsize_override' ] = ( int )$_POST[ 'sectorsize_override' ];
    $data[ 'secure_erase' ] = @$_POST[ 'secure_erase' ] == 'on';

    // kill powerd daemon for accurate frequency scanning
    service_manage_rc('powerd', 'stop', true);
    usleep(1000);

    // sysinfo
    $data[ 'sysinfo' ] = array();
    $data[ 'sysinfo' ][ 'product_name' ] = $guru[ 'product_name' ];
    $data[ 'sysinfo' ][ 'product_version' ] = $guru[ 'product_version_string' ];
    $data[ 'sysinfo' ][ 'distribution' ] = $currentver[ 'dist' ];
    $data[ 'sysinfo' ][ 'system_version' ] = $currentver[ 'sysver' ];
    $data[ 'sysinfo' ][ 'cpu_name' ] = trim(shell_exec("sysctl -n hw.model"));
    $data[ 'sysinfo' ][ 'cpu_count' ] = ( int )(shell_exec("sysctl -n hw.ncpu"));
    $freq_ghz = ( int )(shell_exec("sysctl -n dev.cpu.0.freq")) / 1000;
    $data[ 'sysinfo' ][ 'cpu_freq_ghz' ] = @number_format($freq_ghz, 1);
    $physmem_gib = ( int )(shell_exec("sysctl -n hw.physmem")) / ( 1024 * 1024 * 1024 );
    $data[ 'sysinfo' ][ 'physmem_gib' ] = @number_format($physmem_gib, 1);
    $kmem_gib = ( int )(shell_exec("sysctl -n vm.kmem_size")) / ( 1024 * 1024 * 1024 );
    $data[ 'sysinfo' ][ 'kmem_gib' ] = @number_format($kmem_gib, 1);
    if (@$_SESSION[ 'loaderconf_needreboot' ] === true ) {
        $data[ 'sysinfo' ][ 'contamination' ] = true;
    } else {
        $data[ 'sysinfo' ][ 'contamination' ] = false;
    }

    // serialize and write array to file
    $serial = serialize($data);
    $filename = trim(shell_exec("realpath .")) . '/benchmarks/startbenchmark.dat';
    exec('/bin/rm ' . $filename);
    $result = file_put_contents($filename, $serial, LOCK_EX);
    if ($result === false ) {
        error('Could not write benchmark data file');
    }
    usleep(1000);

    // remove all running_ benchmark images
    super_execute('/bin/rm ' . $guru[ 'docroot' ] . '/benchmarks/running_*.png');

    // execute benchmark
    $filename2 = './benchmarks/benchmarkoutput.dat';
    exec('/bin/rm ' . $filename2);
    $command = $guru[ 'docroot' ] . '/scripts/advanced_benchmark.php startbenchmark '
    . '> ' . $filename2 . ' 2>&1 &';
    $result = super_execute($command);
    if ($result[ 'rv' ] != 0 ) {
        error('could not start benchmark process (' . ( int )$rv . ')');
    }
    sleep(1);

    // redirect
    redirect_url($url);
}

function submit_disks_benchmark_stop() 
{
    global $guru;

    // elevated privileges
    activate_library('super');

    // redirect url
    $url = 'disks.php?benchmark&advancedbench';

    // stop processes
    exec(
        '/bin/ps xw | grep "advanced_benchmark.php|raidtest" | grep -v grep',
        $output, $rv 
    );
    if ($rv == 0 ) {
        $pids = array();
        foreach ( $output as $line ) {
            if (is_numeric(substr($line, 0, strpos($line, ' '))) ) {
                $pids[] = ( int )substr($line, 0, strpos($line, ' '));
            }
        }
        foreach ( $pids as $pid ) {
            super_execute('/bin/kill ' . ( int )$pid);
        }
    }

    // oldschool kill everything
    super_execute('/usr/bin/killall advanced_benchmark.php raidtest dd');
    usleep(50000);

    // import and delete test pool as well
    super_execute('/sbin/zpool import -f ' . $guru[ 'benchmark_poolname' ]);
    usleep(1000);
    super_execute('/sbin/zpool destroy -f ' . $guru[ 'benchmark_poolname' ]);
    usleep(100000);

    // redirect
    redirect_url($url);
}
