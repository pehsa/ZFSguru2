<?php

function content_services_system() {
	global $guru;

	// required libraries
	activate_library( 'internalservice' );
	activate_library( 'service' );

	// fetch internal services
	$iservices = internalservice_fetch();

	// queried service
	$query = @$_GET[ 'query' ];

	// servicelist table
	$iservicelist = array();
	foreach ( $iservices as $iservice => $data ) {
		$activerow = ( $query == $iservice ) ? 'activerow' : 'normal';

		// determine running status
		if ( ( strlen( @$data[ 'func_isrunning' ] ) > 0 )AND( function_exists( 'internalservice_' . @$data[ 'func_isrunning' ] ) ) ) {
			$running = call_user_func( 'internalservice_' . $data[ 'func_isrunning' ] );
			if ( $running ) {
				$status = 'RUNNING';
				$class_status = 'green';
			} else {
				$status = 'STOPPED';
				$class_status = 'red';
			}
		} elseif ( strlen( $data[ 'process' ] ) < 1 ) {
			$status = 'PASSIVE';
			$class_status = 'grey';
		}
		elseif ( @$data[ 'isrunning' ] == 'unknown' ) {
			$status = 'Unknown';
			$class_status = 'grey';
		}
		elseif ( service_isprocessrunning( $data[ 'process' ] ) ) {
			$status = 'RUNNING';
			$class_status = 'green';
		}
		else {
			$status = 'STOPPED';
			$class_status = 'red';
		}

		// classes
		$class_startbutton = @( ( $status == 'STOPPED' )AND( !$data[ 'only_restart' ] ) ) ?
			'normal' : 'hidden';
		$class_stopbutton = @( ( $status == 'RUNNING' )AND( !$data[ 'only_restart' ] ) ) ?
			'normal' : 'hidden';
		$class_restartbutton = @( $data[ 'only_restart' ] == true ) ? 'normal' : 'hidden';

		// autostart
		$autostart = internalservice_queryautostart( $iservice );
		$class_autostart_y = ( $autostart === true ) ? 'normal' : 'hidden';
		$class_autostart_n = ( $autostart === false ) ? 'normal' : 'hidden';
		$class_autostart_p = ( $autostart === NULL ) ? 'normal' : 'hidden';

		// service name as text or link
		$serviceredir = array(
			'openssh' => 'access.php?ssh',
			'samba' => 'access.php?shares',
			'nfs' => 'access.php?nfs',
			'pf' => 'network.php?firewall',
		);
		$class_svctext = ( !@isset( $serviceredir[ $iservice ] ) ) ? 'normal' : 'hidden';
		$class_svclink = ( @isset( $serviceredir[ $iservice ] ) ) ? 'normal' : 'hidden';
		$service_url = @$serviceredir[ $iservice ];

		// add row to servicelist table
		$iservicelist[] = @array(
			'CLASS_ACTIVEROW' => $activerow,
			'CLASS_SVCTEXT' => $class_svctext,
			'CLASS_SVCLINK' => $class_svclink,
			'SERVICE_NAME' => htmlentities( $iservice ),
			'SERVICE_LONGNAME' => htmlentities( $data[ 'longname' ] ),
			'SERVICE_URL' => htmlentities( $service_url ),
			'SERVICE_PROCESS' => $data[ 'process' ],
			'SERVICE_DESC' => htmlentities( $data[ 'desc' ] ),
			'CLASS_STATUS' => $class_status,
			'SERVICE_STATUS' => $status,
			'CLASS_STOPBUTTON' => $class_stopbutton,
			'CLASS_STARTBUTTON' => $class_startbutton,
			'CLASS_RESTARTBUTTON' => $class_restartbutton,
			'CLASS_AUTOSTART_Y' => $class_autostart_y,
			'CLASS_AUTOSTART_N' => $class_autostart_n,
			'CLASS_AUTOSTART_P' => $class_autostart_p
		);
	}

	// hide noservices div when services are present
	$class_services = ( @empty( $iservices )OR strlen( $query ) > 0 ) ? 'hidden' : 'normal';
	$class_noservices = ( @empty( $iservices ) ) ? 'normal' : 'hidden';
	$class_qservice = ( strlen( $query ) > 0 ) ? 'normal' : 'hidden';

	// export new tags
	return array(
		'PAGE_ACTIVETAB' => 'Manage',
		'PAGE_TITLE' => 'System services',
		'TABLE_SERVICELIST' => @$iservicelist,
		'QSERVICE' => $query,
		'QSERVICE_LONG' => @$iservices[ $query ][ 'longname' ],
		'CLASS_SERVICES' => $class_services,
		'CLASS_NOSERVICES' => $class_noservices,
		'CLASS_QSERVICE' => $class_qservice
	);
}

