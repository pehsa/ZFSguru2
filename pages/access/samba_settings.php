<?php

function content_access_samba_settings() {
	// required modules
	activate_library( 'internalservice' );
	activate_library( 'samba' );

	// javascript + stylesheet
	page_register_javascript( 'pages/access/samba_settings.js' );

	// read samba configuration
	$sambaconf = samba_readconfig();
	if ( $sambaconf === false )
		error( 'Could not read Samba configuration file!' );

	// check whether Samba is running
	$isrunning = internalservice_querystart( 'samba' );

	// classes
	$class_notrunning = ( !$isrunning ) ? 'normal' : 'hidden';
	$class_corruptconfig = ( $sambaconf === false ) ? 'normal' : 'hidden';
	$class_noshares = ( @count( $sambaconf[ 'shares' ] ) < 1 ) ? 'normal' : 'hidden';
	$class_deleteselectedshares = ( $class_noshares == 'hidden' ) ?
		'' : 'disabled="disabled"';

	// global settings
	$smbdv_output = `/usr/local/sbin/smbd -V`;
	$sambaversion = htmlentities( trim( substr( $smbdv_output, strlen( 'Version' ) ) ) );
	$workgroup = htmlentities( $sambaconf[ 'global' ][ 'workgroup' ] );
	$netbiosname = htmlentities( $sambaconf[ 'global' ][ 'netbios name' ] );
	$servercomment = htmlentities( $sambaconf[ 'global' ][ 'server string' ] );
	// performance settings
	$async_read = htmlentities( $sambaconf[ 'global' ][ 'aio read size' ] );
	$async_write = htmlentities( $sambaconf[ 'global' ][ 'aio write size' ] );
	$asyncwb_on = ( @$sambaconf[ 'global' ][ 'aio write behind' ] == 'yes' ) ?
		'normal' : 'hidden';
	$asyncwb_off = ( $asyncwb_on != 'normal' ) ? 'normal' : 'hidden';
	$sendfile_on = ( @$sambaconf[ 'global' ][ 'use sendfile' ] == 'yes' ) ?
		'normal' : 'hidden';
	$sendfile_off = ( $sendfile_on != 'normal' ) ? 'normal' : 'hidden';
	// security model
	$sm_share = ( $sambaconf[ 'global' ][ 'security' ] == 'share' ) ?
		'selected="selected"' : '';
	$sm_domain = ( $sambaconf[ 'global' ][ 'security' ] == 'domain' ) ?
		'selected="selected"' : '';
	$sm_ads = ( $sambaconf[ 'global' ][ 'security' ] == 'ads' ) ?
		'selected="selected"' : '';
	$sm_server = ( $sambaconf[ 'global' ][ 'security' ] == 'server' ) ?
		'selected="selected"' : '';
	// authentication backend
	$ab_ldapsam = ( @$sambaconf[ 'global' ][ 'passdb backend' ] == 'ldapsam' ) ?
		'selected="selected"' : '';
	$ab_smbpasswd = ( @$sambaconf[ 'global' ][ 'passwd backend' ] == 'smbpasswd' ) ?
		'selected="selected"' : '';
	// file permissions
	$create_mask = @$sambaconf[ 'global' ][ 'create mask' ];
	$dir_mask = @$sambaconf[ 'global' ][ 'directory mask' ];
	if ( !is_numeric( $create_mask )OR( strlen( $create_mask ) != 4 ) )
		$create_mask = '0660';
	if ( !is_numeric( $dir_mask )OR( strlen( $dir_mask ) != 4 ) )
		$create_mask = '0770';
	$special_permissions = "$create_mask/$dir_mask";
	$opt_permissions_0660 = '';
	$opt_permissions_0600 = '';
	$opt_permissions_custom = '';
	if ( $special_permissions == '0660/0770' )
		$opt_permissions_0660 = 'selected';
	elseif ( $special_permissions == '0600/0700' )
		$opt_permissions_0600 = 'selected';
	elseif ( $special_permissions != '0666/0777' )
		$opt_permissions_custom = 'selected';
	// dfree command
	$dfree = ( @$sambaconf[ 'global' ][ 'dfree command' ] ==
		'/usr/local/www/zfsguru/scripts/dfree "%P"' );
	$cb_special_dfree = ( $dfree ) ? 'checked' : '';
	// other classes
	$class_corruptconfig = ( $sambaconf === false ) ? 'normal' : 'hidden';
	$class_noshares = ( @count( $sambaconf[ 'shares' ] ) < 1 ) ? 'normal' : 'hidden';
	$class_deleteselectedshares = ( $class_noshares == 'hidden' ) ?
		'' : 'disabled="disabled"';
	// table: global variables
	$table_samba_globalvars = table_samba_globalvariables();
	// table: share variables
	$table_samba_shareglobalvars = table_samba_sharevariables( $sambaconf );
	// table: extraglobals
	$table_samba_extraglobals = array();
	$globalvars = array( 'workgroup', 'netbios name', 'server string',
		'aio read size', 'aio write size', 'aio write behind', 'use sendfile',
		'security', 'passdb backend', 'create mask', 'directory mask' );
	if ( ( strlen( @$sambaconf[ 'global' ][ 'dfree command' ] ) < 2 )OR $dfree )
		$globalvars[] = 'dfree command';
	foreach ( $sambaconf[ 'global' ] as $property => $value )
		if ( !in_array( $property, $globalvars ) )
			$table_samba_extraglobals[] = array(
				'EXTRAGLOB_PROPERTY' => htmlentities( $property ),
				'EXTRAGLOB_VALUE' => htmlentities( $value ),
				'EXTRAGLOB_TYPE_BOOLEAN' => ( ( $value == 'yes' )OR( $value == 'no' ) ) ?
				'normal' : 'hidden',
				'EXTRAGLOB_TYPE_STRING' => ( ( $value != 'yes' )AND( $value != 'no' ) ) ?
				'normal' : 'hidden',
				'EXTRAGLOB_ENABLED' => ( $value == 'yes' ) ? 'normal' : 'hidden',
				'EXTRAGLOB_DISABLED' => ( $value == 'no' ) ? 'normal' : 'hidden'
			);

		// Unused: Logs
		// TODO: implement somewhere (subpage of Settings?)
	if ( @isset( $_GET[ 'logs' ] ) ) {
		$logdir = '/var/log/samba4/';
		$scandir = scandir( $logdir );
		if ( !is_array( $scandir ) )
			$scandir = array();
		unset( $scandir[ 0 ] );
		unset( $scandir[ 1 ] );
		foreach ( $scandir as $id => $logname )
			if ( !is_file( $logdir . $logname ) )
				unset( $scandir[ $id ] );
		$samba_log_list = '';
		foreach ( $scandir as $logname )
			if ( @$_GET[ 'select' ] == $logname )
				$samba_log_list .= ' <option value="' . htmlentities( $logname )
				. '" selected="selected">' . htmlentities( $logname ) . '</option>' . chr( 10 );
		else
			$samba_log_list .= ' <option value="' . htmlentities( $logname ) . '">'
		. htmlentities( $logname ) . '</option>' . chr( 10 );

		if ( !empty( $scandir ) ) {
			reset( $scandir );
			if ( @isset( $_GET[ 'select' ] ) )
				if ( in_array( $_GET[ 'select' ], $scandir ) )
					$samba_log_path = $logdir . $_GET[ 'select' ];
			if ( !@isset( $samba_log_path ) )
				$samba_log_path = $logdir . current( $scandir );
			$samba_log = @htmlentities( file_get_contents( $samba_log_path ) );
		}

		// add javascript code to scroll to bottom of preformatted text box
		page_register_headelement( '
   <script type="text/javascript">
    window.onload=function() {
     var objDiv = document.getElementById("samba_logbox");
     objDiv.scrollTop = objDiv.scrollHeight;
    };
   </script>' );
	}

	// export new tags
	return @array(
		'PAGE_TITLE' => 'Samba settings',
		'PAGE_ACTIVETAB' => 'Settings',

		'CLASS_SAMBA_NOTRUNNING' => $class_notrunning,
		'CLASS_SAMBA_CORRUPTCONFIG' => $class_corruptconfig,
		'CLASS_SM_SHARE' => $sm_share,
		'CLASS_SM_DOMAIN' => $sm_domain,
		'CLASS_SM_ADS' => $sm_ads,
		'CLASS_SM_SERVER' => $sm_server,
		'CLASS_AB_LDAPSAM' => $ab_ldapsam,
		'CLASS_AB_SMBPASSWD' => $ab_smbpasswd,
		'SPECIAL_PERMISSIONS' => $special_permissions,
		'CB_SPECIAL_DFREE' => $cb_special_dfree,
		'OPT_PERMISSIONS_0660' => $opt_permissions_0660,
		'OPT_PERMISSIONS_0600' => $opt_permissions_0600,
		'OPT_PERMISSIONS_CUSTOM' => $opt_permissions_custom,
		'CLASS_SAMBA_NOSHARES' => $class_noshares,
		'CLASS_DELETESELECTEDSHARES' => $class_deleteselectedshares,
		'SAMBA_WORKGROUP' => $workgroup,

		// Samba settings
		'TABLE_SAMBA_GLOBALVARS' => $table_samba_globalvars,
		'TABLE_SAMBA_SHAREGLOBVARS' => $table_samba_shareglobalvars,
		'TABLE_SAMBA_EXTRAGLOBALS' => $table_samba_extraglobals,
		'SAMBA_VERSION' => $sambaversion,
		'SAMBA_WORKGROUP' => $workgroup,
		'SAMBA_NETBIOSNAME' => $netbiosname,
		'SAMBA_SERVERCOMMENT' => $servercomment,
		'SAMBA_ASYNC_READ' => $async_read,
		'SAMBA_ASYNC_WRITE' => $async_write,
		'SAMBA_ASYNCWB_ON' => $asyncwb_on,
		'SAMBA_ASYNCWB_OFF' => $asyncwb_off,
		'SAMBA_SENDFILE_ON' => $sendfile_on,
		'SAMBA_SENDFILE_OFF' => $sendfile_off,

		// Samba logs
		'SAMBA_LOG_LIST' => $samba_log_list,
		'SAMBA_LOG' => $samba_log,
		'SAMBA_LOG_PATH' => $samba_log_path,
	);
}


/* table functions */

function table_samba_globalvariables() {
	// required library
	activate_library( 'samba' );
	$configvars = samba_variables_global();
	$table_configvars = array();
	foreach ( $configvars as $varname )
		if ( !@isset( $sambaconf[ 'res' ][ $sharename ][ $varname ] ) )
			$table_configvars[] = array(
				'CV_VAR' => htmlentities( $varname )
			);
	return $table_configvars;
}

function table_samba_sharevariables( $sambaconf, $sharename = false ) {
	// required library
	activate_library( 'samba' );
	$configvars = samba_variables_share();
	$table_configvars = array();
	foreach ( $configvars as $varname )
		if ( !$sharename OR( !@isset( $sambaconf[ 'shares' ][ $sharename ][ $varname ] ) ) )
			$table_configvars[] = array(
				'CV_VAR' => htmlentities( $varname )
			);
	return $table_configvars;
}


/* submit functions */

function submit_access_samba_settings() {
	// required library
	activate_library( 'samba' );

	// read samba configuration
	$sambaconf = samba_readconfig();
	if ( $sambaconf === false )
		error( 'Could not read Samba configuration file!' );

	// redirect URL
	$redir = 'access.php?samba&settings';

	// restart samba
	if ( @isset( $_POST[ 'samba_restart_samba' ] ) ) {
		$result = samba_restartservice();
		if ( $result == 0 )
			friendlynotice( 'samba restarted!', $redir );
		else
			friendlyerror( 'could not restart Samba (' . $result . ')', $redir );
	}

	// modify global samba configuration
	if ( @isset( $_POST[ 'samba_update_config' ] ) ) {
		$newconf = $sambaconf;

		// process global variables
		foreach ( $_POST as $name => $value )
			if ( substr( $name, 0, strlen( 'global-' ) ) == 'global-' ) {
				$globalattr = trim( str_replace( '_', ' ', substr( $name, strlen( 'global-' ) ) ) );
				$newconf[ 'global' ][ $globalattr ] = trim( $value );
			} elseif ( substr( $name, 0, strlen( 'extraglob-' ) ) == 'extraglob-' ) {
				$globalattr = trim( str_replace( '_', ' ',
					substr( $name, strlen( 'extraglob-' ) ) ) );
				$newconf[ 'global' ][ $globalattr ] = trim( $value );
			}
		elseif ( substr( $name, 0, strlen( 'cbglobal0-' ) ) == 'cbglobal0-' ) {
			$globalattr = trim( str_replace( '_', ' ',
				substr( $name, strlen( 'cbglobal0-' ) ) ) );
			$newconf[ 'global' ][ $globalattr ] = 'no';
		}
		elseif ( substr( $name, 0, strlen( 'cbglobal1-' ) ) == 'cbglobal1-' ) {
			$globalattr = trim( str_replace( '_', ' ',
				substr( $name, strlen( 'cbglobal1-' ) ) ) );
			$newconf[ 'global' ][ $globalattr ] = 'yes';
		}

		// remove extra globals
		$removeglobals = array();
		foreach ( $_POST as $name => $value )
			if ( substr( $name, 0, strlen( 'cb_removeglob_' ) ) == 'cb_removeglob_' )
				if ( $value == 'on' ) {
					$removeglob = trim( str_replace( '_', ' ',
						substr( $name, strlen( 'cb_removeglob_' ) ) ) );
					$removeglobals[ $removeglob ] = $removeglob;
				}

				// file permissions
		if ( @strlen( $_POST[ 'special-permissions' ] ) == 9 ) {
			$special_permissions = @$_POST[ 'special-permissions' ];
			$perm_file = @substr( $special_permissions, 0, 4 );
			$perm_dir = @substr( $special_permissions, 5, 4 );
			if ( strlen( $perm_dir ) != 4 )
				error( 'file permissions not submitted properly' );
			$newconf[ 'global' ][ 'create mask' ] = $perm_file;
			$newconf[ 'global' ][ 'directory mask' ] = $perm_dir;
		}

		// dfree command
		if ( @$_POST[ 'cbspecial_dfree' ] == 'on' ) {
			$dfree_script = '/usr/local/www/zfsguru/scripts/dfree "%P"';
			$newconf[ 'global' ][ 'dfree command' ] = $dfree_script;
		} elseif ( ( @strlen( $_POST[ 'extraglobal-dfree command' ] ) < 2 )OR( @$_POST[ 'extraglobal-dfree command' ] == $dfree_script ) )
			if ( @isset( $newconf[ 'global' ][ 'dfree command' ] ) )
				$removeglobals[ 'dfree command' ] = 'dfree command';

			// add new variable to samba share configuration
		if ( ( strlen( @$_POST[ 'newvariable_varname' ] ) > 0 )AND( strlen( @$_POST[ 'newvariable_value' ] ) > 0 ) )
			$newconf[ 'global' ][ $_POST[ 'newvariable_varname' ] ] =
			$_POST[ 'newvariable_value' ];

		// save configuration
		$result = samba_writeconfig( $newconf, $removeglobals );

		// redirect
		if ( $result !== true )
			error( 'Error writing Samba configuration file ("' . $result . '")' );
		else
			friendlynotice( 'Samba configuration updated!', $redir );
	}
	friendlyerror( 'invalid form submitted', $redir );
}