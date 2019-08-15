<?php

function content_system_install_step3() {
	// required library
	activate_library( 'zfs' );
	activate_library( 'zfsguru' );

	// include CSS from step1 and step2
	page_register_stylesheet( 'pages/system/install_step1.css' );
	page_register_stylesheet( 'pages/system/install_step2.css' );

	// GET variables
	$version = @$_GET[ 'version' ];
	$source = @$_GET[ 'source' ];
	$target = @$_GET[ 'target' ];
	$dist = @$_GET[ 'dist' ];

	// disabled distributions
	$disabled = distribution_disabled( $version, $source, $target, $dist );

	// classes
	$class_dist = ( $dist ) ? 'normal' : 'hidden';
	$class_nodist = ( !$dist ) ? 'normal' : 'hidden';
	$class_roz = ( strtoupper( $dist ) == 'ROZ' ) ? 'normal' : 'hidden';
	$class_ror = ( strtoupper( $dist ) == 'ROR' ) ? 'normal' : 'hidden';
	$class_rom = ( strtoupper( $dist ) == 'ROM' ) ? 'normal' : 'hidden';
	$class_roz_active = ( strtoupper( $dist ) == 'ROZ' ) ? 'squareboxactive' : '';
	$class_ror_active = ( strtoupper( $dist ) == 'ROR' ) ? 'squareboxactive' : '';
	$class_rom_active = ( strtoupper( $dist ) == 'ROM' ) ? 'squareboxactive' : '';
	$class_roz_disabled = ( @$disabled[ 'ROZ' ] ) ? 'squareboxdisabled' : '';
	$class_ror_disabled = ( @$disabled[ 'ROR' ] ) ? 'squareboxdisabled' : '';
	$class_rom_disabled = ( @$disabled[ 'ROM' ] ) ? 'squareboxdisabled' : '';
	// legacy remove?
	$class_gptdisk = ( $target == 'gpt' ) ? 'normal' : 'hidden';
	$class_rawdisk = ( $target == 'raw' ) ? 'normal' : 'hidden';
	$class_zfspool = ( $target == 'zfs' ) ? 'normal' : 'hidden';

	// mount LiveCD or USB media when applicable
	if ( $source == 'livecd' )
		zfsguru_mountlivecd();
	if ( $source == 'usb' )
		zfsguru_mountusb();

	// process distribution tags
	if ( strtoupper( $dist ) == 'ROZ' )
		page_injecttag( distribution_roz( $version, $source, $target ) );
	elseif ( strtoupper( $dist ) == 'ROR' )
		page_injecttag( distribution_ror( $version, $source, $target ) );
	elseif ( strtoupper( $dist ) == 'ROM' )
		page_injecttag( distribution_rom( $version, $source, $target ) );

	// unmount media (unmounts any ZFSguru LiveCD/USB media)
	zfsguru_unmountmedia();

	// export shared tags
	return array(
		'PAGE_ACTIVETAB' => 'Install',
		'PAGE_TITLE' => 'Install (step 3)',
		'INSTALL_VERSION' => htmlentities( $version ),
		'INSTALL_SOURCE' => htmlentities( $source ),
		'INSTALL_TARGET' => htmlentities( $target ),
		'INSTALL_DIST' => htmlentities( $dist ),
		'CLASS_DIST' => $class_dist,
		'CLASS_NODIST' => $class_nodist,
		'CLASS_ROZ' => $class_roz,
		'CLASS_ROR' => $class_ror,
		'CLASS_ROM' => $class_rom,
		'CLASS_ROZ_ACTIVE' => $class_roz_active,
		'CLASS_ROR_ACTIVE' => $class_ror_active,
		'CLASS_ROM_ACTIVE' => $class_rom_active,
		'CLASS_ROZ_DISABLED' => $class_roz_disabled,
		'CLASS_ROR_DISABLED' => $class_ror_disabled,
		'CLASS_ROM_DISABLED' => $class_rom_disabled,
		'CLASS_GPTDISK' => $class_gptdisk,
		'CLASS_RAWDISK' => $class_rawdisk,
		'CLASS_ZFSPOOL' => $class_zfspool,
	);
}

function distribution_disabled( $version, $source, $target, $dist )
// returns array of disabled distributions for chosen target and redirect
// user if chosen an invalid distribution
{
	$disabled = array(
		'ROZ' => false,
		'ROR' => false,
		'ROM' => false,
	);
	if ( strpos( $target, 'ZFS:' ) !== false ) {
		$disabled[ 'ROR' ] = true;
		$disabled[ 'ROM' ] = true;
	}
	// redirect user if chosen a disabled distribution for the chosen target
	if ( @$disabled[ strtoupper( $dist ) ] )
		friendlyerror( 'distribution ' . $dist . ' is unavailable for your chosen target!',
			'system.php?install&version=' . htmlentities( $version ) . '&source='
			. htmlentities( $source ) . '&target=' . htmlentities( $target ) );
	return $disabled;
}