function submit_services_system() {
	global $guru;

	// super privileges
	activate_library( 'internalservice' );
	activate_library( 'super' );

	// redirect url
	$url = 'services.php?manage&system';

	// fetch internal services
	$iservices = internalservice_fetch();

	// scan each POST variable
	foreach ( $_POST as $name => $value ) {

		if ( substr( $name, 0, strlen( 'svc_start_' ) ) == 'svc_start_' ) {
			// start service
			$svc = trim( substr( $name, strlen( 'svc_start_' ) ) );
			$svc = substr( $svc, 0, -2 );
			$lname = ( @$iservices[ $svc ][ 'longname' ] ) ?
				$iservices[ $svc ][ 'longname' ] : $svc;
			if ( @strlen( $iservices[ $svc ][ 'script' ] ) > 0 ) {
				$script = $iservices[ $svc ][ 'script' ];
				$result = super_execute( $script . ' onestart' );
				if ( @$result[ 'rv' ] !== 0 )
					friendlyerror( 'could not start ' . htmlentities( $lname ) . ' service!', $url );
				else {
					page_feedback( '<b>' . htmlentities( $lname ) . '</b> service started!',
						'b_success' );
					redirect_url( $url );
				}
			} else
				friendlyerror( htmlentities( $lname ) . ' service has no rc.d script!', $url );
		}

		if ( substr( $name, 0, strlen( 'svc_stop_' ) ) == 'svc_stop_' ) {
			// stop service
			$svc = trim( substr( $name, strlen( 'svc_stop_' ) ) );
			$svc = substr( $svc, 0, -2 );
			$lname = ( @$iservices[ $svc ][ 'longname' ] ) ?
				$iservices[ $svc ][ 'longname' ] : $svc;
			if ( @strlen( $iservices[ $svc ][ 'script' ] ) > 0 ) {
				$script = $iservices[ $svc ][ 'script' ];
				$result = super_execute( $script . ' onestop' );
				if ( @$result[ 'rv' ] !== 0 )
					friendlyerror( 'could not stop ' . htmlentities( $lname ) . ' service!', $url );
				else
					friendlynotice( htmlentities( $lname ) . ' service stopped!', $url );
			} else
				friendlyerror( htmlentities( $lname ) . ' service has no rc.d script!', $url );
		}

		if ( substr( $name, 0, strlen( 'svc_restart_' ) ) == 'svc_restart_' ) {
			// restart service
			$svc = trim( substr( $name, strlen( 'svc_restart_' ) ) );
			$svc = substr( $svc, 0, -2 );
			$lname = ( @$iservices[ $svc ][ 'longname' ] ) ?
				$iservices[ $svc ][ 'longname' ] : $svc;
			if ( @strlen( $iservices[ $svc ][ 'script' ] ) > 0 ) {
				$script = $iservices[ $svc ][ 'script' ];
				$result = super_execute( $script . ' restart' );
				if ( @$result[ 'rv' ] !== 0 )
					friendlyerror( 'could not restart ' . htmlentities( $lname ) . ' service!', $url );
				else
					friendlynotice( htmlentities( $lname ) . ' service restarted!', $url );
			} elseif ( @strlen( $iservices[ $svc ][ 'bg_script' ] ) > 0 ) {
				// special script execution on background
				$script = $iservices[ $svc ][ 'bg_script' ];
				$result = super_execute( $script . ' restart > /dev/null &' );
				if ( @$result[ 'rv' ] !== 0 )
					friendlyerror( 'could not restart ' . htmlentities( $lname ) . ' service!', $url );
				else
					friendlynotice( 'delayed restart of ' . htmlentities( $lname ), $url );
			}
			else
				friendlyerror( htmlentities( $lname ) . ' service has no rc.d script!', $url );
		}

		if ( substr( $name, 0, strlen( 'svc_autostart_y_' ) ) == 'svc_autostart_y_' ) {
			// automatically start service upon (re)boot
			$svc = trim( substr( $name, strlen( 'svc_autostart_y_' ) ) );
			$svc = substr( $svc, 0, -2 );
			$result = internalservice_autostart( $svc, true );
			if ( !$result )
				friendlyerror( 'could not enable automatic start of service <b>'
					. htmlentities( $svc ) . '</b>', $url );
		}

		if ( substr( $name, 0, strlen( 'svc_autostart_n_' ) ) == 'svc_autostart_n_' ) {
			// do NOT automatically start service upon (re)boot
			$svc = trim( substr( $name, strlen( 'svc_autostart_n_' ) ) );
			$svc = substr( $svc, 0, -2 );
			$result = internalservice_autostart( $svc, false );
			if ( !$result )
				friendlyerror( 'could not disable automatic start of service <b>'
					. htmlentities( $svc ) . '</b>', $url );
		}

	}

	// default redirect
	redirect_url( $url );
}