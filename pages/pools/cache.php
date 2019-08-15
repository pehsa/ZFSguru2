<?php

function content_pools_cache() {
	// required libraries
	activate_library( 'background' );
	activate_library( 'disk' );
	activate_library( 'html' );
	activate_library( 'zfs' );

	// include stylesheet from pools page
	page_register_stylesheet( 'pages/pools/pools.css' );

	// query pool
	$querypool = @$_GET[ 'pool' ];

	// selected member disks
	if ( @$_GET[ 'members' ] )
		$selecteddisks = @unserialize( @$_GET[ 'members' ] );
	if ( !@is_array( $selecteddisks ) )
		$selecteddisks = false;

	// pool should be at least this version (SPA)
	$minimal_spa_version = 10;

	// process table poollist
	$poollist = array();
	$zpools = zfs_pool_list();
	if ( !is_array( $zpools ) )
		$zpools = array();
	foreach ( $zpools as $poolname => $pooldata ) {
		$class = ( $querypool == $poolname ) ? 'activerow' : 'normal';
		$poolspa = zfs_pool_version( $poolname );

		// cache specific part
		$class_spa_ok = ( $poolspa >= $minimal_spa_version ) ? 'normal' : 'hidden';
		$class_spa_low = ( $poolspa < $minimal_spa_version ) ? 'normal' : 'hidden';

		$zpool_status = `zpool status $poolname`;
		if ( strpos( $zpool_status, 'raidz3' ) !== false )
			$redundancy = 'RAID7 (triple parity)';
		elseif ( strpos( $zpool_status, 'raidz2' ) !== false )
			$redundancy = 'RAID6 (double parity)';
		elseif ( strpos( $zpool_status, 'raidz1' ) !== false )
			$redundancy = 'RAID5 (single parity)';
		elseif ( strpos( $zpool_status, 'mirror' ) !== false )
			$redundancy = 'RAID1 (mirroring)';
		else
			$redundancy = 'RAID0 (no redundancy)';
		$statusclass = 'normal';
		if ( $pooldata[ 'status' ] == 'ONLINE' )
			$statusclass = 'green pool_online';
		elseif ( $pooldata[ 'status' ] == 'FAULTED' ) {
			$statusclass = 'red pool_faulted';
			if ( $class == 'normal' )
				$class = 'failurerow pool_faulted';
		}
		elseif ( $pooldata[ 'status' ] == 'DEGRADED' ) {
			$statusclass = 'amber pool_degraded';
			if ( $class == 'normal' )
				$class = 'warningrow pool_degraded';
		}
		$poollist[] = array(
			'POOLLIST_CLASS' => $class,
			'POOLLIST_POOLNAME' => htmlentities( trim( $poolname ) ),
			'POOLLIST_SPA' => $poolspa,
			'POOLLIST_SPA_OK' => $class_spa_ok,
			'POOLLIST_SPA_LOW' => $class_spa_low,
			'POOLLIST_REDUNDANCY' => $redundancy,
			'POOLLIST_SIZE' => $pooldata[ 'size' ],
			'POOLLIST_USED' => $pooldata[ 'used' ],
			'POOLLIST_FREE' => $pooldata[ 'free' ],
			'POOLLIST_STATUS' => $pooldata[ 'status' ],
			'POOLLIST_STATUSCLASS' => $statusclass,
			'POOLLIST_POOLNAME_URLENC' => htmlentities( trim( $poolname ) )
		);
	}

	// check whether pool is healthy when selected
	if ( $querypool )
		if ( ( $zpools[ $querypool ][ 'status' ] != 'ONLINE' )AND( $zpools[ $querypool ][ 'status' ] != 'DEGRADED' ) )
			friendlyerror( 'pool <b>' . $querypool . '</b> can not be used, because the '
				. 'pool is <b>' . $zpools[ $querypool ][ 'status' ] . '</b>!', 'pools.php?cache' );

		// member disks
	$memberdisks = html_memberdisks();

	// performance test
	$performancetested = true;
	$benchmarkrunning = false;
	$performancetest = $selecteddisks;
	$minimumscore = 1000;
	$pquery = background_query( 'pool_cache_benchmark' );

	// performance table
	$table_performance = array();
	$slowdevice = false;
	$combinedsize = 0;
	if ( is_array( $performancetest ) )
		foreach ( $performancetest as $device ) {
			$outputarr = explode( chr( 10 ), @$pquery[ 'ctag' ][ $device ][ 'stdout' ] );
			$oarr = preg_split( '/ +/m', @$outputarr[ 2 ] );
			$score = ( int )@$oarr[ 2 ];
			if ( ( $score == 0 )AND( strlen( @$outputarr[ 2 ] ) > 0 ) ) {
				$testrunning = true;
				$benchmarkrunning = true;
			} else
				$testrunning = false;
			$diskinfo = disk_info( '/dev/' . $device );
			if ( $score AND $score < $minimumscore )
				$slowdevice = true;
			if ( $score < 1 )
				$performancetested = false;
			$scorefactor = $score / 60;
			if ( $scorefactor > 32 )
				$scorefactor = round( $score / 60 );
			else
				$scorefactor = round( $score / 60, 1 );
			$combinedsize += @$diskinfo[ 'mediasize' ];
			$table_performance[] = array(
				'CLASS_TESTED_OK' => ( $score > $minimumscore ) ? 'normal' : 'hidden',
				'CLASS_TESTED_SLOW' => ( $score < $minimumscore AND $score ) ?
				'normal' : 'hidden',
				'CLASS_TESTED_TEST' => ( !$score AND!$testrunning ) ? 'normal' : 'hidden',
				'CLASS_TESTED_RUN' => ( $testrunning ) ? 'normal' : 'hidden',
				'PERF_DISKNAME' => htmlentities( $device ),
				'PERF_SIZEBINARY' => sizebinary( @$diskinfo[ 'mediasize' ], 1 ),
				'PERF_SCORE' => $score,
				'PERF_HDDCOMPARE' => $scorefactor
			);
		}
	else
		$performancetested = false;

	// combined l2arc size
	$totalsize = sizebinary( $combinedsize, 1 );
	$l2arc_memoryuse_ratio = 40;
	$memreq = sizebinary( $combinedsize / $l2arc_memoryuse_ratio, 1 );
	$ramsize = common_sysctl( 'hw.physmem' );
	$ramfactor = 8;

	// classes
	$class_moreinfo = ( @isset( $_GET[ 'explain' ] ) ) ? 'normal' : 'hidden';
	$class_step2 = ( $querypool ) ? 'normal' : 'hidden';
	$class_step2_lowmem = ( $ramsize / $ramfactor < $memreq ) ? 'normal' : 'hidden';
	$class_step3 = ( $querypool AND $selecteddisks ) ? 'normal' : 'hidden';
	$class_step3_fast = ( !$slowdevice AND $performancetested ) ? 'normal' : 'hidden';
	$class_step3_slow = ( $slowdevice ) ? 'normal' : 'hidden';
	$class_step3_test = ( !$performancetested AND!$benchmarkrunning ) ?
		'normal' : 'hidden';
	$class_step3_testing = ( $benchmarkrunning ) ? 'normal' : 'hidden';
	$class_step4 = ( $performancetested ) ? 'normal' : 'hidden';
	$class_step2_now = ( $class_step2 == 'normal'
			AND $class_step3 != 'normal' ) ?
		'normal' : 'hidden';

	// set automatic refresh when benchmark is running
	if ( $benchmarkrunning )
		page_refreshinterval( 3 );

	// export tags
	return array(
		'PAGE_ACTIVETAB' => 'Cache devices',
		'PAGE_TITLE' => 'Cache devices',
		'TABLE_POOL_POOLLIST' => $poollist,
		'TABLE_PERFORMANCE' => $table_performance,
		'CLASS_MOREINFO' => $class_moreinfo,
		'CLASS_STEP2' => $class_step2,
		'CLASS_STEP2_NOW' => $class_step2_now,
		'CLASS_STEP2_LOWMEM' => $class_step2_lowmem,
		'CLASS_STEP3' => $class_step3,
		'CLASS_STEP3_TEST' => $class_step3_test,
		'CLASS_STEP3_TESTING' => $class_step3_testing,
		'CLASS_STEP3_FAST' => $class_step3_fast,
		'CLASS_STEP3_SLOW' => $class_step3_slow,
		'CLASS_STEP4' => $class_step4,
		'QUERYPOOL' => $querypool,
		'MEMBERDISKS' => $memberdisks,
		'SELECTEDDEVICES' => @htmlentities( $_GET[ 'members' ] ),
		'TOTALSIZE' => $totalsize,
		'MEMREQ' => $memreq
	);
}

