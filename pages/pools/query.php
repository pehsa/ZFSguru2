<?php

function content_pools_query() {
	// required libraries
	activate_library( 'html' );
	activate_library( 'zfs' );

	// include stylesheet from pools page
	page_register_stylesheet( 'pages/pools/pools.css' );

	// process table poollist
	$poollist = array();
	$zpools = zfs_pool_list();
	if ( !is_array( $zpools ) )
		$zpools = array();
	foreach ( $zpools as $poolname => $pooldata ) {
		$class = ( @$_GET[ 'query' ] == $poolname ) ? 'activerow' : 'normal';
		$poolspa = zfs_pool_version( $poolname );
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
			'POOLLIST_REDUNDANCY' => $redundancy,
			'POOLLIST_SIZE' => $pooldata[ 'size' ],
			'POOLLIST_USED' => $pooldata[ 'used' ],
			'POOLLIST_FREE' => $pooldata[ 'free' ],
			'POOLLIST_STATUS' => $pooldata[ 'status' ],
			'POOLLIST_STATUSCLASS' => $statusclass,
			'POOLLIST_POOLNAME_URLENC' => htmlentities( trim( $poolname ) )
		);
	}

	// pool count
	$poolcount = count( $zpools );
	$poolcountstr = ( $poolcount == 1 ) ? '' : 's';

	// querypool
	$querypool = @trim( $_GET[ 'query' ] );
	$pool_esc = @htmlentities( $querypool );

	// call functions
	$scrub = zfs_pool_isbeingscrubbed( $querypool );
	$pool = zfs_pool_status( $querypool, '-v' );
	$ashift = zfs_pool_ashift( $querypool );
	$memberdetails = zfs_pool_memberdetails( $pool, $querypool );
	$memberdisks_select = html_memberdisks_select();

	// make some data bold by adding HTML tags
	$regexp = array(
		'/([0-9]{1,4}(\.[0-9]{1,2})?[KMGT]\/s),/',
		'/ ([0-9]+\.[0-9]+%) done/'
	);
	$repl = array(
		'<b>$1</b>,',
		' <b>$1</b> done'
	);
	$pool[ 'scrub' ] = preg_replace( $regexp, $repl, $pool[ 'scrub' ] );

	// suppress warning about upgrading pool
	$suppresstext = 'The pool is formatted using a';
	if ( substr( @$pool[ 'status' ], 0, strlen( $suppresstext ) ) == $suppresstext ) {
		$pool[ 'status' ] = '';
		$pool[ 'action' ] = '';
	}
	// suppress warning about pool features
	$suppresstext = 'Some supported features are not enabled on the pool';
	if ( substr( @$pool[ 'status' ], 0, strlen( $suppresstext ) ) == $suppresstext ) {
		$pool[ 'status' ] = '';
		$pool[ 'action' ] = '';
	}

	// pool details
	$pooldetails = array();
	$detailvars = array(
		'Status' => 'state',
		'Description' => 'status',
		'Action' => 'action',
		'See' => 'see',
		'Scrub' => 'scrub',
		'Config' => 'config'
	);
	$statusclass = poolquery_statusclass( @$pool[ 'state' ] );
	foreach ( $detailvars as $name => $var )
		if ( strlen( @$pool[ $var ] ) > 0 )
			$pooldetails[ $name ] = array(
				'POOLDETAILS_CLASS' => ( $var == 'state' ) ? $statusclass : 'normal',
				'POOLDETAILS_NAME' => $name,
				'POOLDETAILS_VALUE' => ( $var == 'scrub' ) ? nl2br( $pool[ $var ] ) : $pool[ $var ],
			);

		// ashift value
	if ( ( int )$ashift > 0 ) {
		$ashift_sectorsize = pow( 2, $ashift );
		$pooldetails[] = array(
			'POOLDETAILS_NAME' => 'Ashift',
			'POOLDETAILS_VALUE' => 'pool is optimized for '
			. sizebinary( $ashift_sectorsize ) . ' sector disks (ashift=' . $ashift . ')'
		);
	}

	// scrub status
	if ( $scrub ) {
		$scrubname = 'Stop';
		$scrubaction = 'stop';
	} else {
		$scrubname = 'Start';
		$scrubaction = 'start';
	}

	// process members table
	$memberdisks = array();
	if ( is_array( $pool[ 'members' ] ) )
		foreach ( $pool[ 'members' ] as $id => $member ) {
			$special = ( bool )@$memberdetails[ $id ][ 'special' ];
			$vdevtype = @$memberdetails[ $id ][ 'type' ];
			// done with vdev type now set attributes
			$pooldev = ( $member[ 'name' ] == $querypool ) ? true : false;
			$normaldisk = ( !$special AND!$pooldev );
			if ( $member[ 'name' ] == $querypool )
				$mem_class = 'darkrow member_main';
			if ( $normaldisk ) {
				if ( @$member[ 'state' ] == 'ONLINE' )
					$mem_class = 'member_normal';
				if ( @$member[ 'state' ] == 'DEGRADED'
					OR @$member[ 'state' ] == 'OFFLINE' )
					$mem_class = 'warningrow member_degraded';
				if ( @$member[ 'state' ] == 'FAULTED'
					OR @$member[ 'state' ] == 'UNAVAIL' )
					$mem_class = 'failurerow member_faulted';
				$mem[ 'online' ] = ( @$member[ 'state' ] == 'OFFLINE' ) ? 'normal' : 'hidden';
				$mem[ 'offline' ] = ( @$member[ 'state' ] == 'ONLINE'
						AND $vdevtype != 'stripe' ) ?
					'normal' : 'hidden';
				$mem[ 'attach' ] = ( $vdevtype == 'stripe' ) ? 'normal' : 'hidden';
				$mem[ 'detach' ] = ( $vdevtype == 'mirror' ) ? 'normal' : 'hidden';
				$mem[ 'replace' ] = 'normal';
				$mem[ 'clear' ] = ( ( int )@$member[ 'read' ] != 0 OR( int )@$member[ 'write' ] != 0 OR( int )@$member[ 'cksum' ] != 0 ) ? 'normal' : 'hidden';
				$mem[ 'remove' ] = ( $vdevtype == 'cache'
					OR $vdevtype == 'log'
					OR $vdevtype == 'hot spares' ) ? 'normal' : 'hidden';
			} else {
				// toplevel vdev (mirror, raidz)
				if ( $pooldev )
					$mem_class = 'darkrow member_main';
				elseif ( @$member[ 'state' ] == 'ONLINE' )
					$mem_class = 'specialrow member_special';
				elseif ( @$member[ 'state' ] == 'DEGRADED'
					OR @$member[ 'state' ] == 'OFFLINE' )
					$mem_class = 'warningrow member_degraded';
				elseif ( @$member[ 'state' ] == 'FAULTED'
					OR @$member[ 'state' ] == 'UNAVAIL' )
					$mem_class = 'failurerow member_faulted';
				$mem[ 'online' ] = 'hidden';
				$mem[ 'offline' ] = 'hidden';
				$mem[ 'attach' ] = 'hidden';
				$mem[ 'detach' ] = 'hidden';
				$mem[ 'replace' ] = 'hidden';
				$mem[ 'clear' ] = 'hidden';
				$mem[ 'remove' ] = 'hidden';
			}
			// hide actiondiv if no options present
			//   $mem_actiondiv = (strlen($member['state']) > 0) ? 'normal' : 'hidden';
			$mem_actiondiv = 'hidden';
			foreach ( $mem as $value )
				if ( $value == 'normal' ) {
					$mem_actiondiv = 'normal';
					break;
				}

				// detect disk type
			$disktype = disk_detect_type( $member[ 'name' ] );
			$specialtypes = array( 'mirror', 'raidz', 'cache', 'log', 'spare' );
			foreach ( $specialtypes as $stype )
				if ( $special AND( strpos( $member[ 'name' ], $stype ) !== false ) )
					$disktype = $stype;

			if ( $member[ 'name' ] == $querypool )
				$disktype = 'pool';

			// disk classes
			$class_hdd = ( $disktype == 'hdd' ) ? 'normal' : 'hidden';
			$class_ssd = ( $disktype == 'ssd' ) ? 'normal' : 'hidden';
			$class_flash = ( $disktype == 'flash' ) ? 'normal' : 'hidden';
			$class_memdisk = ( $disktype == 'memdisk' ) ? 'normal' : 'hidden';
			$class_usbstick = ( $disktype == 'usbstick' ) ? 'normal' : 'hidden';
			$class_network = ( $disktype == 'network' ) ? 'normal' : 'hidden';
			// extra classes
			$class_pool = ( $disktype == 'pool' ) ? 'normal' : 'hidden';
			$class_mirror = ( $disktype == 'mirror' ) ? 'normal' : 'hidden';
			$class_raidz = ( $disktype == 'raidz' ) ? 'normal' : 'hidden';
			$class_cache = ( $disktype == 'cache' ) ? 'normal' : 'hidden';
			$class_log = ( $disktype == 'log' ) ? 'normal' : 'hidden';
			$class_spare = ( $disktype == 'spare' ) ? 'normal' : 'hidden';

			// member state class
			$class_state = poolquery_statusclass( @$member[ 'state' ] );

			// all data ready, add a new row to the memberdisks table
			$memberdisks[] = array(
				'CLASS_HDD' => $class_hdd,
				'CLASS_SSD' => $class_ssd,
				'CLASS_FLASH' => $class_flash,
				'CLASS_MEMDISK' => $class_memdisk,
				'CLASS_USBSTICK' => $class_usbstick,
				'CLASS_NETWORK' => $class_network,
				'CLASS_POOL' => $class_pool,
				'CLASS_MIRROR' => $class_mirror,
				'CLASS_RAIDZ' => $class_raidz,
				'CLASS_CACHE' => $class_cache,
				'CLASS_LOG' => $class_log,
				'CLASS_SPARE' => $class_spare,
				'CLASS_STATE' => $class_state,
				'MEMBER_CLASS' => $mem_class,
				'MEMBER_NAME' => @htmlentities( $member[ 'name' ] ),
				'MEMBER_STATE' => @htmlentities( $member[ 'state' ] ),
				'MEMBER_READ' => @htmlentities( $member[ 'read' ] ),
				'MEMBER_WRITE' => @htmlentities( $member[ 'write' ] ),
				'MEMBER_CHECKSUM' => @htmlentities( $member[ 'cksum' ] ),
				'MEMBER_HASEXTRA' => ( @$member[ 'extra' ] ) ? 'normal' : 'hidden',
				'MEMBER_EXTRA' => @htmlentities( $member[ 'extra' ] ),
				'MEMBER_ACTIONDIV' => $mem_actiondiv,
				'MEMBER_ONLINE' => $mem[ 'online' ],
				'MEMBER_OFFLINE' => $mem[ 'offline' ],
				'MEMBER_ATTACH' => $mem[ 'attach' ],
				'MEMBER_DETACH' => $mem[ 'detach' ],
				'MEMBER_REPLACE' => $mem[ 'replace' ],
				'MEMBER_CLEAR' => $mem[ 'clear' ],
				'MEMBER_REMOVE' => $mem[ 'remove' ]
			);
		}

	// pool errors
	// corrupted files list
	$class_corrupted = 'hidden';
	$class_corruptmore = ( !@isset( $_GET[ 'corruptedfiles' ] ) ) ? 'normal' : 'hidden';
	$class_corruptless = ( @isset( $_GET[ 'corruptedfiles' ] ) ) ? 'normal' : 'hidden';
	$table_corrupted = array();
	$corrupt_count = @count( $pool[ 'errors' ] );
	if ( @count( $pool[ 'errors' ] ) > 0 ) {
		$class_corrupted = 'normal';
		foreach ( $pool[ 'errors' ] as $corruptedfile )
			$table_corrupted[] = array(
				'CORRUPT_FILENAME' => $corruptedfile
			);
		if ( $class_corruptmore == 'normal' )
			$table_corrupted = array_splice( $table_corrupted, 0, 3 );
	}

	// pool feature flags
	$table_features = array();
	$featureflags = zfs_pool_features( $querypool );
	if ( @is_array( $featureflags[ $querypool ] ) )
		foreach ( $featureflags[ $querypool ] as $flag )
			$table_features[] = array(
				'FEAT_CLASS' => ( $flag[ 'status' ] == 'disabled' ) ? 'normal' : 'hidden',
				'FEAT_NAME' => htmlentities( $flag[ 'name' ] ),
				'FEAT_DESC' => htmlentities( $flag[ 'desc' ] ),
				'FEAT_ENABLED' => ( $flag[ 'status' ] == 'enabled' ) ? 'normal' : 'hidden',
				'FEAT_ACTIVE' => ( $flag[ 'status' ] == 'active' ) ? 'normal' : 'hidden',
				'FEAT_DISABLED' => ( $flag[ 'status' ] == 'disabled' ) ? 'normal' : 'hidden',
				'FEAT_STATUS' => ucfirst( $flag[ 'status' ] ),
			);

	// pool history
	$poolhistory = array();
	$historyall = ( @isset( $_GET[ 'history' ] ) ) ? true : false;
	$historymore = 'hidden';
	$historyless = 'hidden';
	$maxhistory_items = 3;
	$historycount = 0;
	if ( $querypool ) {
		$history = zfs_pool_history( $querypool );
		$history = array_reverse( $history );
		$historymore = ( ( count( $history ) > $maxhistory_items )AND( !$historyall ) ) ?
			'normal' : 'hidden';
		$historyless = ( $historyall ) ? 'normal' : 'hidden';
		foreach ( $history as $data )
			if ( ( ++$historycount <= $maxhistory_items )OR $historyall )
				$poolhistory[] = array(
					'HISTORY_DATE' => $data[ 'date' ],
					'HISTORY_TIME' => $data[ 'time' ],
					'HISTORY_EVENT' => $data[ 'event' ]
				);
	}

	// export tags
	return array(
		'PAGE_ACTIVETAB' => 'Pool status',
		'PAGE_TITLE' => 'Pool ' . $querypool,
		'TABLE_POOL_LIST' => $poollist,
		'TABLE_POOL_DETAILS' => $pooldetails,
		'TABLE_CORRUPTED' => $table_corrupted,
		'TABLE_POOL_FEATURES' => $table_features,
		'TABLE_POOL_HISTORY' => $poolhistory,
		'TABLE_MEMBERDISKS' => $memberdisks,
		'CLASS_CORRUPTED' => $class_corrupted,
		'CLASS_CORRUPTMORE' => $class_corruptmore,
		'CLASS_CORRUPTLESS' => $class_corruptless,
		'CLASS_HISTORYMORE' => $historymore,
		'CLASS_HISTORYLESS' => $historyless,
		'MEMBERDISKS_SELECT' => $memberdisks_select,
		'POOL_COUNT' => $poolcount,
		'POOL_COUNT_STRING' => $poolcountstr,
		'QUERY_POOLNAME' => $pool_esc,
		'QUERY_POOLSTATUS' => @htmlentities( $pool[ 'state' ] ),
		'QUERY_DESCRIPTION' => @htmlentities( $pool[ 'status' ] ),
		'QUERY_ACTION' => @htmlentities( $pool[ 'action' ] ),
		'QUERY_SEE' => @htmlentities( $pool[ 'see' ] ),
		'QUERY_SCRUB' => @htmlentities( $pool[ 'scrub' ] ),
		'QUERY_CONFIG' => @htmlentities( $pool[ 'config' ] ),
		'QUERY_SCRUBACTION' => $scrubaction,
		'QUERY_SCRUBNAME' => $scrubname,
		'QUERY_ASHIFT' => $ashift,
		'CORRUPT_COUNT' => $corrupt_count
	);
}

