<?php

function content_network_network() 
{
    // required library
    activate_library('network');

    // call function
    $interfaces = network_interfaces();

    // queried interface
    $queryif = @$_GET[ 'query' ];

    // process table IFLIST
    $iflist = array();
    foreach ( $interfaces as $ifname => $ifdata ) {
        // check interface type
        $iftype = network_checkinterface($ifname);
        // classes
        $class_activerow = ( ( strlen($ifname) > 0 )AND( $ifname == $queryif ) ) ?
        'activerow' : 'normal';
        $class_wired = ( $iftype == 'wired' ) ? 'normal' : 'hidden';
        $class_wireless = ( $iftype == 'wireless' ) ? 'normal' : 'hidden';
        $class_loopback = ( $iftype == 'loopback' ) ? 'normal' : 'hidden';
        $class_other = ( $iftype == 'other' ) ? 'normal' : 'hidden';

        // ident
        $ident_maxlen = 50;
        if (@strlen($ifdata[ 'ident' ]) > $ident_maxlen ) {
            $ident = '<acronym title="' . htmlentities($ifdata[ 'ident' ]) . '">'
            . substr(htmlentities($ifdata[ 'ident' ]), 0, $ident_maxlen) . '..</acronym>';
        } else {
            $ident = htmlentities($ifdata[ 'ident' ]);
        }
        // manual ident for loopback adapter
        if ($ifname == 'lo0' ) {
            $ident = 'Loopback adapter (special system adapter)';
        }

        $iflist[] = array(
        'CLASS_ACTIVEROW' => $class_activerow,
        'CLASS_WIRED' => $class_wired,
        'CLASS_WIRELESS' => $class_wireless,
        'CLASS_LOOPBACK' => $class_loopback,
        'CLASS_OTHER' => $class_other,
        'IF_NAME' => $ifname,
        'IF_IDENT' => $ident,
        'IF_IP' => $ifdata[ 'ip' ],
        'IF_STATUS' => $ifdata[ 'status' ],
        'IF_MTU' => $ifdata[ 'mtu' ],
        'IF_MAC' => $ifdata[ 'ether' ]
        );
    }

    // export new tags
    $newtags = array(
    'PAGE_ACTIVETAB' => 'Interfaces',
    'PAGE_TITLE' => 'Network interfaces',
    'TABLE_NETWORK_IFLIST' => $iflist
    );
    return $newtags;
}
