<?php

/**
 * @return array
 */
function content_access_ssh()
{
    $username = 'ssh';
    $ipaddress = $_SERVER[ 'SERVER_ADDR' ];

    // export new tags
    return [
    'PAGE_TITLE' => 'SSH access',
    'PAGE_ACTIVETAB' => 'SSH',
    'SSH_USERNAME' => $username,
    'SSH_IPADDRESS' => $ipaddress
    ];
}

function submit_access_ssh_reset() 
{
    global $guru;

    // redirect URL
    $redir = 'access.php?ssh';

    // reset SSH password
    if (@isset($_POST[ 'access_ssh_reset' ]) ) {
        // sanity checks
        if (@strlen($_POST[ 'ssh_reset_pw' ]) < 4 ) {
            friendlyerror(
                'Password length too low; enter at least four characters',
                $redir 
            );
        }
        if (@$_POST[ 'ssh_reset_pw' ] != @$_POST[ 'ssh_reset_pw2' ] ) {
            friendlyerror(
                'The second password you entered differs from the first',
                $redir 
            );
        }

        // super privileges
        activate_library('super');

        // call reset password script
        // write new password to temp file
        file_put_contents(
            $guru[ 'tempdir' ] . '/zfsguru_newsshpasswd.dat',
            trim($_POST[ 'ssh_reset_pw' ]) 
        );
        // call reset password script
        $result = super_script('reset_ssh_passwd');
        // remove temp password file (redundant)
        @unlink($guru[ 'tempdir' ] . '/zfsguru_newsshpasswd.dat');

        // act on result
        if ($result[ 'rv' ] === 0 ) {
            friendlynotice('a new SSH password has been set!', $redir);
        } else {
            error('Could not reset SSH password!');
        }
    }
    redirect_url($redir);
}
