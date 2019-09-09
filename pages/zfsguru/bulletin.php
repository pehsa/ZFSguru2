<?php

function content_zfsguru_bulletin() 
{
    // required library
    activate_library('gurudb');

    // call function
    $bulletins = gurudb_bulletin();
    //viewarray($bulletins);

    // queried bulletin message
    $bid = @$_GET[ 'bulletin' ];
    $body = ( @isset($bulletins[ $bid ]) ) ? gurudb_bulletin_body($bid) : '';

    // classes
    $class_bulletin = ( ( int )$bid > 0 ) ? 'normal' : 'hidden';
    $class_bulletinlist = ( ( int )$bid < 1 ) ? 'normal' : 'hidden';
    $class_modified = ( ( int )@$bulletins[ $bid ][ 'modified' ] > ( int )@$bulletins[ $bid ][ 'created' ] ) ? 'normal' : 'hidden';

    // export new tags
    return array(
    'PAGE_TITLE' => 'Bulletin messages',
    'PAGE_ACTIVETAB' => 'Bulletin',
    'TABLE_BULLETINS' => table_bulletins($bulletins),
    'CLASS_BULLETIN' => $class_bulletin,
    'CLASS_BULLETINLIST' => $class_bulletinlist,
    'CLASS_MODIFIED' => $class_modified,
    'BULLETIN_ID' => ( int )$bid,
    'BULLETIN_TITLE' => @htmlentities($bulletins[ $bid ][ 'title' ]),
    'BULLETIN_BODY' => $body,
    'BULLETIN_CREATED' => @date('j M Y H:i:s', $bulletins[ $bid ][ 'created' ]),
    'BULLETIN_MODIFIED' => @date('j M Y H:i:s', $bulletins[ $bid ][ 'modified' ]),
    );
}

function table_bulletins( $bulletins ) 
{
    global $guru;

    // required library
    activate_library('bulletin');

    $table = array();
    ksort($bulletins);
    foreach ( array_reverse($bulletins, true) as $id => $data ) {
        // skip bulletin messages which do not conform to specified type
        if (@$_GET[ 'type' ] ) {
            if ($data[ 'type' ] != $_GET[ 'type' ] ) {
                continue;
            }
        }
        // view only unread messages if applicable
        if (@$_GET[ 'view' ] == 'unread' ) {
            if (bulletin_isread($data) ) {
                continue;
            }
        }

        // determine type/colour/name
        $class_row = 'darkrow';
        bulletin_types($types, $colours);
        foreach ( $types as $short => $long ) {
            if ($short == $data[ 'type' ] ) {
                $class_row = $colours[ $short ];
            }
        }
        // we make unread bulletins bold
        $class_bold = ( bulletin_isread($data) ) ? 'normal' : 'bold';
        $table[] = array(
        'CLASS_ROW' => $class_row,
        'CLASS_BOLD' => $class_bold,
        'BULL_ID' => ( int )$id,
        'BULL_TYPESHORT' => @$data[ 'type' ],
        'BULL_TYPELONG' => @$types[ $data[ 'type' ] ],
        'BULL_TITLE' => htmlentities($data[ 'title' ]),
        'BULL_CREATED' => date('j M Y H:i:s', $data[ 'created' ]),
        );
    }
    return $table;
}

function submit_bulletin() 
{
    // url
    $url = 'zfsguru.php?bulletin';

    // view
    if (@isset($_POST[ 'submit_bulletin_viewall' ]) ) {
        redirect_url($url);
    }
    if (@isset($_POST[ 'submit_bulletin_viewunread' ]) ) {
        redirect_url($url . '&view=unread');
    }

    // type
    foreach ( $_POST as $name => $value ) {
        if (substr($name, 0, strlen('submit_bulletin_type')) == 'submit_bulletin_type' ) {
            redirect_url($url . '&type=' . substr($name, strlen('submit_bulletin_type')));
        }
    }

    // required library
    activate_library('bulletin');

    // button: mark all bulletins as read
    if (@isset($_POST[ 'submit_bulletin_markread' ]) ) {
        bulletin_markread();
    }

    // redirect
    redirect_url($url);
}
