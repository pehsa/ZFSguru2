<?php

function content_pools_version() {
	global $guru;

	// required library
	activate_library( 'zfs' );

	// fetch data
	$zfsver = zfs_version();
	$fsversions = zfs_filesystem_versions();
	$poolversions = zfs_pool_versions();

	// zplversions table
	$table_zplversions = array();
	$zpl_defaultselected = min( $guru[ 'recommended_zfsversion' ][ 'zpl' ],
		$zfsver[ 'zpl' ] );
	foreach ( $fsversions as $nr => $desc ) {
		$selected = ( $nr == $zpl_defaultselected ) ? 'checked ' : '';
		$systemlow = ( $nr > $zfsver[ 'zpl' ] ) ? 'normal' : 'hidden';
		$table_zplversions[] = array(
			'ZPL_SELECT' => $selected,
			'ZPL_VER' => $nr,
			'ZPL_DESC' => $desc,
			'ZPL_CANSELECT' => ( $nr <= $zfsver[ 'zpl' ] ) ? 'normal' : 'hidden',
			'ZPL_SYSTEMLOW' => ( $nr > $zfsver[ 'zpl' ] ) ? 'normal' : 'hidden'
		);
	}

	// spaversions table
	$table_spaversions = array();
	$spa_defaultselected = min( $guru[ 'recommended_zfsversion' ][ 'spa' ],
		$zfsver[ 'spa' ] );

	foreach ( $poolversions as $nr => $desc ) {
		$selected = ( $nr == $spa_defaultselected ) ? 'checked ' : '';
		$systemlow = ( $nr > $zfsver[ 'spa' ] ) ? 'normal' : 'hidden';
		$table_spaversions[] = array(
			'SPA_SELECT' => $selected,
			'SPA_VER' => $nr,
			'SPA_DESC' => $desc,
			'SPA_CANSELECT' => ( $nr <= $zfsver[ 'spa' ] ) ? 'normal' : 'hidden',
			'SPA_SYSTEMLOW' => ( $nr > $zfsver[ 'spa' ] ) ? 'normal' : 'hidden'
		);
	}

	// inject tags and handle page
	return array(
		'PAGE_ACTIVETAB' => 'Create',
		'TABLE_ZPLVERSIONS' => $table_zplversions,
		'TABLE_SPAVERSIONS' => $table_spaversions
	);
}

function submit_pools_version() {
	$zpl = @$_POST[ 'zpl_version' ];
	$spa = @$_POST[ 'spa_version' ];
	$suffix = '';
	if ( strlen( $spa ) > 0 )
		$suffix .= '&spa=' . ( int )$spa;
	if ( strlen( $zpl ) > 0 )
		$suffix .= '&zpl=' . ( int )$zpl;
	if ( @isset( $_POST[ 'submit_goback' ] ) )
		redirect_url( 'pools.php?create' );
	else
		redirect_url( 'pools.php?create' . $suffix );
}