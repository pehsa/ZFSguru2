<?php

// import main lib
require 'includes/main.php';

// navtabs
$tabs = [
    'Shares' => 'access.php?samba&shares',
    'Users' => 'access.php?samba&users',
    'Settings' => 'access.php?samba&settings',
    'NFS' => 'access.php?nfs',
    'SSH' => 'access.php?ssh'
];

$content = '';

// select page
if (@isset($_GET[ 'nfs' ]) ) {
    $content = content_handle('access', 'nfs');
} elseif (@isset($_GET[ 'iscsi' ]) ) {
    $content = content_handle('access', 'iscsi');
} elseif (@isset($_GET[ 'ssh' ]) ) {
    $content = content_handle('access', 'ssh');
} elseif (@isset($_GET[ 'samba' ]) ) {
    if (!isset($_GET['share'], $_GET['shares'])) {
        $content = content_handle('access', 'samba_shares');
    } elseif (@isset($_GET[ 'users' ]) ) {
        $content = content_handle('access', 'samba_users');
    } elseif (@isset($_GET[ 'settings' ]) ) {
        $content = content_handle('access', 'samba_settings');
    } else {
        redirect_url('access.php');
    }
}
else {
    $content = content_handle('access', 'samba_shares');
}

// serve content
page_handle($content);