function poolquery_statusclass( $status )
// returns CSS class for given status (ONLINE,DEGRADED,FAULTED,UNAVAIL)
{
	if ( $status == 'ONLINE' )
		$statusclass = 'green status_online';
	elseif ( $status == 'FAULTED' )
		$statusclass = 'red status_faulted';
	elseif ( $status == 'UNAVAIL' )
		$statusclass = 'red status_faulted';
	elseif ( $status == 'DEGRADED' )
		$statusclass = 'amber status_degraded';
	elseif ( $status == 'OFFLINE' )
		$statusclass = 'amber status_degraded';
	else
		$statusclass = 'blue normal';
	return $statusclass;
}

function submit_pools_vdev() {
	// pool we are working on
	$poolname = @$_POST[ 'poolname' ];
	$url = 'pools.php?query=' . $poolname;

	// required libary
	activate_library( 'zfs' );

	// check whether to start or stop a running scrub
	if ( @$_POST[ 'pool_startscrub' ] )
		zfs_pool_scrub( $poolname );
	elseif ( @$_POST[ 'pool_stopscrub' ] )
		zfs_pool_scrub( $poolname, true );

	// scan for vdev operations
	foreach ( $_POST as $name => $value )
		if ( substr( $name, 0, strlen( 'member_action_' ) ) == 'member_action_' ) {
			$vdev = substr( $name, strlen( 'member_action_' ) );
			$action = $value;
			if ( $action == 'offline' )
				dangerouscommand( '/sbin/zpool offline ' . $poolname . ' ' . $vdev, $url );
			elseif ( $action == 'online' )
				dangerouscommand( '/sbin/zpool online ' . $poolname . ' ' . $vdev, $url );
			elseif ( $action == 'attach' ) {
				$newvdev = $_POST[ 'member_action_' . $vdev . '_attach' ];
				if ( !$newvdev )
					friendlyerror( 'you need to select a new disk, '
						. 'before you can use the attach feature', $url );
				$command = '/sbin/zpool attach ' . $poolname . ' ' . $vdev . ' ' . $newvdev;
				dangerouscommand( $command, $url );
			}
			elseif ( $action == 'detach' )
				dangerouscommand( '/sbin/zpool detach ' . $poolname . ' ' . $vdev, $url );
			elseif ( $action == 'clear' )
				dangerouscommand( '/sbin/zpool clear ' . $poolname . ' ' . $vdev, $url );
			elseif ( $action == 'replace' ) {
				activate_library( 'disk' );
				$newvdev = $_POST[ 'member_action_' . $vdev . '_replace' ];
				// sanity
				if ( !$newvdev )
					friendlyerror( 'you need to select a new disk, '
						. 'before you can use the replace feature', $url );
				$di_old = disk_info( $vdev );
				$di_new = disk_info( $newvdev );
				if ( $di_old[ 'mediasize' ] > $di_new[ 'mediasize' ] )
					friendlyerror( 'you cannot replace a disk with a smaller disk ('
						. $di_old[ 'mediasize' ] . ' versus ' . $di_new[ 'mediasize' ] . ' bytes)', $url );
				// ready to continue with zpool replace command
				$command = '/sbin/zpool replace ' . $poolname . ' ' . $vdev . ' ' . $newvdev;
				dangerouscommand( $command, $url );
			}
			elseif ( $action == 'remove' )
				dangerouscommand( '/sbin/zpool remove ' . $poolname . ' ' . $vdev, $url );
		}

		// redirect back to pool query page
	redirect_url( $url );
}

