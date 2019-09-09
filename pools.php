<?php

// import main lib
require 'includes/main.php';

// navtabs
$tabs = array(
    'Pool status' => 'pools.php',
    'Create' => 'pools.php?create',
    'Expansion' => 'pools.php?expansion',
    'Cache devices' => 'pools.php?cache',
    'Log devices' => 'pools.php?slog',
    'Hot spares' => 'pools.php?spare',
    'Benchmark' => 'pools.php?benchmark'
);
if (@$guru[ 'preferences' ][ 'advanced_mode' ] !== true ) {
    unset($tabs[ 'Log devices' ], $tabs[ 'Hot spares' ]);
}

// select page
if (@isset($_GET[ 'create' ]) ) {
    $content = content_handle('pools', 'create');
} elseif (@isset($_GET[ 'version' ]) ) {
    $content = content_handle('pools', 'version');
} elseif (@isset($_GET[ 'expansion' ]) ) {
    $content = content_handle('pools', 'expansion');
} elseif (@isset($_GET[ 'cache' ]) ) {
    $content = content_handle('pools', 'cache');
} elseif (@isset($_GET[ 'slog' ]) ) {
    $content = content_handle('pools', 'slog');
} elseif (@isset($_GET[ 'spare' ]) ) {
    $content = content_handle('pools', 'spare');
} elseif (@isset($_GET[ 'benchmark' ]) ) {
    $content = content_handle('pools', 'benchmark');
} elseif (@isset($_GET[ 'query' ]) ) {
    $content = content_handle('pools', 'query');
} else {
    $content = content_handle('pools', 'pools');
}

// serve page
page_handle($content);
