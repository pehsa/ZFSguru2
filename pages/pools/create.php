<?php

function content_pools_create() {
	global $guru;

	// required libraries
	activate_library( 'html' );
	activate_library( 'zfs' );

	// call functions
	$zfsver = zfs_version();

	// SPA list
	$poolspa = '';
	for ( $i = 1; $i <= $zfsver[ 'spa' ]; $i++ )
		if ( ( $i <= 28 )OR( $i >= 5000 ) ) {
			$userver = ( ( ( int )@$_GET[ 'spa' ] > 0 )AND( ( int )@$_GET[ 'zpl' ] > 0 ) );
			$userchosen = ( @$_GET[ 'spa' ] == $i );
			$selected = ( $userchosen OR( !$userver ) ) ? 'selected ' : '';
			$poolspa .= ( '  <option ' . $selected . 'value="' . $i . '">' . $i );
			if ( $userchosen )
				$poolspa .= ' (selected)';
			if ( $i == 5000 )
				$poolspa .= ' (feature flags)';
			$poolspa .= '</option>' . chr( 10 );
		}

		// ZPL list
	$poolzpl = '';
	for ( $i = 1; $i <= $zfsver[ 'zpl' ]; $i++ ) {
		$userver = ( ( ( int )@$_GET[ 'spa' ] > 0 )AND( ( int )@$_GET[ 'zpl' ] > 0 ) );
		$userchosen = ( @$_GET[ 'zpl' ] == $i );
		$selected = ( $userchosen OR( !$userver ) ) ? 'selected ' : '';
		$poolzpl .= ( '  <option ' . $selected . 'value="' . $i . '">' . $i );
		if ( $userchosen )
			$poolzpl .= ' (selected)';
		$poolzpl .= '</option>' . chr( 10 );
	}

	// radio button (zfs version)
	$specifyversion = ( @$_GET[ 'spa' ]OR @$_GET[ 'zpl' ] ) ? true : false;
	$radio_modernzfs = ( !$specifyversion ) ? 'checked="checked"' : '';
	$radio_specify = ( $specifyversion ) ? 'checked="checked"' : '';

	// member disks
	$memberdisks = html_memberdisks();

	// new tags
	$newtags = array(
		'PAGE_ACTIVETAB' => 'Create',
		'PAGE_TITLE' => 'Create new pool',
		'RADIO_MODERNZFS' => $radio_modernzfs,
		'RADIO_SPECIFY' => $radio_specify,
		'POOL_ZPLLIST' => $poolzpl,
		'POOL_SPALIST' => $poolspa,
		'POOL_MEMBERDISKS' => $memberdisks
	);
	return $newtags;
}

