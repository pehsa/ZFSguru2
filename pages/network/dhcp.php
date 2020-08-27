<?php

function content_network_dhcp() 
{
    // required library
    activate_library('dnsmasq');

    // include stylesheet from dns page
    page_register_stylesheet('pages/network/dns.css');

    // dnsmasq configuration
    $dnsmasq = dnsmasq_readconfig();

    // tables
    $table[ 'dhcp' ][ 'string' ] = array();
    $table[ 'dhcp' ][ 'switch' ] = array();

    // populate tables
    $servicetype = 'dhcp';
    if (@is_array($dnsmasq[ $servicetype ]) ) {
        foreach ( $dnsmasq[ $servicetype ] as $datatype => $dataarray ) {
            if (is_array($dataarray) ) {
                foreach ( $dataarray as $configvar ) {
                    $table[ $servicetype ][ $datatype ][ $configvar ] = array(
                    strtoupper($servicetype) . '_' . strtoupper($datatype) . '_NAME' =>
                    htmlentities($configvar),
                    strtoupper($servicetype) . '_' . strtoupper($datatype) . '_VALUE' =>
                    @htmlentities($dnsmasq[ $servicetype ][ $datatype ][ $configvar ]),
                    );
                }
            }
        }
    }

    // table: network interfaces for dhcp configuration
    activate_library('network');
    $netif = network_interfaces();
    $table_dhcp_if = array();
    foreach ( $netif as $ifname => $ifdata ) {
        if (strpos($ifname, 'lo') !== 0) {
            $maskhex = @$ifdata[ 'inet' ][ 0 ][ 'netmask' ];
            $mask = '';
            if ($maskhex ) {
                $iparr = array();
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

            $table_dhcp_if[] = array(
            'DHCP_IF_NAME' => htmlentities($ifname),
            'DHCP_IF_INET4' => @$ifdata[ 'ip' ],
            'DHCP_IF_INET6' => @$ifdata[ 'inet6' ][ 0 ][ 'ip' ],
            'DHCP_IF_MASK4' => $mask,
            'DHCP_IF_MASK6' => @$ifdata[ 'inet6' ][ 0 ][ 'prefixlen' ],
            'DHCP_IF_CONNECTED' => '',
            );
        }
    }
    //viewarray($netif);
    //viewarray($table_dhcp_if);

    // export tags
    return array(
    'PAGE_TITLE' => 'DNSmasq',
    'PAGE_ACTIVETAB' => 'DNSmasq',
    'TABLE_DHCP_STRING' => $table[ 'dhcp' ][ 'string' ],
    'TABLE_DHCP_SWITCH' => $table[ 'dhcp' ][ 'switch' ],
    'TABLE_DHCP_IF' => $table_dhcp_if,
    );
}