function submit_pools_features() {
	// variables
	$poolname = @$_POST[ 'poolname' ];
	$url = 'pools.php?query=' . $poolname;

	// scan POST data for button clicked
	$feature = false;
	foreach ( $_POST as $name => $value )
		if ( substr( $name, 0, strlen( 'enablefeat_' ) ) == 'enablefeat_' )
			$feature = substr( $name, strlen( 'enablefeat_' ) );

		// sanity
	if ( !$feature )
		friendlyerror( 'unknown feature', $url );
	if ( !$poolname )
		friendlyerror( 'unknown pool name', $url );

	// enable pool feature
	$command = '/sbin/zpool set feature@' . $feature . '=enabled '
	. escapeshellarg( $poolname );
	dangerouscommand( $command, $url );
}

function submit_pools_operations() {
	// variables
	$url1 = 'pools.php';
	sanitize( @$_POST[ 'poolname' ], null, $poolname );
	$url2 = 'pools.php?query=' . $poolname;

	// pool operations - handle with dangerouscommand function
	if ( @isset( $_POST[ 'upgrade_pool' ] ) ) {
		// fetch data
		activate_library( 'zfs' );
		$zfsver = zfs_version();
		$poolversions = zfs_pool_versions();
		$poolversion = zfs_pool_version( $poolname );
		$nr_upgrade = 0;

		// spaversions table
		$table_spaversions = array();
		foreach ( $poolversions as $nr => $desc ) {
			// handle v5000 separately
			if ( $nr > 28 )
				break;
			$upgrade = ( ( $nr > $poolversion )AND( $nr <= $zfsver[ 'spa' ] ) ) ?
				'normal' : 'hidden';
			$downgrade = ( $nr < $poolversion ) ? 'normal' : 'hidden';
			if ( $upgrade == 'normal' )
				$nr_upgrade++;
			$selected = ( $nr == $zfsver[ 'spa' ] ) ? 'checked' : '';
			$current = ( $nr == $poolversion ) ? 'normal' : 'hidden';
			$systemlow = ( $nr > $zfsver[ 'spa' ] ) ? 'normal' : 'hidden';
			$table_spaversions[] = array(
				'SPA_SELECT' => $selected,
				'SPA_VER' => $nr,
				'SPA_DESC' => $desc,
				'SPA_DOWNGRADE' => $downgrade,
				'SPA_UPGRADE' => $upgrade,
				'SPA_CURRENT' => $current,
				'SPA_SYSTEMLOW' => ( $nr > $zfsver[ 'spa' ] ) ? 'normal' : 'hidden'
			);
		}

		// upgrade classes
		$canupgrade = 'hidden';
		$cantupgrade = 'hidden';
		if ( $nr_upgrade > 0 )
			$canupgrade = 'normal';
		elseif ( $zfsver[ 'spa' ] == '5000' ) {
			$canupgrade = ( $poolversion < 5000 ) ? 'normal' : 'hidden';
			$cantupgrade = ( $poolversion == 5000 ) ? 'normal' : 'hidden';
		}

		// special row for v5000
		$v5000_upgrade = ( ( $zfsver[ 'spa' ] == 5000 )AND( $poolversion < 5000 ) ) ?
			'normal' : 'hidden';
		$v5000_select = ( ( $zfsver[ 'spa' ] == 5000 )AND( $poolversion == 28 ) ) ?
			'checked' : '';
		$v5000_current = ( $poolversion == 5000 ) ? 'normal' : 'hidden';
		$v5000_nosupport = ( $zfsver[ 'spa' ] < 5000 ) ? 'normal' : 'hidden';

		// inject tags and handle page
		page_injecttag( array( 'POOLNAME' => $poolname ) );
		page_injecttag( array( 'POOLVERSION' => $poolversion ) );
		page_injecttag( array( 'TABLE_SPAVERSIONS' => $table_spaversions ) );
		page_injecttag( array( 'CLASS_CANUPGRADE' => $canupgrade ) );
		page_injecttag( array( 'V5000_UPGRADE' => $v5000_upgrade ) );
		page_injecttag( array( 'V5000_SELECT' => $v5000_select ) );
		page_injecttag( array( 'V5000_CURRENT' => $v5000_current ) );
		page_injecttag( array( 'V5000_NOSUPPORT' => $v5000_nosupport ) );

		$content = content_handle( 'pools', 'upgrade', false, true );
		page_handle( $content );
		die();
	} elseif ( @isset( $_POST[ 'rename_pool' ] ) ) {
		page_injecttag( array( 'POOLNAME' => $poolname ) );
		$content = content_handle( 'pools', 'rename', false, true );
		page_handle( $content );
		die();
	}
	elseif ( @isset( $_POST[ 'export_pool' ] ) )
		dangerouscommand( '/sbin/zpool export ' . $poolname, $url1 );
	elseif ( @isset( $_POST[ 'destroy_pool' ] ) ) {
		// required libraries
		activate_library( 'samba' );
		activate_library( 'zfs' );

		// get list of filesystems (may return false if pool is faulted)
		$fslist = zfs_filesystem_list( $poolname, '-r -t filesystem' );
		if ( $fslist == false ) {
			$fslist = array();
			page_feedback( 'this pool does not have any active filesystems - '
				. 'unable to scan for active Samba shares!', 'a_warning' );
		}

		// remove any samba filesystem
		$sharesremoved = 0;
		foreach ( $fslist as $fsname => $fsdata )
			if ( strlen( @$fsdata[ 'mountpoint' ] ) > 1 )
				$sharesremoved += ( int )samba_removesharepath( $fsdata[ 'mountpoint' ] );

			// display message if applicable
		if ( $sharesremoved > 1 )
			page_feedback( 'removed <b>' . ( int )$sharesremoved . ' Samba shares</b> that were'
				. ' attached to the filesystems you are about to destroy', 'c_notice' );
		elseif ( $sharesremoved == 1 )
			page_feedback( 'removed <b>one Samba share</b> that was attached to one of '
				. 'the filesystems you are about to destroy', 'c_notice' );

		// start command array
		$command = array();

		// scan for swap volumes and deactivate them prior to destroying them
		$vollist = zfs_filesystem_list( $poolname, '-r -t volume' );
		exec( '/sbin/swapctl -l', $swapctl_raw );
		$swapctl = @implode( chr( 10 ), $swapctl_raw );
		if ( @is_array( $vollist ) )
			foreach ( $vollist as $volname => $voldata ) {
				$prop = zfs_filesystem_properties( $volname, 'org.freebsd:swap' );
				// check if volume is in use as a SWAP device
				if ( @$prop[ $volname ][ 'org.freebsd:swap' ][ 'value' ] == 'on' )
					if ( @strpos( $swapctl, '/dev/zvol/' . $volname ) !== false )
						$command[] = '/sbin/swapoff /dev/zvol/' . $volname;
			}
			// display message if swap volumes detected
		if ( count( $command ) == 1 )
			page_feedback( 'if you continue, one SWAP volume will be deactivated',
				'c_notice' );
		if ( count( $command ) > 1 )
			page_feedback( 'if you continue, ' . count( $command ) . ' SWAP volumes will be '
				. 'deactivated', 'c_notice' );

		// destroy pool command
		$command[] = '/sbin/zpool destroy ' . $poolname;

		// defer to dangerous command function
		dangerouscommand( $command, $url1 );
	}
	friendlyerror( 'no operation detected', $url2 );
}

