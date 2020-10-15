<?php

/**
 * @return array
 */
function content_panel_dnsmasq()
{
    // tabbar
    $tab = @$_GET[ 'tab' ];
    // tabbar
    $tabbar = [
    'dnsmasq' => 'DNSmasq',
    'dns' => 'DNS',
    'dhcp' => 'DHCP',
    'tftp' => 'TFTP',
    'pxe' => 'PXE',
    ];
    $tabbar_url = 'services.php?panel=dnsmasq';
    foreach ( $tabbar as $tag => $name ) {
        if (@isset($_GET[ $tag ]) ) {
            $tabbar_tab = '&' . $tag;
            $class_tabbar[ $tag ] = 'normal';
        } else {
            $class_tabbar[ $tag ] = 'hidden';
        }
    }

    // dnsmasq
    $dnsmasq = dnsmasq_readconfig();

    // tables
    $table[ 'dns' ][ 'string' ] = [];
    $table[ 'dns' ][ 'switch' ] = [];
    $table[ 'dhcp' ][ 'string' ] = [];
    $table[ 'dhcp' ][ 'switch' ] = [];
    $table[ 'tftp' ][ 'string' ] = [];
    $table[ 'tftp' ][ 'switch' ] = [];

    // populate tables
    foreach ( $dnsmasq[ 'configvars' ] as $servicetype => $servicedata ) {
        foreach ( $servicedata as $datatype => $dataarray ) {
            if (is_array($dataarray) ) {
                foreach ( $dataarray as $configvar ) {
                    $table[ $servicetype ][ $datatype ][ $configvar ] = [
                    strtoupper($servicetype) . '_' . strtoupper($datatype) . '_NAME' =>
                    htmlentities($configvar),
                    strtoupper($servicetype) . '_' . strtoupper($datatype) . '_VALUE' =>
                    @htmlentities($dnsmasq[ $servicetype ][ $datatype ][ $configvar ]),
                    ];
                }
            }
        }
    }

    // table: network interfaces for dhcp configuration
    activate_library('network');
    $netif = network_interfaces();
    $table_dhcp_if = [];
    foreach ( $netif as $ifname => $ifdata ) {
        if (strncmp($ifname, 'lo', 2) !== 0) {
            $maskhex = @$ifdata[ 'inet' ][ 0 ][ 'netmask' ];
            $mask = '';
            if ($maskhex ) {
                $iparr = [];
                for ( $i = 2; $i < 10; $i += 2 ) {
                    $iparr[] = hexdec(
                        $maskhex {
                        $i
                        } . $maskhex {
                        $i + 1
                        } 
                    );
                }
                $mask = implode('.', $iparr);
            }

            //   $ip = explode('.', $mask);
            //   $HEXIP = sprintf('%02x%02x%02x%02x', $ip[0], $ip[1], $ip[2], $ip[3]);
            //error($mask);
            //   $netmask = long2ip(-1 << (32 - (int)$int));

            $table_dhcp_if[] = [
            'DHCP_IF_NAME' => htmlentities($ifname),
            'DHCP_IF_INET4' => @$ifdata[ 'ip' ],
            'DHCP_IF_INET6' => @$ifdata[ 'inet6' ][ 0 ][ 'ip' ],
            'DHCP_IF_MASK4' => $mask,
            'DHCP_IF_MASK6' => @$ifdata[ 'inet6' ][ 0 ][ 'prefixlen' ],
            'DHCP_IF_CONNECTED' => '',
            ];
        }
    }
    //viewarray($netif);
    //viewarray($table_dhcp_if);

    // export tags
    return [
    'PAGE_TITLE' => 'DNSmasq',
    'PAGE_ACTIVETAB' => 'DNSmasq',
    'PAGE_TABBAR' => $tabbar,
    'PAGE_TABBAR_URL' => $tabbar_url,
    'PAGE_TABBAR_URLTAB' => $tabbar_url . $tabbar_tab,
    'TABLE_DNS_STRING' => $table[ 'dns' ][ 'string' ],
    'TABLE_DNS_SWITCH' => $table[ 'dns' ][ 'switch' ],
    'TABLE_DHCP_STRING' => $table[ 'dhcp' ][ 'string' ],
    'TABLE_DHCP_SWITCH' => $table[ 'dhcp' ][ 'switch' ],
    'TABLE_TFTP_STRING' => $table[ 'tftp' ][ 'string' ],
    'TABLE_TFTP_SWITCH' => $table[ 'tftp' ][ 'switch' ],
    'TABLE_DHCP_IF' => $table_dhcp_if,
    'CLASS_TAB_DNSMASQ' => $class_tabbar[ 'dnsmasq' ],
    'CLASS_TAB_DNS' => $class_tabbar[ 'dns' ],
    'CLASS_TAB_DHCP' => $class_tabbar[ 'dhcp' ],
    'CLASS_TAB_TFTP' => $class_tabbar[ 'tftp' ],
    'CLASS_TAB_PXE' => $class_tabbar[ 'pxe' ],
    ];
}

