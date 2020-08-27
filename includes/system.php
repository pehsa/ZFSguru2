<?php

function system_users()
{
    $rawtxt = shell_exec("cat /etc/passwd");
    preg_match_all(
        '/^([^#\n:]*):(.*):(.*):(.*):(.*):(.*):[^#\n]*$/m',
        $rawtxt, $matches 
    );
    $users = array();
    if (@is_array($matches[ 1 ]) ) {
        foreach ( $matches[ 1 ] as $id => $username ) {
            if ($username !== 'toor' ) {
                $users[ @$matches[ 3 ][ $id ] ] = @array(
                    'username' => $username,
                    'password' => $matches[ 2 ][ $id ],
                    'userid' => $matches[ 3 ][ $id ],
                    'groupid' => $matches[ 4 ][ $id ],
                    'desc' => $matches[ 5 ][ $id ],
                    'homedir' => $matches[ 6 ][ $id ],
                    'shell' => $matches[ 7 ][ $id ],
                    );
            }
        }
    }
    // sort by userid
    uasort($users, 'sortbyid');
    return $users;
}

function system_groups()
{
    $rawtxt = shell_exec("cat /etc/group");
    preg_match_all('/^([^#\n:]*):(.*):(.*):([^#\n]*)$/m', $rawtxt, $matches);
    $groups = array();
    if (@is_array($matches[ 1 ]) ) {
        foreach ( $matches[ 1 ] as $id => $groupname ) {
            $groups[ @$matches[ 3 ][ $id ] ] = @array(
                'groupname' => $groupname,
                'unkown' => $matches[ 2 ][ $id ],
                'groupid' => $matches[ 3 ][ $id ],
                'users' => $matches[ 4 ][ $id ],
            );
        }
    }
    // sort by groupid
    uasort($groups, 'sortbyid');
    return $groups;
}

function sortbyid( $a, $b )
{
    if (count($a) == 7 ) {
        $aa = @$a[ 'userid' ];
        $bb = @$b[ 'userid' ];
    } else {
        $aa = @$a[ 'groupid' ];
        $bb = @$b[ 'groupid' ];
    }
    if ($aa == $bb ) {
        return 0;
    }
    return ( $aa < $bb ) ? -1 : 1;
}

function system_adduser( $username, $password = false, $userid = false,
    $group = false, $useroptions = false 
) {
    // creates a new system account; input (username) should be sanitized!
    // TODO: password is not implemented
    // elevated privileges
    activate_library('super');

    // sanity
    // note that $username must be sanitized before calling this function!
    if ($username == '') {
        error('no username provided; cannot create new user account');
    }

    // options
    $uid = '';
    if (is_numeric($userid) ) {
        $uid = ' -u ' . $userid;
    }
    $membergroup = ($group != '') ? ' -g ' . $group : '';
    $shell = ' -s /sbin/nonexistent';

    // create user account
    if ($useroptions ) {
        $result = super_execute(
            '/usr/sbin/pw useradd ' . $username . $uid
            . $membergroup . ' ' . $useroptions 
        );
    } else {
        $result = super_execute('/usr/sbin/pw useradd ' . $username . $uid . $shell);
    }

    return !($result['rv'] != 0);
}

function system_user_delete( $username, $delete_homedir = false )
{
    // elevated privileges
    activate_library('super');
    // remove user from samba database
    activate_library('samba');
    samba_remove_user($username);
    super_execute('/usr/local/bin/smbpasswd -x "' . $username . '"');
    // remove user
    $command = '/usr/sbin/pw userdel -n "' . $username . '"';
    $result = super_execute($command);

    return $result['rv'] == 0;
}

function system_group_delete( $groupname, $delete_resident_users = false )
{
    // elevated privileges
    activate_library('super');
    // remove group from samba database
    activate_library('samba');
    samba_remove_group($groupname);
    // remove group
    $command = '/usr/sbin/pw groupdel -n "' . $groupname . '"';
    $result = super_execute($command);

    return $result['rv'] == 0;
}