function submit_pools_createpool() {
	// required libraries
	activate_library( 'disk' );
	activate_library( 'zfs' );

	// POST variables
	sanitize( @$_POST[ 'new_zpool_name' ], null, $new_zpool, 24 );
	$sectorsize = ( @$_POST[ 'new_zpool_sectorsize' ] ) ?
		( int )$_POST[ 'new_zpool_sectorsize' ] : 512;
	$recordsize = @$_POST[ 'new_zpool_recordsize' ];
	$force = ( @$_POST[ 'new_zpool_force' ] == 'on' ) ? true : false;
	$url = 'pools.php?create';
	$url2 = 'pools.php?query=' . $new_zpool;

	// sanity
	if ( $new_zpool != @$_POST[ 'new_zpool_name' ] )
		friendlyerror( 'please use only alphanumerical characters for the pool name',
			$url );
	if ( strlen( $new_zpool ) < 1 )
		friendlyerror( 'please enter a name for your new pool', $url );
	if ( zfs_pool_isreservedname( $new_zpool ) )
		friendlyerror( 'you chose a reserved name; please choose a different name!',
			$url );

	// options string
	$options_str = '';

	// apply force (disables protection overwriting disks with existing pool)
	if ( $force )
		$options_str .= '-f ';

	// mountpoint
	$mountpoint = '/' . $new_zpool;

	// filesystem version
	$spa = ( int )@$_POST[ 'new_zpool_spa' ];
	$zpl = ( int )@$_POST[ 'new_zpool_zpl' ];
	$syszfs = zfs_version();
	if ( $spa == 5000 ) {
		// ZFS version 5000 with feature flags
		$options_str .= '-d -o feature@async_destroy=enabled '
			. '-o feature@empty_bpobj=enabled -o feature@lz4_compress=enabled ';
	} else {
		// ZFS version 28 or below - no feature flags
		if ( ( $spa > 0 )AND( $spa <= $syszfs[ 'spa' ] ) )
			$options_str .= '-o version=' . $spa . ' ';
		if ( ( $zpl > 0 )AND( $zpl <= $syszfs[ 'zpl' ] ) )
			$options_str .= '-O version=' . $zpl . ' ';
	}

	// disable access times - few people need them
	$options_str .= '-O atime=off ';

	// large blocks feature (recordsize > 128K)
	if ( in_array( $recordsize, array( '256K', '512K', '1024K' ) ) )
		if ( $spa == 5000 )
			$options_str .= '-o feature@large_blocks=enabled -O recordsize='
			. $recordsize . ' ';
		else
			friendlyerror( 'pool version 5000 is required for the large_blocks feature',
				$url );

		// extract and format submitted disks to add
	$vdev = zfs_extractsubmittedvdevs( $url );
	$redundancy = zfs_extractsubmittedredundancy( $_POST[ 'new_zpool_redundancy' ],
		$vdev[ 'member_count' ], $url );

	// check for member disks
	if ( $vdev[ 'member_count' ] < 1 )
		error( 'vdev member count zero' );

	// check member disk minimum size
	$minsize = 64 * 1024 * 1024;
	foreach ( $vdev[ 'member_disks' ] as $vdevdisk ) {
		if ( !file_exists( '/dev/' . $vdevdisk ) )
			friendlyerror( 'disk <b>' . $vdevdisk . '</b> does not actually exist!', $url );
		$diskinfo = disk_info( $vdevdisk );
		if ( $diskinfo[ 'mediasize' ] < $minsize )
			friendlyerror( 'at least one disk selected is too small, minimum is '
				. sizebinary( $minsize ), $url );
	}

	// warn for RAID0 with more than 1 disk (could be a mistake)
	if ( ( $vdev[ 'member_count' ] > 1 )AND( $redundancy == '' ) )
		page_feedback( 'you selected RAID0 with more than one disk; are you sure '
			. 'that is what you wanted?', 'a_warning' );

	// process 2-way or 3-way or 4-way mirrors
	if ( $redundancy == 'mirror2'
		OR $redundancy == 'mirror3'
		OR $redundancy == 'mirror4' ) {
		$member_arr = array();
		$member_str = '';
		for ( $i = 2; $i <= 10; $i++ )
			if ( $redundancy == 'mirror' . $i )
				for ( $y = 0; $y <= 255; $y = $y + $i )
					if ( @isset( $vdev[ 'member_disks' ][ $y ] ) )
						for ( $z = 0; $z <= ( $i - 1 ); $z++ )
							$member_arr[ $y ][] = $vdev[ 'member_disks' ][ $y + $z ];
		foreach ( $member_arr as $components )
			$member_str .= 'mirror ' . implode( ' ', $components ) . ' ';
	} elseif ( $redundancy == '' )
		$member_str = $vdev[ 'member_str' ];
	else
		$member_str = $redundancy . ' ' . $vdev[ 'member_str' ];

	// handle sectorsize override
	$old_ashift_min = @trim( shell_exec( '/sbin/sysctl -n vfs.zfs.min_auto_ashift' ) );
	$old_ashift_max = @trim( shell_exec( '/sbin/sysctl -n vfs.zfs.max_auto_ashift' ) );
	$new_ashift = 9;
	for ( $new_ashift = 9; $new_ashift <= 17; $new_ashift++ )
		if ( pow( 2, $new_ashift ) == $sectorsize )
			break;
	if ( $new_ashift > 16 )
		error( 'unable to find correct ashift number for sectorsize override' );

	// command array
	$commands = array();

	// force specific ashift setting to be used during pool creation
	if ( is_numeric( $sectorsize ) ) {
		$commands[] = '/sbin/sysctl vfs.zfs.min_auto_ashift=' . ( int )$new_ashift;
		$commands[] = '/sbin/sysctl vfs.zfs.max_auto_ashift=' . ( int )$new_ashift;
	}

	// create pool
	// TODO: SECURITY
	$commands[] = '/sbin/zpool create ' . $options_str
		. escapeshellarg( $new_zpool ) . ' ' . $member_str;

	// restore original min/max_auto_ashift setting
	if ( is_numeric( $sectorsize ) ) {
		$commands[] = '/sbin/sysctl vfs.zfs.min_auto_ashift=' . ( int )$old_ashift_min;
		$commands[] = '/sbin/sysctl vfs.zfs.max_auto_ashift=' . ( int )$old_ashift_max;
	}

	// create share filesystem and set permissions
	$commands[] = '/sbin/zfs create ' . escapeshellarg( $new_zpool . '/share' );
	$commands[] = '/bin/chmod 777 ' . escapeshellarg( $mountpoint . '/share' );
	$commands[] = '/usr/sbin/chown -R 1000:1000 ' . escapeshellarg( $mountpoint );

	// defer to dangerouscommand function
	dangerouscommand( $commands, $url2 );
}