/**
 * @param false $raw
 *
 * @return array
 */
function dnsmasq_readconfig( $raw = false )
{
    // fetch DNSmasq configuration file
    $config = [];
    $filepath = '/usr/local/etc/dnsmasq.conf';
    $contents = @file_get_contents($filepath);
    if ($raw ) {
        $config[ 'raw' ] = $contents;
    }

    // match: ^ var= value # comment $
    preg_match_all(
        '/^[\s]*([^#\s\n][^=\n]*)[\s]*='
        . '[\s]*([^#\n]+)[\s]*(#[\s]*(.*))?[\s]*$/Um', $contents, $matches1
    );

    // match: ^ var # comment $
    preg_match_all(
        '/^[\s]*([^#\s\n][^=\n]*)[\s]*(#[\s]*(.*))?[\s]*$/Um',
        $contents, $matches2 
    );

    // fetch configuration variables
    $dnsmasq = dnsmasq_configvars();
    $config[ 'configvars' ] = $dnsmasq;

    // migrate into single configuration array
    $allthree = ['dns', 'dhcp', 'tftp'];
    foreach ( $allthree as $servicetype ) {
        foreach ( $matches1[ 1 ] as $id => $varname ) {
            if (@in_array($varname, $dnsmasq[$servicetype]['string'], true)) {
                $config[ $servicetype ][ 'string' ][ $varname ] = $matches1[ 2 ][ $id ];
            }
        }
        foreach ( $matches2[ 1 ] as $id => $varname ) {
            if (@in_array($varname, $dnsmasq[$servicetype]['switch'], true)) {
                $config[ $servicetype ][ 'switch' ][ $varname ] = $matches2[ 2 ][ $id ];
            }
        }
    }

    // return rich array
    return $config;
}

function dnsmasq_writeconfig()
{
    error('todo!');
}

/**
 * @return array
 */
function dnsmasq_pxeprofiles()
{
    return [];
}

/**
 * @return array[]
 */
function dnsmasq_configvars()
{
    $dns = [
    'activation' => 'port',
    'string' => [
    'port',
    'resolv-file',
    'server',
    'local',
    'address',
    'ipset',
    'user',
    'group',
    'interface',
    'except-interface',
    'listen-address',
    'no-dhcp-interface',
    'addn-hosts',
    'domain',
    ],
    'switch' => [
    'domain-needed',
    'bogus-priv',
    'filterwin2k',
    'strict-order',
    'no-resolv',
    'no-poll',
    'bind-interfaces',
    'no-hosts',
    'expand-hosts',
    ],
    ];
    $dhcp = [
    'activation' => 'dhcp-range',
    'string' => [
    'dhcp-range',
    'dhcp-host',
    'dhcp-ignore',
    'dhcp-vendorclass',
    'dhcp-userclass',
    'dhcp-mac',
    'dhcp-option',
    'dhcp-option-force',
    'dhcp-boot',
    'dhcp-match',
    'pxe-prompt',
    'pxe-service',
    ],
    'switch' => [
    'enable-ra',
    'read-ethers',
    ],
    ];
    $tftp = [
    'activation' => 'enable-tftp',
    'string' => [
    'tftp-root',
    ],
    'switch' => [
    'enable-tftp',
    'tftp-secure',
    'tftp-no-blocksize',
    ],
    ];
    return compact('dns', 'dhcp', 'tftp');
}
