<?php

/**
 * @param $svc
 *
 * @return bool|null
 */
function internalservice_querystart( $svc )
{
    // required library
    activate_library('service');

    // call function
    $iservices = internalservice_fetch();

    // query autostart of $svc
    return service_isprocessrunning(@$iservices[ $svc ][ 'process' ]);
}

/**
 * @param $svc
 *
 * @return bool|null
 */
function internalservice_queryautostart( $svc )
{
    // required library
    activate_library('service');

    $iservices = internalservice_fetch();
    $rclist = @$iservices[ $svc ][ 'rclist' ];
    if (empty($rclist)OR!is_array($rclist) ) {
        return null;
    }
    $autostart = true;
    if (is_array($rclist) ) {
        foreach ( $rclist as $rcfragment ) {
            $rcconf = service_runcontrol_isenabled($rcfragment);
            if (!$rcconf ) {
                $autostart = false;
            }
        }
    }
    return $autostart;
}

/**
 * @param      $svc
 * @param bool $autostart
 *
 * @return bool
 */
function internalservice_autostart( $svc, $autostart = true )
{
    // required library
    activate_library('service');

    $iservices = internalservice_fetch();
    $rclist = @$iservices[ $svc ][ 'rclist' ];
    if (empty($rclist)OR!is_array($rclist) ) {
        return false;
    }
    $current = internalservice_queryautostart($svc);
    if ($current AND $autostart) {
        return true;
    }

    if (!$current AND!$autostart) {
        return true;
    }
    foreach ( $rclist as $rcfragment ) {
        if ($autostart ) {
            service_runcontrol_enable($rcfragment);
        } else {
            service_runcontrol_disable($rcfragment);
        }
    }
    return true;
}

/**
 * @return bool
 */
function internalservice_isrunning_pf()
{
    // super privileges
    activate_library('super');

    // gather info
    $result = super_execute('/sbin/pfctl -s info');

    if (preg_match('/^Status: Enabled/m', $result[ 'output_str' ])) {
        return true;
    }

    if (preg_match('/^Status: Disabled/m', $result[ 'output_str' ])) {
        return false;
    }

    page_feedback('could not determine pf firewall status', 'a_warning');

    return false;
}

/**
 * @return array[]
 */
function internalservice_fetch()
{
    global $guru;

    // determine webserver in use; lighttpd or apache
    if (stripos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false ) {
        $websrv = [
        'longname' => 'Lighttpd',
        'process' => 'lighttpd',
        'bg_script' => $guru[ 'docroot' ] . '/scripts/restart_lighttpd.sh'
        ];
    } elseif (stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false ) {
        $websrv = [
        'longname' => 'Apache',
        'process' => 'httpd',
        'bg_script' => $guru[ 'docroot' ] . '/scripts/restart_apache.sh'
        ];
    } else {
        $websrv = [
        'longname' => 'unknown webserver',
        'process' => ''
        ];
    }

    // return internal services array
    return [
    'webserver' => [
    'longname' => $websrv[ 'longname' ],
    'desc' => 'Webserver used for ZFSguru',
    'process' => $websrv[ 'process' ],
    'script' => '',
    //   'rclist' => array('lighttpd', 'apache22', 'apache24'),
    'rclist' => ['lighttpd'],
    'bg_script' => $websrv[ 'bg_script' ],
    'only_restart' => true
    ],
    'cron' => [
    'longname' => 'Cron',
    'desc' => 'Task scheduler',
    'process' => 'cron',
    'script' => '/etc/rc.d/cron',
    'rclist' => ['cron']
    ],
    'ctld' => [
    'longname' => 'iSCSI-target',
    'desc' => 'CTL daemon handles the connections for iSCSI-target',
    'process' => 'ctld',
    'script' => '/etc/rc.d/ctld',
    'rclist' => ['ctld']
    ],
    'moused' => [
    'longname' => 'Mouse',
    'desc' => 'Provides mouse support on the monitor',
    'process' => 'moused',
    'script' => '/etc/rc.d/moused',
    'rclist' => ['moused']
    ],
    'named' => [
    'longname' => 'Nameserver',
    'desc' => 'DNS internet name server',
    'process' => 'named',
    'script' => '/etc/rc.d/named',
    'rclist' => ['named']
    ],
    'nfs' => [
    'longname' => 'NFS daemon',
    'desc' => 'NFS file sharing commonly used by Linux',
    'process' => 'nfsd',
    'script' => '/etc/rc.d/nfsd',
    'rclist' => [
                'nfs_server',
                'mountd',
                'rpcbind',
                'rpc_lockd',
                'rpc_statd'
    ]
    ],
    'ntpd' => [
    'longname' => 'NTP daemon',
    'desc' => 'Network date/time synchronization server',
    'process' => 'ntpd',
    'script' => '/etc/rc.d/ntpd',
    'rclist' => ['ntpd']
    ],
    'openssh' => [
    'longname' => 'OpenSSH',
    'desc' => 'SSH remote login server',
    'process' => 'sshd',
    'script' => '/etc/rc.d/sshd',
    'rclist' => ['sshd']
    ],
    'pf' => [
    'longname' => 'Firewall',
    'desc' => 'Packet firewall kernel module',
    'process' => '',
    'script' => '/etc/rc.d/pf',
    'func_isrunning' => 'isrunning_pf',
    'rclist' => ['pf']
    ],
    'powerd' => [
    'longname' => 'Power daemon',
    'desc' => 'CPU power (throttle) service',
    'process' => 'powerd',
    'script' => '/etc/rc.d/powerd',
    'rclist' => ['powerd']
    ],
    'samba' => [
    'longname' => 'Samba',
    'desc' => 'Windows-native filesharing service',
    'process' => 'smbd',
    'script' => '/usr/local/etc/rc.d/samba_server',
    'rclist' => ['samba']
    ],
    'sendmail' => [
    'longname' => 'Sendmail',
    'desc' => 'SMTP email server',
    'process' => 'sendmail',
    'script' => '/etc/rc.d/sendmail',
    'rclist' => [
                'sendmail',
                'sendmail_clientmqueue',
                'sendmail_msp_queue'
    ]
    ]
    ];
}
