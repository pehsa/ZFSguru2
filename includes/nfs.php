<?php

function nfs_configuration_list( $webnfs = true )
// returns array of NFS configuration options
{
	$config = array(
		'alldirs' => 'Allow subdirectories to be mounted',
		'mapall' => 'Treat all remote users as one local user',
		'maproot' => 'Treat the remote root user as given local user',
		'network' => 'Restrict access to the specified network',
		'mask' => 'Restrict access to the specified network mask',
		'sec' => 'NFSv4 security flavor',
		'ro' => 'Read only NFS share',
		'quiet' => 'Surpress error logging for this share',
	);
	if ( $webnfs ) {
		$config[ 'webnfs' ] = 'WebNFS share (advanced)';
		$config[ 'index' ] = 'WebNFS directory filehandle';
		$config[ 'public' ] = 'WebNFS public access';
	}
	return $config;
}

function nfs_sharenfs_list( $filesystems = false )
// returns list of zfs filesystems with sharenfs setting enabled
{
	// required library
	activate_library( 'zfs' );

	$sharenfs = zfs_filesystem_properties( $filesystems, 'sharenfs',
		'filesystem' );
	// in case queried filesystem is not in the list, do a separate query
	if ( $filesystems AND!@$sharenfs[ $filesystems ] ) {
		$sharenfs2 = zfs_filesystem_properties( $filesystems, 'sharenfs',
			'filesystem' );
		// return false if queried filesystem does not exist it all
		if ( !@isset( $sharenfs2[ $filesystems ] ) )
			return false;
		$sharenfs3[ $filesystems ] = $sharenfs2[ $filesystems ];
		// do some tricks to sort properly
		foreach ( $sharenfs as $var => $val )
			if ( $var != $filesystems )
				$sharenfs3[ $var ] = $val;
		$sharenfs = $sharenfs3;
	}
	if ( !is_array( $sharenfs ) )
		return array();
	$fs = array();
	foreach ( $sharenfs as $fsname => $fsdata )
		$fs[ $fsname ] = $fsname;
	$mp = zfs_filesystem_properties( implode( ' ', $fs ),
		'mountpoint', 'filesystem' );
	// assemble nfslist array
	$nfslist = array();
	foreach ( $sharenfs as $fsname => $fsdata )
		if ( @$mp[ $fsname ][ 'mountpoint' ][ 'value' ] {
				0
			} == '/' )
			if ( ( $fsname == $filesystems )OR( @$fsdata[ 'sharenfs' ][ 'value' ] != 'off' ) ) {
				$inherited = !in_array( @$fsdata[ 'sharenfs' ][ 'source' ],
					array( 'local', 'received' ) );
				$parent = ( !$inherited ) ? $fsname : substr( $fsdata[ 'sharenfs' ][ 'source' ],
					strlen( 'inherited from ' ) );
				$options = array();
				// parse either by comma-separated string or by options begining with '-'
				if ( strpos( $fsdata[ 'sharenfs' ][ 'value' ], ',' ) !== false )
					preg_match_all( '/(.+)(\ ((.+)))?\,|$/U', $fsdata[ 'sharenfs' ][ 'value' ],
						$matches );
				else
					preg_match_all( '/\-([^\s\=\-\ ]+)((\=|\ )([^\s\=\-]+))?/',
						$fsdata[ 'sharenfs' ][ 'value' ], $matches );
				foreach ( $matches[ 1 ] as $id => $optionname )
					if ( strlen( trim( $optionname ) ) > 0 )
						$options[ trim( $optionname ) ][] = trim( $matches[ 4 ][ $id ] );
				$nfslist[ $fsname ] = array(
					'mountpoint' => $mp[ $fsname ][ 'mountpoint' ][ 'value' ],
					'sharenfs' => $fsdata[ 'sharenfs' ][ 'value' ],
					'inherited' => $inherited,
					'parent' => $parent,
					'options' => $options,
				);
			}
	return $nfslist;
}

function nfs_showmount_list()
// returns array of interpreted output from showmount -e command
{
	$nfscmd = 'showmount -e';
	exec( $nfscmd, $output, $rv );
	preg_match_all( '/^(\/.+) (.+)$/m', implode( chr( 10 ), $output ), $matches );
	$array_showmount = array();
	if ( @is_array( $matches[ 1 ] ) )
		foreach ( $matches[ 1 ] as $id => $mountpoint )
			$array_showmount[ trim( $mountpoint ) ] = trim( @$matches[ 2 ][ $id ] );
	return $array_showmount;
}

/* NFS get/set functions */

function nfs_getprofile( $sharenfs_fs )
// returns string derived from row of array from nfs_sharenfs_list
{
	if ( $sharenfs_fs[ 'sharenfs' ] == 'off' )
		return 'notshared';
	$options = @$sharenfs_fs[ 'options' ];
	if ( @$options[ 'mask' ][ 0 ] == '0.0.0.0' )
		return 'public';
	if ( @$options[ 'network' ][ 0 ] == '0.0.0.0/0' )
		return 'public';
	if ( @count( $options[ 'network' ] ) < 1 )
		return 'public';
	if ( ( @count( $options[ 'network' ] ) == 1 )AND( @strpos( $options[ 'network' ][ 0 ], '/32' ) !== false ) )
		return 'private';
	return 'protected';
}

