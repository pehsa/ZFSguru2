<?php

/**
 * @param false $file
 *
 * @return mixed
 */
function bulletin_markread( $file = false )
{
    global $guru;

    // required library
    activate_library('gurudb');

    // retrieve bulletins
    $bindex = gurudb_bulletin($file);

    // find maximum date of bulletins (created/modified)
    $max = $guru[ 'preferences' ][ 'bulletin_lastread' ];
    foreach ( $bindex as $bulletin ) {
        $max = max($bulletin[ 'created' ], $bulletin[ 'modified' ]);
    }

    // store and return maximum date
    $guru[ 'preferences' ][ 'bulletin_lastread' ] = $max;
    $guru[ 'preferences' ][ 'bulletin_unread' ] = bulletin_unread($file);
    procedure_writepreferences($guru[ 'preferences' ]);
    return $max;
}

/**
 * @param false $file
 *
 * @return int
 */
function bulletin_unread( $file = false )
{
    // required library
    activate_library('gurudb');

    $bindex = gurudb_bulletin($file);
    $unread = 0;
    foreach ( $bindex as $bulletin ) {
        if (!bulletin_isread($bulletin) ) {
            $unread++;
        }
    }
    return $unread;
}

/**
 * @param $bulletin
 *
 * @return bool
 */
function bulletin_isread( $bulletin )
{
    global $guru;
    return ( max($bulletin[ 'created' ], $bulletin[ 'modified' ]) <=
    $guru[ 'preferences' ][ 'bulletin_lastread' ] );
}

/**
 * @param $types
 * @param $colours
 */
function bulletin_types( & $types, & $colours )
{
    $types = [
    'gen' => 'general',
    'rel' => 'release',
    'sec' => 'security',
    'pro' => 'problem',
    'dev' => 'development',
    ];
    $colours = [
    'gen' => 'warningrow',
    'rel' => 'specialrow',
    'sec' => 'failurerow',
    'pro' => 'normal',
    'dev' => 'activerow',
    ];
}