function submit_pool_cache() {
	global $guru;

	// redirect URL
	$url1 = 'pools.php?cache';
	$url2 = $url1;
	if ( @strlen( $_POST[ 'pool' ] ) > 0 )
		$url2 .= '&pool=' . $_POST[ 'pool' ];

	// selected member disks (unserialized; step 2)
	$selecteddisks = array();
	if ( @isset( $_POST[ 'select_memberdisks' ] ) )
		foreach ( $_POST as $name => $value )
			if ( substr( $name, 0, strlen( 'addmember_' ) ) == 'addmember_' )
				$selecteddisks[] = substr( $name, strlen( 'addmember_' ) );
	if ( count( $selecteddisks ) > 0 ) {
		// remove selected disk performance data
		activate_library( 'background' );
		background_remove( 'pool_cache_benchmark' );
		// redirect with member variable as GET
		redirect_url( $url2 . '&members=' . urlencode( serialize( $selecteddisks ) ) );
	}

	// sanity check on pool
	$pool = @$_POST[ 'pool' ];
	if ( strlen( $pool ) < 1 )
		friendlyerror( 'please select a pool first', $url1 );

	// selected devices (serialized; beyond step 2)
	$selecteddevices = @unserialize( $_POST[ 'selecteddevices' ] );
	if ( !is_array( $selecteddevices )OR( count( $selecteddevices ) < 1 ) )
		friendlyerror( 'please select at least one device', $url2 );
	$url2 .= '&members=' . urlencode( serialize( $selecteddevices ) );

	// perform benchmark
	$number = 16384 * 8;
	if ( @isset( $_POST[ 'perform_benchmark' ] )and( count( $selecteddevices ) > 0 ) ) {
		// check whether rawio is available
		$rawio = $guru[ 'docroot' ] . '/files/rawio';
		if ( !file_exists( $rawio ) )
			friendlyerror( 'performance test is unavailable - rawio binary is missing',
				$url2 );
		// set commands to execute
		$commands = array();
		foreach ( $selecteddevices as $device )
			$commands[ $device ] = $rawio
			. ' -R -A 4096 -c 4096 -n ' . $number . ' /dev/' . $device;
		// perform random read benchmark on selected devices
		activate_library( 'background' );
		background_register( 'pool_cache_benchmark', array(
			'commands' => $commands,
			'super' => true,
		) );
	}

	// add cache device(s) to pool
	if ( @isset( $_POST[ 'add_l2arc' ] ) ) {
		$command = array();
		// remove selected disk performance data
		activate_library( 'background' );
		background_remove( 'pool_cache_benchmark' );
		// TRIM erase selected devices when applicable
		if ( @$_POST[ 'cb_trim_erase' ] == 'on' )
			foreach ( $selecteddevices as $device )
				$command[] = $guru[ 'docroot' ] . '/scripts/secure_erase.sh ' . $device;
		// finally add devices as L2ARC cache to pool
		$command[] = '/sbin/zpool add ' . $pool . ' cache '
		. implode( ' ', $selecteddevices );
		// defer to dangerouscommand function
		dangerouscommand( $command, $url1 );
	}

	// redirect
	redirect_url( $url2 );
}