function nfs_setprofile( $fs, $profile, $privateip = '0.0.0.0' )
// sets new profile on chosen filesystem, returns cmd arr
{
	if ( $profile == 'public' )
		return nfs_setsharenfs( $fs, array( 'network' => array( '0.0.0.0/0' ),
			'mask' => array( '0.0.0.0' ) ) );
	if ( $profile == 'protected' )
		return nfs_setsharenfs( $fs, array( 'network' => array(
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
		) ), array( 'mask' ) );
	if ( $profile == 'private' )
		return nfs_setsharenfs( $fs, array( 'network' => array( $privateip . '/32' ) ),
			array( 'mask' ) );
	if ( $profile == 'notshared' )
		return nfs_setsharenfs( $fs, 'off' );
}

function nfs_geteasypermissions( $options )
// returns string derived from 'options' array from nfs_sharenfs_list
{
	if ( !@isset( $options[ 'maproot' ] ) )
		if ( @isset( $options[ 'alldirs' ] ) )
			if ( @$options[ 'mapall' ][ 0 ] == '1000:1000' )
				return true;
	return false;
}

function nfs_seteasypermissions( $fs, $enable = true )
// enables or disables Easy Permissions, returns cmd arr
{
	if ( $enable )
		return nfs_setsharenfs( $fs, array(
			'alldirs' => array(),
			'mapall' => array( '1000:1000' ),
		), array(
			'maproot',
		) );
	else
		return nfs_setsharenfs( $fs, array(), array(
			'alldirs',
			'mapall',
			'maproot',
		) );
}

function nfs_getreadonly( $options )
// returns boolean whether nfs share is readonly or not
{
	return ( @isset( $options[ 'ro' ] ) );
}

function nfs_setreadonly( $fs, $on = true )
// sets readonly flag on or off on specified filesystem, returns cmd arr
{
	if ( $on )
		return nfs_setsharenfs( $fs, array( 'ro' => array() ) );
	else
		return nfs_setsharenfs( $fs, array(), array( 'ro' ) );
}

function nfs_resetpermissions( $fs )
// resets permissions of all files contained by filesystem, returns cmd arr
{
	// required library
	activate_library( 'zfs' );

	// get zfs mountpoint for given filesystem
	$zfsprop = zfs_filesystem_properties( $fs, 'mountpoint,mounted' );
	if ( @$zfsprop[ $fs ][ 'mounted' ][ 'value' ] != 'yes' )
		error( 'cannot reset permissions, filesystem "' . htmlentities( $fs ) . '" is not mounted!' );
	$mp = @$zfsprop[ $fs ][ 'mountpoint' ][ 'value' ];
	if ( !is_dir( $mp ) )
		error( 'cannot reset permissions: "' . $mp . '" not a directory' );

	// permissions
	$uid = 1000;
	$gid = 1000;
	$dirperms = '0775';
	$fileperms = '0664';

	// return array with commands to reset permissions of all files and dirs
	return array(
		'/usr/bin/chown -R ' . $uid . ':' . $gid . ' ' . $mp,
		'/usr/bin/find ' . $mp . ' -type d -print0'
		. ' | /usr/local/bin/sudo /usr/bin/xargs -0 /bin/chmod ' . $dirperms,
		'/usr/bin/find ' . $mp . ' -type f -print0'
		. ' | /usr/local/bin/sudo /usr/bin/xargs -0 /bin/chmod ' . $fileperms,
	);
}

function nfs_removeshare( $fs, $explicit = false )
// removes NFS share, either by inheritance or explicitly, returns cmd arr
{
	$commandprefix = ( $explicit ) ? '/sbin/zfs set sharenfs="off" ' :
		'/sbin/zfs inherit sharenfs ';
	return array( $commandprefix . $fs );
}

/* helper functions */

function nfs_setsharenfs( $fs, $options, $unset_options = array() )
// sets the sharenfs command while preserving existing options, returns cmd arr
{
	// disable sharing if $options is not an array
	if ( !is_array( $options ) )
		return array( '/sbin/zfs set sharenfs="off" ' . $fs );
	// merge existing options with new ones
	$oldshare = nfs_sharenfs_list( $fs );
	$sharenfs = array();
	if ( @is_array( $oldshare[ $fs ][ 'options' ] ) )
		foreach ( $oldshare[ $fs ][ 'options' ] as $oldoption => $oldoptionvalue )
			if ( !@isset( $options[ $oldoption ] ) )
				$options[ $oldoption ] = $oldoptionvalue;
			// unset options
	foreach ( $unset_options as $opt )
		if ( @isset( $options[ $opt ] ) )
			unset( $options[ $opt ] );
		// create string from options
	$sharenfs = array();
	foreach ( $options as $option => $optiondata )
		if ( in_array( $option, array( 'alldirs', 'public', 'quiet', 'ro', 'webnfs' ) ) )
			$sharenfs[] = "-$option";
		elseif ( count( $optiondata ) > 1 )
			foreach ( $optiondata as $optiondataone )
				$sharenfs[] = "-$option=$optiondataone";
	elseif ( @strlen( $optiondata[ 0 ] ) > 0 )
		$sharenfs[] = "-$option=$optiondata[0]";
	// return zfs set command
	return array( '/sbin/zfs set sharenfs="' . implode( ' ', $sharenfs ) . '" ' . $fs );
}