function distribution_roz( $version, $source, $target ) {
	// required library
	activate_library( 'zfs' );
	activate_library( 'zfsguru' );

	// system image location
	$locate = zfsguru_locatesystem();
	$sysloc = @$locate[ 'name' ][ $version ][ 'path' ];

	// target ("ZFS: <poolname>")
	if ( substr( $target, 0, strlen( 'ZFS: ' ) ) != 'ZFS: ' )
		friendlyerror( 'Root-on-ZFS distribution can only be installed on a ZFS pool!',
			'system.php?install&version=' . $version . '&source=' . $source );
	$poolname = substr( $target, strlen( 'ZFS: ' ) );
	$poolfs = zfs_filesystem_list( $poolname, '-p' );
	$pool_version = zfs_pool_version( $poolname );
	$pool_v5000 = ( ( int )$pool_version == 5000 );

	// space required
	$syssize = @$locate[ 'name' ][ $version ][ 'size' ];
	if ( ( int )$syssize < ( 100 * 1024 * 1024 ) )
		$syssize = 410 * 1024 * 1024;
	$syssize_binary = sizebinary( $syssize, 1 );
	$amplificationfactor = 3;
	$class_lowspace = '';
	$class_toolowspace = '';
	$space_avail = sizebinary( $poolfs[ $poolname ][ 'avail' ], 1 );
	$space_min = sizebinary( $syssize, 1 );
	$space_max = sizebinary( $syssize * $amplificationfactor, 1 );

	// target filesystem 
	$targetfs = substr( $version, 0, 10 ); /* max length of targetfs is 10 chars */
	$targetprefix = $poolname . '/zfsguru/';
	$zfslist = zfs_filesystem_list( $poolname, '-r' );
	$class_targetinuse = ( @isset( $zfslist[ $targetprefix . $targetfs ] ) ) ?
		'normal' : 'hidden';

	// checksum (??)
	$sha512 = ( @isset( $system[ $version ][ $platform ][ 'sha512' ] ) ) ?
		$system[ $sysver ][ $platform ][ 'sha512' ] : $version . ' (cannot verify)';

	// swap size table
	$table_roz_swapsize = array();
	// TODO: available swap space
	//  $swap_availspace = $syssize - $syssize_uncompressed;
	$swaplist = array(
		'0.125' => '128 MiB (minimum)</option>',
		'0.25' => '256 MiB',
		'0.5' => '512 MiB',
		'1.0' => '1 GiB',
		'2.0' => '2 GiB',
		'4.0' => '4 GiB',
		'6.0' => '6 GiB',
		'8.0' => '8 GiB',
		'10.0' => '10 GiB',
		'16.0' => '16 GiB',
		'20.0' => '20 GiB',
		'32.0' => '32 GiB'
	);
	foreach ( $swaplist as $value => $name ) {
		// TODO: available swap space; hide SWAP volumes that are too big
		//   if (($value * 1024 * 1024 * 1024) > $targetfreespace_afterinstall)
		//    continue;
		$swapname = $name;
		$selected = '';
		if ( $value == '2.0' ) {
			$swapname = $name . ' (default)';
			$selected = 'selected="selected"';
		}
		$table_roz_swapsize[] = array(
			'SWAP_NAME' => $swapname,
			'SWAP_VALUE' => $value,
			'SWAP_SELECTED' => $selected
		);
	}
	if ( count( $table_roz_swapsize ) > 1 ) {
		$arr = $table_roz_swapsize;
		$akeys = array_keys( $arr );
		$lastkey = array_pop( $akeys );
		$table_roz_swapsize[ $lastkey ][ 'SWAP_NAME' ] .= ' (maximum)';
		if ( ( double )$table_roz_swapsize[ $lastkey ][ 'SWAP_VALUE' ] < 2.0 )
			$table_roz_swapsize[ $lastkey ][ 'SWAP_SELECTED' ] = 'selected="selected"';
	}

	// export new tags
	return @array(
		'TABLE_ROZ_SWAPSIZE' => $table_roz_swapsize,
		'CLASS_LOWSPACE' => $class_lowspace,
		'CLASS_TOOLOWSPACE' => $class_toolowspace,
		'CLASS_TARGETINUSE' => $class_targetinuse,
		'ROZ_TARGETFS' => htmlentities( $targetfs ),
		'ROZ_TARGETPREFIX' => htmlentities( $targetprefix ),
		'ROZ_SYSLOC' => htmlentities( $sysloc ),
		'ROZ_POOLNAME' => htmlentities( $poolname ),
		'ROZ_SPACEAVAIL' => $space_avail,
		'ROZ_SPACEMIN' => $space_min,
		'ROZ_SPACEMAX' => $space_max,
		'ROZ_LZ4' => ( $pool_v5000 ) ? 'selected' : 'disabled',
		'ROZ_LZJB' => ( !$pool_v5000 ) ? 'selected' : '',
	);
}

function distribution_ror( $version, $source, $target ) {
	return array();
}

function distribution_rom( $version, $source, $target ) {
	return array();
}