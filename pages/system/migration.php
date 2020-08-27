<?php

function content_system_migration() 
{
    global $guru;

    // required library
    activate_library('migration');

    // spaces table
    $spaces = migration_spaces();

    // add a free space at the end
    $spaces[] = array(
    'name' => 'free',
    'type' => 'free',
    'size' => 0,
    'changed' => 'no'
    );

    // query migration space
    $query = @$_GET[ 'migration' ];
    $qmig = @$spaces[ $query ];
    $query_name = @htmlentities($qmig[ 'name' ]);

    // sanity
    if ($query AND!is_array($qmig) ) {
        friendlyerror('this migration spot does not exist!', 'system.php?migration');
    }

    // table migrationspaces
    $table_mig = array();
    $count = count($spaces);
    for ( $i = 1; $i <= $count; $i += 3 ) {
        $class = array();
        for ( $y = $i; $y <= $i + 2; $y++ ) {
            if (!is_array(@$spaces[ $y ]) ) {
                $spaces[ $y ] = array(
                'name' => 'free',
                'type' => 'free',
                'size' => 0,
                'changed' => 'no'
                );
            }
            $class[ $y ] = 'mig_' . $spaces[ $y ][ 'type' ];
            if ($y === ( int )$query ) {
                $class[ $y ] .= ' mig_selected';
            }
            $sel[ $y ] = ( $y === ( int )$query ) ? 'normal' : 'hidden';
        }
        // add row to table mig (who processes three table columns at once)
        $table_mig[] = @array(
        'MIG1_ID' => $i,
        'MIG1_CLASS' => $class[ $i ],
        'MIG1_SELECTED' => $sel[ $i ],
        'MIG1_NAME' => $spaces[ $i ][ 'name' ],
        'MIG1_TYPE' => $spaces[ $i ][ 'type' ],
        'MIG1_SIZE' => $spaces[ $i ][ 'size' ],
        'MIG1_CHANGED' => $spaces[ $i ][ 'changed' ],
        'MIG2_ID' => $i + 1,
        'MIG2_CLASS' => $class[ $i + 1 ],
        'MIG2_SELECTED' => $sel[ $i + 1 ],
        'MIG2_NAME' => $spaces[ $i + 1 ][ 'name' ],
        'MIG2_TYPE' => $spaces[ $i + 1 ][ 'type' ],
        'MIG2_SIZE' => $spaces[ $i + 1 ][ 'size' ],
        'MIG2_CHANGED' => $spaces[ $i + 1 ][ 'changed' ],
        'MIG3_ID' => $i + 2,
        'MIG3_CLASS' => $class[ $i + 2 ],
        'MIG3_SELECTED' => $sel[ $i + 2 ],
        'MIG3_NAME' => $spaces[ $i + 2 ][ 'name' ],
        'MIG3_TYPE' => $spaces[ $i + 2 ][ 'type' ],
        'MIG3_SIZE' => $spaces[ $i + 2 ][ 'size' ],
        'MIG3_CHANGED' => $spaces[ $i + 2 ][ 'changed' ]
        );
    }

    // classes
    $class_query = ($query != '') ? 'normal' : 'hidden';
    $class_migspecial = ( @$qmig[ 'type' ] === 'special' ) ? 'normal' : 'hidden';
    $class_miglight = ( @$qmig[ 'type' ] === 'light' ) ? 'normal' : 'hidden';
    $class_migheavy = ( @$qmig[ 'type' ] === 'heavy' ) ? 'normal' : 'hidden';
    $class_migfree = ( @$qmig[ 'type' ] === 'free' ) ? 'normal' : 'hidden';

    // page specific
    if ($class_migfree === 'normal' ) {
        // check for services
        activate_library('service');
        $slist = service_list();
        // construct services table
        $table_services = array();
        foreach ( $slist as $data ) {
            $table_services[] = @array(
            'SVC_SHORT' => htmlentities($data[ 'name' ]),
            'SVC_LONGNAME' => htmlentities($data[ 'longname' ]),
            'SVC_SIZE' => sizebinary(( int )$data[ 'size' ]),
            );
        }
        // document root
        $docroot = $guru[ 'docroot' ];
        // size of system files
        $msysfiles = migration_sysfiles();
        $size = array();
        foreach ( $msysfiles as $systag => $sysfiles ) {
            foreach ( $sysfiles as $sysfile ) {
                if (is_dir($sysfile) ) {
                    @$size[ $systag ] += @( int )filesize($sysfile);
                } elseif (is_file($sysfile) ) {
                    @$size[ $systag ] += @( int )filesize($sysfile);
                }
            }
        }
        // size of extra stuff
        $size[ 'web' ] = dirSize($docroot);
        $size[ 'home' ] = dirSize('/home');
        //$size['root'] = dirSize('/root');
    }

    // debug
    if (@isset($_GET[ 'debug' ]) ) {
        viewarray($spaces); // debug
    }

    // document root
    $docroot = $guru[ 'docroot' ];

    // export new tags
    return @array(
    'PAGE_TITLE' => 'Migration',
    'PAGE_ACTIVETAB' => 'Migration',
    'TABLE_MIGRATIONSPACES' => $table_mig,
    'TABLE_SERVICES' => $table_services,
    'CLASS_QUERY' => $class_query,
    'CLASS_MIGSPECIAL' => $class_migspecial,
    'CLASS_MIGLIGHT' => $class_miglight,
    'CLASS_MIGHEAVY' => $class_migheavy,
    'CLASS_MIGFREE' => $class_migfree,
    'QUERY_NAME' => $query_name,
    'QUERY_MIGID' => $query,
    'DOCROOT' => $docroot,
    'SIZE_LOADER' => sizebinary($size[ 'loader' ], 1),
    'SIZE_USER' => sizebinary($size[ 'user' ], 1),
    'SIZE_RC' => sizebinary($size[ 'rc' ], 1),
    'SIZE_SAMBA' => sizebinary($size[ 'samba' ], 1),
    'SIZE_PF' => sizebinary($size[ 'pf' ], 1),
    'SIZE_LIGHTTPD' => sizebinary($size[ 'lighttpd' ], 1),
    'SIZE_WEB' => sizebinary($size[ 'web' ], 1),
    'SIZE_HOME' => sizebinary($size[ 'home' ], 1),
    'SIZE_ROOT' => sizebinary($size[ 'root' ], 1),
    );
}

