<?php

function content_system_install_submit() {
	// should not be here; redirect
	$url = 'system.php?install&progress';
	friendlyerror( 'Oops; you were at the wrong place', $url );
}

function submit_system_install()
// installs Root-on-ZFS or Embedded distribution on target device
{
	// required library
	activate_library( 'zfsguru' );

	// defer to library function
	zfsguru_install( $_POST );

	// zfsguru function should redirect URL, so this should never execute:
	error( 'unhandled exit after having called installation function' );
}