function submit_pools_upgrade() {
	$poolname = @$_POST[ 'poolname' ];
	$url = 'pools.php?query=' . $poolname;
	$poolnewversion = @$_POST[ 'pool_newversion' ];

	// go back redirect
	if ( @isset( $_POST[ 'submit_goback' ] ) )
		redirect_url( $url );

	// sanity
	if ( !$poolname )
		error( 'no poolname provided' );
	if ( !$poolnewversion )
		error( 'no new pool version provided' );

	// defer to dangerous command
	dangerouscommand( '/sbin/zpool upgrade -V ' . ( int )$poolnewversion . ' ' . $poolname,
		$url );
}

function submit_pools_rename() {
	$poolname = @$_POST[ 'poolname' ];
	$newname = @$_POST[ 'pool_newname' ];
	$url = 'pools.php?query=' . $poolname;

	// go back redirect
	if ( @isset( $_POST[ 'submit_goback' ] ) )
		redirect_url( $url );

	// sanity
	if ( !$poolname )
		error( 'no poolname provided' );
	if ( !$newname )
		error( 'no new pool name provided' );

	// defer to dangerous command
	$commands = array();
	$commands[] = '/sbin/zpool export ' . $poolname;
	$commands[] = '/sbin/zpool import ' . $poolname . ' ' . $newname;
	dangerouscommand( $commands, $url );
	die( 'ESCAPE' );
}

