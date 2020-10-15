<?php

/**
 * @return array
 */
function content_network_ports()
{
    // required library
    activate_library('network');

    // retrieve sockstat data
    $sockstat = network_sockstat();

    // table
    $table_networkports = table_networkports($sockstat);

    // class
    $class_noports = ( empty($table_networkports) ) ? 'normal' : 'hidden';

    return [
    'PAGE_TITLE' => 'Network ports',
    'PAGE_ACTIVETAB' => 'Ports',
    'TABLE_NETWORK_PORTS' => $table_networkports,
    'CLASS_NOPORTS' => $class_noports,
    ];
}

/**
 * @param $sockstat
 *
 * @return array
 */
function table_networkports( $sockstat )
{
    $table = [];
    foreach ( $sockstat as $id => $row ) {
        foreach ( $row as $name => $value ) {
            $table[ $id ][ 'NP_' . strtoupper($name) ] = htmlentities($value);
        }
        $table[ $id ][ 'NP_PORT' ] = substr($row[ 'local' ], strrpos($row[ 'local' ], ':') + 1);
        $table[ $id ][ 'NP_LOCAL' ] = substr($row[ 'local' ], 0, strrpos($row[ 'local' ], ':'));
    }
    return $table;
}
