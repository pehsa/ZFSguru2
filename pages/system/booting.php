<?php

function content_system_booting() {
	// include pools.php and pools.css
	page_register_stylesheet( 'pages/pools/pools.css' );

	// table: poollist
	$table_bootlist = table_system_bootlist( $advice );

	// check expert mode (allows doing stupid things)
	$emode = ( @isset( $_GET[ 'expertmode' ] ) ) ? true : false;
	$class_normal = ( !$emode ) ? 'normal' : 'hidden';
	$class_expert = ( $emode ) ? 'normal' : 'hidden';

	// advice
	$class_adv_noboot = ( $advice == 'noboot' ) ? 'normal' : 'hidden';
	$class_adv_oneboot = ( $advice == 'oneboot' ) ? 'normal' : 'hidden';
	$class_adv_multiboot = ( $advice == 'multiboot' ) ? 'normal' : 'hidden';
	$class_adv_conflict = ( $advice == 'conflict' ) ? 'normal' : 'hidden';
	$class_adv_expert = ( $emode ) ? 'normal' : 'hidden';

	// export new tags
	return array(
		'PAGE_TITLE' => 'Booting',
		'PAGE_ACTIVETAB' => 'Booting',
		'TABLE_BOOTLIST' => $table_bootlist,
		'CLASS_NORMAL' => $class_normal,
		'CLASS_EXPERT' => $class_expert,
		'CLASS_ADV_NOBOOT' => $class_adv_noboot,
		'CLASS_ADV_ONEBOOT' => $class_adv_oneboot,
		'CLASS_ADV_MULTIBOOT' => $class_adv_multiboot,
		'CLASS_ADV_CONFLICT' => $class_adv_conflict,
		'CLASS_ADV_EXPERT' => $class_adv_expert
	);
}

function table_system_bootlist( & $advice ) {
	// required library
	activate_library( 'zfs' );

	// gather data
	$zpools = zfs_pool_list();

	// set default advice
	$advice = 'noboot';

	// check for conflicts
	$bootablepools = 0;

	// check expert mode (allows doing stupid things)
	$emode = ( @isset( $_GET[ 'expertmode' ] ) ) ? true : false;

	// create boot list table
	$table_bootlist = array();
	foreach ( $zpools as $poolname => $pooldata ) {
		// fetch pool bootfs setting
		$bootfs = zfs_pool_getbootfs( $poolname );
		if ( $bootfs != '-' )
			$bootablepools++;
		// fetch filesystem list with /zfsguru prefix
		$fslist = zfs_filesystem_list( $poolname . '/zfsguru', '-r' );
		// traverse filesystem list to search for bootable ZFSguru filesystems
		if ( !is_array( $fslist ) )
			continue;
		foreach ( $fslist as $fsname => $fsdata ) {
			// skip filesystem if it is not a ZFSguru installation
			if ( !preg_match( '/^([^\/]+)\/zfsguru\/([^\/]+)$/', $fsname ) )
				continue;
			if ( $fsdata[ 'mountpoint' ] != 'legacy' )
				continue;
			// advice
			if ( $advice == 'noboot' )
				$advice = 'oneboot';
			elseif ( $advice == 'oneboot' )
				$advice = 'multiboot';
			// row status (highlight when current row has activated bootfs)
			$class_bootlist = ( $fsname == $bootfs ) ? 'activerow' : 'normal';
			// bootfs name
			$bootfs_name = substr( $fsname, strrpos( $fsname, '/' ) + 1 );
			// bootfs encoded in BASE64
			$b64_bootfs = @base64_encode( $fsname );
			// referenced data by bootfs
			$prop_used = zfs_filesystem_properties( $fsname, 'used' );
			$size = @htmlentities( $prop_used[ $fsname ][ 'used' ][ 'value' ] );
			// boot status
			$bootstatus = '?';
			if ( $fsname == $bootfs )
				$bootstatus = 'Activated';
			else
				$bootstatus = 'Inactive';
			// boot status class
			if ( $bootstatus == 'Activated' )
				$class_bootstatus = 'green';
			elseif ( $bootstatus == 'Inactive' )
				$class_bootstatus = 'grey';
			else
				$class_bootstatus = 'red';
			// action button classes
			$act = ( $bootstatus == 'Activated' ) ? true : false;
			$class_activate = ( !$act ) ? 'normal' : 'hidden';
			$class_inactivate = ( $act AND $emode ) ? 'normal' : 'hidden';
			$class_noinactivate = ( $act AND!$emode ) ? 'normal' : 'hidden';
			$class_candelete = ( !$act OR $emode ) ? 'normal' : 'hidden';
			// add row to bootlist table
			$table_bootlist[] = @array(
				'CLASS_BOOTLIST' => $class_bootlist,
				'CLASS_BOOTSTATUS' => $class_bootstatus,
				'CLASS_ACTIVATE' => $class_activate,
				'CLASS_INACTIVATE' => $class_inactivate,
				'CLASS_NOINACTIVATE' => $class_noinactivate,
				'CLASS_CANDELETE' => $class_candelete,
				'BOOTLIST_POOLNAME' => htmlentities( $poolname ),
				'BOOTLIST_BOOTFS' => htmlentities( $bootfs_name ),
				'B64_BOOTFS' => htmlentities( $b64_bootfs ),
				'BOOTLIST_SIZE' => $size,
				'BOOTLIST_STATUS' => $bootstatus,
			);
		}
	}
	if ( $bootablepools > 1 )
		$advice = 'conflict';
	return $table_bootlist;
}

function submit_system_bootfs() {
	// redirect url
	$url = 'system.php?booting';

	// traverse POST variables
	if ( is_array( $_POST ) )
		foreach ( $_POST as $name => $value )
			if ( substr( $name, 0, strlen( 'activate_bootfs_' ) ) == 'activate_bootfs_' ) {
				$bootfs = base64_decode( substr( $name, strlen( 'activate_bootfs_' ) ) );
				$poolname = substr( $bootfs, 0, strpos( $bootfs, '/' ) );
				dangerouscommand( '/sbin/zpool set bootfs="' . $bootfs . '" ' . $poolname, $url );
			}
	if ( substr( $name, 0, strlen( 'inactivate_bootfs_' ) ) == 'inactivate_bootfs_' ) {
		$bootfs = base64_decode( substr( $name, strlen( 'inactivate_bootfs_' ) ) );
		$poolname = substr( $bootfs, 0, strpos( $bootfs, '/' ) );
		dangerouscommand( '/sbin/zpool set bootfs="" ' . $poolname, $url );
	}
	if ( substr( $name, 0, strlen( 'delete_bootfs_' ) ) == 'delete_bootfs_' ) {
		$bootfs = base64_decode( substr( $name, strlen( 'delete_bootfs_' ) ) );
		$poolname = substr( $bootfs, 0, strpos( $bootfs, '/' ) );
		dangerouscommand( '/sbin/zfs destroy -R ' . $bootfs, $url );
	}

	// default redirect
	redirect_url( $url );
}