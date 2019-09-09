<?php

function content_network_pxe() 
{
    // required library
    activate_library('dnsmasq');

    // include stylesheet from dns page
    page_register_stylesheet('pages/network/dns.css');

    // dnsmasq configuration
    $dnsmasq = dnsmasq_readconfig();

    // tables
    $table[ 'tftp' ][ 'string' ] = array();
    $table[ 'tftp' ][ 'switch' ] = array();

    // populate tables
    $servicetype = 'tftp';
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

    // export tags
    return array(
    'PAGE_TITLE' => 'PXE',
    'PAGE_ACTIVETAB' => 'PXE',
    'TABLE_TFTP_STRING' => $table[ 'tftp' ][ 'string' ],
    'TABLE_TFTP_SWITCH' => $table[ 'tftp' ][ 'switch' ],
    );
}
