<?php

// import main lib
require 'includes/main.php';

// set navtabs
$tabs = [
    'Status' => 'status.php',
    'Processor usage' => 'status.php?cpu',
    'Memory usage' => 'status.php?memory',
    'Logs' => 'status.php?log',
];
// hide advanced tabs unless advanced_mode is set
if (@$guru[ 'preferences' ][ 'advanced_mode' ] !== true ) {
    unset($tabs[ 'Logs' ]);
}

// select page
if (@isset($_GET[ 'hardware' ]) ) {
    $content = content_handle('status', 'hardware');
} elseif (@isset($_GET[ 'cpu' ]) ) {
    $content = content_handle('status', 'cpu');
} elseif (@isset($_GET[ 'memory' ]) ) {
    $content = content_handle('status', 'memory');
} elseif (@isset($_GET[ 'log' ]) ) {
    $content = content_handle('status', 'log');
} else {
    $content = content_handle('status', 'status');
}

// serve page
page_handle($content);
