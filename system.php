<?php

// import main lib
require 'includes/main.php';

// navtabs
$tabs = array(
    'Preferences' => 'system.php?pref',
    'Booting' => 'system.php?booting',
    'Install' => 'system.php?install',
    'Tuning' => 'system.php?tuning',
    'Command line' => 'system.php?cli',
    'Update' => 'system.php?update',
    'Shutdown' => 'system.php?shutdown'
);

// hide certain tabs when not enabled
if (@$guru[ 'preferences' ][ 'advanced_mode' ] !== true ) {
    unset($tabs['Tuning'], $tabs['Command line'], $tabs['Migration']);
}

// select page
if (@isset($_GET[ 'pref' ]) ) {
    $content = content_handle('system', 'preferences');
} elseif (@isset($_GET[ 'booting' ]) ) {
    $content = content_handle('system', 'booting');
} elseif (@isset($_GET[ 'install' ]) ) {
    // different content for each installation step
    if (@isset($_GET[ 'progress' ]) ) {
        $content = content_handle('system', 'install_progress');
    } elseif (@isset($_GET[ 'startinstall' ]) ) {
        $content = content_handle('system', 'install_submit');
    } elseif (@isset($_GET[ 'version' ])AND @isset($_GET[ 'target' ]) ) {
        $content = content_handle('system', 'install_step3');
    } elseif (@isset($_GET[ 'version' ]) ) {
        $content = content_handle('system', 'install_step2');
    } else {
        $content = content_handle('system', 'install_step1');
    }
}
elseif (@isset($_GET[ 'tuning' ]) ) {
    $content = content_handle('system', 'tuning');
} elseif (@isset($_GET[ 'cli' ]) ) {
    $content = content_handle('system', 'cli');
} elseif (@isset($_GET[ 'root' ]) ) {
    $content = content_handle('system', 'root');
} elseif (@isset($_GET[ 'update' ]) ) {
    $content = content_handle('system', 'update');
} elseif (@isset($_GET[ 'migration' ]) ) {
    $content = content_handle('system', 'migration');
} elseif (@isset($_GET[ 'shutdown' ]) ) {
    $content = content_handle('system', 'shutdown');
} elseif (@isset($_GET[ 'activation' ]) ) {
    $content = content_handle('system', 'activation');
} else {
    redirect_url('system.php?pref');
}

// serve page
page_handle($content);
