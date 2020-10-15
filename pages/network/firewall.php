<?php

/**
 * @return array
 */
function content_network_firewall()
{
    activate_library('super');

    // fetch pf.conf
    $pfconf = @file_get_contents('/etc/pf.conf');

    // read pf.conf manual page
    if (is_executable('/usr/local/bin/man2html') ) {
        $pfman = shell_exec('/usr/bin/man pf.conf | /usr/local/bin/man2html -bare 2>&1');
        $pfman = substr($pfman, strpos($pfman, '<H2>'));
    } else {
        $pfman = '<p><b>Note:</b> since you are running an older system version, '
        . 'the output is not formatted as nicely.</p><pre>'
        . @htmlentities(shell_exec('man pf.conf')) . '</pre>';
    }

    // pf running?
    $pfctl = super_execute('/sbin/pfctl -s info 2>&1| /usr/bin/head -n 1 2>&1');
    if (strpos($pfctl[ 'output_str' ], 'Status: Enabled') !== false ) {
        $class_running = 'normal';
        $class_notrunning = 'hidden';
    } else {
        $class_running = 'hidden';
        $class_notrunning = 'normal';
    }

    // update date of pf.conf
    clearstatcache();
    $updated = 'last updated: ';
    $diff = time() - ( int )@filemtime('/etc/pf.conf');
    if ($diff < 60 ) {
        $updated .= $diff . ' seconds ago';
    } elseif ($diff < 60 * 60 ) {
        $updated .= floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 60 * 60 * 24 ) {
        $updated .= floor($diff / ( 60 * 60 )) . ' hours ago';
    } elseif ($diff < 60 * 60 * 24 * 30 ) {
        $updated .= floor($diff / ( 60 * 60 * 24 )) . ' days ago';
    } elseif ($diff < 60 * 60 * 24 * 365 ) {
        $updated .= floor($diff / ( 60 * 60 * 24 * 30 )) . ' months ago';
    } else {
        $updated .= round($diff / ( 60 * 60 * 24 * 365 ), 1) . ' years ago';
    }

    // export tags
    return [
    'CLASS_PF_RUNNING' => $class_running,
    'CLASS_PF_NOTRUNNING' => $class_notrunning,
    'NETWORK_FW_PFCONF' => htmlentities($pfconf),
    'NETWORK_FW_PFMAN' => $pfman,
    'NETWORK_FW_UPDATED' => $updated,
    ];
}

function submit_network_firewall() 
{
    // required library
    activate_library('network');

    // handle restart pf button
    if (@isset($_POST[ 'submit_network_firewall_restart' ]) ) {
        if (!network_firewall_checkconfig($errorline) ) {
            page_feedback(
                'cannot activate - there is an error in your firewall '
                . 'configuration on line ' . ( int )$errorline, 'a_error' 
            );
        } elseif (!network_firewall_activate() ) {
            page_feedback('could not activate pf firewall - unknown error!', 'a_error');
        }
    } elseif (@$_POST[ 'network_firewall_pfconf' ] ) {
        network_firewall_newconfig($_POST[ 'network_firewall_pfconf' ]);
        if (!network_firewall_checkconfig($errorline) ) {
            page_feedback(
                'there is an error in your firewall configuration on line '
                . ( int )$errorline, 'a_warning' 
            );
        }
    }

    // redirect
    redirect_url('network.php?firewall');
}