function system_mountpoints()
{
    $mp = array();
    exec('/sbin/mount', $output, $rv);
    if ($rv != 0 ) {
        page_feedback('unable to check mountpoints!', 'a_warning');
        return $mp;
    }
    if (is_array($output) ) {
        foreach ( $output as $line ) {
            if (preg_match('/(.*) on (\/.*) \((.*)\)$/', $line, $matches) ) {
                $mp[ @$matches[ 1 ] ] = array(
                    'device' => @$matches[ 1 ],
                    'mountpoint' => @$matches[ 2 ],
                    'options' => @$matches[ 3 ]
                    );
            }
        }
    }
    return $mp;
}

function system_ismounted( $disk, $mountpoints = false )
{
    if ($mountpoints == false ) {
        $mp = system_mountpoints();
    } else {
        $mp = $mountpoints;
    }
    if (@isset($mp[ $disk ]) ) {
        return true;
    }

    return false;
}

function system_detect_vmenvironment()
{
    $dmesg_boot = @file_get_contents('/var/run/dmesg.boot');
    if (strpos($dmesg_boot, 'VBOX VBOXXSDT') !== false ) {
        return 'vbox';
    }
    return false;
    // todo
    if (stripos($dmesg_boot, 'esxi_rdm') !== false) {
        return 'esxi_rdm';
    }

    if (stripos($dmesg_boot, 'esxi_vtd') !== false) {
        return 'esxi_vtd';
    } elseif (stripos($dmesg_boot, 'esxi') !== false ) {
        return 'esxi';
    } elseif (stripos($dmesg_boot, 'qemu') !== false ) {
        return 'qemu';
    } elseif (stripos($dmesg_boot, 'vmware') !== false ) {
        return 'vmware';
    } else {
        return false;
    }
}

function system_detect_networkspeed()
{
    exec('/sbin/ifconfig', $output, $rv);
    $outputstr = ( is_array($output) ) ? implode(chr(10), $output) : '';
    if (strpos($outputstr, '100Gbase') !== false) {
        return '100 gigabit';
    }

    if (strpos($outputstr, '10Gbase') !== false) {
        return '10 gigabit';
    } elseif (strpos($outputstr, '1000base') !== false ) {
        return 'Gigabit';
    } elseif (strpos($outputstr, '100base') !== false ) {
        return '100 megabit';
    } elseif (strpos($outputstr, '10base') !== false ) {
        return '10 megabit';
    } else {
        return 'unknown';
    }
}

function system_detect_physmem()
{
    $dmesg_boot = file_get_contents('/var/run/dmesg.boot');
    preg_match_all(
        '/^(real|avail) memory[\s]+= ([0-9]+) \(.*\)[\s]*$/m',
        $dmesg_boot, $matches 
    );
    return array(
    'installed' => ( int )@$matches[ 2 ][ 0 ],
    'usable' => ( int )@$matches[ 2 ][ 1 ],
    );
}

function system_uptime()
{
    $uptime_cmd = shell_exec('/usr/bin/uptime');
    preg_match('/load averages: ([0-9.]+),/', $uptime_cmd, $matches);
    $uptime_tmp = substr($uptime_cmd, strpos($uptime_cmd, 'up ') + 3);
    return array(
    'uptime' => substr($uptime_tmp, 0, strpos($uptime_tmp, ',')),
    'loadavg' => trim(substr($uptime_cmd, strrpos($uptime_cmd, ':') + 1)),
    'cpupct' => ( ( double )@$matches[ 1 ] * 100 ) . ' %',
    );
}

function system_loadkernelmodule( $kmod )
{
    activate_library('super');
    $result = super_execute(
        '/sbin/kldload '
        . escapeshellarg('/boot/kernel/' . $kmod . '.ko') 
    );
    return ( $result[ 'rv' ] == 0 );
}
