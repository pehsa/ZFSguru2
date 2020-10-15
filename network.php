<?php

// import main lib
require 'includes/main.php';

// navtabs
$tabs = [
    'Interfaces' => 'network.php',
    'Ports' => 'network.php?ports',
    'Firewall' => 'network.php?firewall',
];
if (@$guru[ 'preferences' ][ 'advanced_mode' ] !== true ) {
    unset($tabs[ 'Link aggregation' ]);
}

// select page
if (@isset($_GET[ 'monitor' ]) ) {
    $content = content_handle('network', 'monitor');
} elseif (@isset($_GET[ 'ports' ]) ) {
    $content = content_handle('network', 'ports');
} elseif (@isset($_GET[ 'firewall' ]) ) {
    $content = content_handle('network', 'firewall');
} elseif (@isset($_GET[ 'lagg' ]) ) {
    $content = content_handle('network', 'lagg');
} elseif (@isset($_GET[ 'dns' ]) ) {
    $content = content_handle('network', 'dns');
} elseif (@isset($_GET[ 'dhcp' ]) ) {
    $content = content_handle('network', 'dhcp');
} elseif (@isset($_GET[ 'pxe' ]) ) {
    $content = content_handle('network', 'pxe');
} elseif (@isset($_GET[ 'query' ]) ) {
    $content = content_handle('network', 'networkquery');
} else {
    $content = content_handle('network', 'network');
}

// serve page
page_handle($content);