function submit_pools_clearcorruption() {
	// required library
	activate_library( 'zfs' );

	// variables
	$poolname = @$_POST[ 'poolname' ];
	$url = 'pools.php?query=' . $poolname;

	// sanity check
	if ( !@isset( $_POST[ 'pool_clearcorruption' ] )OR( strlen( $poolname ) < 1 ) )
		error( 'no valid form submitted to clear corruption on pool' );

	// call function
	$status = zfs_pool_status( $poolname, '-v' );
	if ( count( $status[ 'errors' ] ) < 1 )
		friendlyerror( 'there seem to be no errors on pool ' . $poolname, 'a_warning' );

	// start assembling commands for file deletion
	$commands = array();
	foreach ( $status[ 'errors' ] as $corruptedfile )
		$commands[] = '/bin/rm ' . escapeshellarg( $corruptedfile );

	// conclude commands array with zpool clear command
	$commands[] = '/sbin/zpool clear ' . $poolname;
	$commands[] = '/sbin/zpool scrub ' . $poolname;

	// feedback message for dangerous command function
	page_feedback( 'this procedure will initiate a <b>scrub rebuild</b>, '
		. 'which should clear all errors afterwards.', 'a_warning' );
	page_feedback( 'remember that you may have to remove snapshots as well '
		. 'if any affected file is referenced by a snapshot.', 'c_notice' );

	// defer to dangerous command function
	dangerouscommand( $commands, $url );
}