function dirSize( $directory ) 
{
    if (!is_dir($directory) ) {
        return 0;
    }
    $size = 0;
    foreach ( new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file ) {
        $size += $file->getSize();
    }
    return $size;
}


/* submit functions */

function submit_system_migration() 
{
    // required library
    activate_library('migration');

    // main submit function
    if (@isset($_POST[ 'export_webinterface' ]) ) {
        migration_export();
    }

    // URL redirect (url = overview page, url2 = query page)
    $url = 'system.php?migration';
    $mig_id = @$_POST[ 'mig_id' ];
    $url2 = ( $mig_id > 0 ) ? $url . '=' . ( int )$mig_id: $url;

    // create new profile
    if (@isset($_POST[ 'submit_migfree' ]) ) {
        // profile data
        $mig_name = @$_POST[ 'mig_longname' ];
        $mig_desc = @$_POST[ 'mig_description' ];
        $mig_size = 666;
        $mig_date = time();
        // fetch sysfiles array
        $msysfiles = migration_sysfiles();
        // determine which sysfiles were selected by user
        $selected = array();
        foreach ( $msysfiles as $systag => $sysfiles ) {
            foreach ( $sysfiles as $sysfile ) {
                if (@$_POST[ 'mig_cfg_' . $systag ] === 'on' ) {
                    $selected[] = $systag;
                }
            }
        }
        // construct profile
        $newprofile = array(
        'name' => $mig_name,
        'desc' => $mig_desc,
        'type' => 'light',
        'size' => $mig_size,
        'changed' => $mig_date,
        'contents' => array(
        'sysfiles' => $selected
        )
        );
        // save profile
        migration_addprofile($newprofile, $mig_id);
        // redirect
        page_feedback('new profile created!', 'b_success');
        redirect_url($url2);
    }

    // used profile actions
    if (@isset($_POST[ 'submit_migused' ]) ) {
        $action = @$_POST[ 'mig_action' ];
        switch ( $action ) {
         // light
        case 'promote':
            migration_promote($mig_id);
            break;
        case 'modify':
            friendlyerror('modify not yet implemented', $url2);
            break;
        case 'delete':
            migration_deleteprofile($mig_id);
            page_feedback('profile deleted!', 'c_notice');
            redirect_url($url);
            break;
            // heavy
        case 'update':
            friendlyerror('update not yet implemented', $url2);
            break;
        case 'activate':
            friendlyerror('activation not yet implemented', $url2);
            break;
        case 'download':
            friendlyerror('download not yet implemented', $url2);
            break;
        case 'prune':
            migration_prune($mig_id);
            break;
        default:
        }
    }

    // default redirect
    friendlynotice('nothing done.', $url2);
}
