<?php

// import main lib
require 'includes/main.php';

// navtabs
$tabs = array(
    'Manage' => 'services.php?manage',
);

// add extra tabs for services with panel interfaces
activate_library('service');
$tabs_svc_panels = service_panels();
foreach ( $tabs_svc_panels as $tabs_svc_cats ) {
    foreach ( $tabs_svc_cats as $tabs_panel ) {
        $tabs[ htmlentities($tabs_panel[ 'longname' ]) ] = 'services.php?panel='
        . htmlentities($tabs_panel[ 'name' ]);
    }
}

// select page
if (@isset($_GET[ 'manage' ])AND @isset($_GET[ 'query' ]) ) {
    $content = content_handle('services', 'query');
} elseif (@isset($_GET[ 'panel' ]) ) {
    // handoff panel request to service library
    service_panel_handle($_GET[ 'panel' ]);
    // error if not handled
    error('panel function did not terminate!');
}
else {
    $content = content_handle('services', 'manage');
}

// serve content
page_handle($content);
