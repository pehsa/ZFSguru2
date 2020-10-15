<?php

// passive functions

/**
 * @param false $filepath
 *
 * @return array
 */
function loaderconf_readsettings( $filepath = false )
{
    $loaderpath = ( $filepath ) ?: '/boot/loader.conf';
    $loaderconf = @file_get_contents($loaderpath);
    $loadervars = [
    'vm.kmem_size', 'vm.kmem_size_max',
    'vfs.zfs.arc_min', 'vfs.zfs.arc_max',
    'vfs.zfs.arc_meta_limit',
    'vfs.zfs.zfetch.array_rd_sz', 'vfs.zfs.zfetch.block_cap',
    'vfs.zfs.vdev.min_pending', 'vfs.zfs.vdev.max_pending'
    ];
    // regexp for active loader variables
    $preg_loader = [
    1 => '/^[\s]*([a-zA-Z0-9._-]+)[\s]*\=[\s]*\"?([a-zA-Z0-9._-]+)\"?[\s]*$/m',
    2 => '/^[\s]*\#([a-zA-Z0-9._-]+)[\s]*\=[\s]*\"?([a-zA-Z0-9._-]+)\"?[\s]*$/m'
    ];
    preg_match_all($preg_loader[ 1 ], $loaderconf, $active);
    // we also detect 'commented out' variables, prefixed with a #
    preg_match_all($preg_loader[ 2 ], $loaderconf, $commented);
    // start with hardcoded vars
    $loader = [];
    foreach ( $loadervars as $loadervar ) {
        $loader[ $loadervar ] = ['enabled' => false, 'value' => ''];
    }
    // now process each known loader variable
    foreach ( $commented[ 1 ] as $id => $loadervar ) {
        $loader[ $loadervar ] = [
            'enabled' => false,
        'value' => $commented[ 2 ][ $id ]
        ];
    }
    foreach ( $active[ 1 ] as $id => $loadervar ) {
        $loader[ $loadervar ] = [
            'enabled' => true,
        'value' => $active[ 2 ][ $id ]
        ];
    }
    return $loader;
}

/**
 * @return array
 */
function loaderconf_profiles()
{
    return [
    'none' => [
    'static' => [
                'vm.kmem_size' => false,
                'vfs.zfs.arc_max' => false,
                'vfs.zfs.arc_min' => false,
                'vfs.zfs.prefetch_disable' => false,
    ]
    ],
    'minimal' => [
    'multiply' => [
                'vm.kmem_size' => 1.5,
                'vfs.zfs.arc_max' => 0.1,
                'vfs.zfs.arc_min' => 0.1
    ]
    ],
    'conservative' => [
    'multiply' => [
                'vm.kmem_size' => 1.5,
                'vfs.zfs.arc_max' => 0.3,
                'vfs.zfs.arc_min' => 0.2
    ]
    ],
    'balanced' => [
    'multiply' => [
                'vm.kmem_size' => 1.5,
                'vfs.zfs.arc_max' => 0.5,
                'vfs.zfs.arc_min' => 0.2
    ]
    ],
    'performance' => [
    'multiply' => [
                'vm.kmem_size' => 1.5,
                'vfs.zfs.arc_max' => 0.6,
                'vfs.zfs.arc_min' => 0.4
    ],
    'static' => [
                'vfs.zfs.prefetch_disable' => '0',
    ]
    ],
    'aggressive' => [
    'multiply' => [
                'vm.kmem_size' => 1.5,
                'vfs.zfs.arc_max' => 0.75,
                'vfs.zfs.arc_min' => 0.5
    ],
    'static' => [
                'vfs.zfs.prefetch_disable' => '0',
    ]
    ],
    'i386' => [
    'static' => [
                'vm.kmem_size' => '512M',
                'vfs.zfs.arc_max' => '128M',
                'vfs.zfs.arc_min' => '128M',
                'vfs.zfs.prefetch_disable' => '1'
    ]
    ],
    ];
}

/**
 * @return false|int|string
 */
function loaderconf_activeprofile()
{
    // fetch data
    $loaderconf = loaderconf_readsettings();
    $profiles = loaderconf_profiles();
    $physmem = ( int )common_sysctl('hw.physmem');
    $physmem_gib = round($physmem / ( 1024 * 1024 * 1024 ), 1);

    // traverse profiles array and look for the first matching profile
    foreach ( $profiles as $profilename => $profile ) {
        if (@is_array($profile[ 'multiply' ]) ) {
            foreach ( $profile[ 'multiply' ] as $multivar => $value ) {
                if (round($physmem_gib * $value, 1) . 'g' != $loaderconf[ $multivar ][ 'value' ]OR $loaderconf[ $multivar ][ 'enabled' ] !== true ) {
                    continue 2;
                }
            }
        }
        if (@is_array($profile[ 'static' ]) ) {
            foreach ( $profile[ 'static' ] as $staticvar => $value ) {
                if (( $loaderconf[ $staticvar ][ 'value' ] != $value OR $loaderconf[ $staticvar ][ 'enabled' ] !== true )AND( $value != false OR $loaderconf[ $staticvar ][ 'enabled' ] !== false ) ) {
                    continue 2;
                }
            }
        }
        return $profilename;
    }
    return false;
}


/*
 ** active functions
 */


/**
 * @param       $profilename
 * @param false $loadersettings
 * @param false $loaderconf
 *
 * @return array|false
 */
function loaderconf_reset( $profilename, $loadersettings = false,
    $loaderconf = false 
) {
    // resets loader.conf with some recommended values; preserves existing options
    // TODO: outdated, needs updated distribution code
    global $guru;

    // if loadersettings is not supplied, retrieve a loader.conf from files dir
    if (!is_array($loadersettings) ) {
        // call function
        $dist = common_distribution_type();
        if ($dist === 'livecd'
            OR $dist === 'embedded'
        ) {
            $source_loaderconf = $guru[ 'docroot' ] . 'files/emb_loader.conf';
        } elseif ($dist === 'RoZ' ) {
            $source_loaderconf = $guru[ 'docroot' ] . 'files/roz_loader.conf';
        } else {
            // set warning and use Root-on-ZFS loader.conf
            page_feedback(
                'unknown distribution type detected! '
                . 'Using Root-on-ZFS loader.conf instead.', 'a_warning' 
            );
            $source_loaderconf = $guru[ 'docroot' ] . 'files/roz_loader.conf';
        }

        // root privileges
        activate_library('super');

        // copy loader.conf and reset proper permissions
        super_execute('/bin/cp -p ' . $source_loaderconf . ' /boot/loader.conf');
        super_execute('/usr/sbin/chown root:wheel /boot/loader.conf');
        super_execute('/bin/chmod 644 /boot/loader.conf');
        // now fetch loadersettings
        $loadersettings = loaderconf_readsettings();
    }

    // select memory tuning profile
    $profiles = loaderconf_profiles();
    if (@isset($profiles[ $profilename ]) ) {
        $profile = $profiles[ $profilename ];
    } else {
        $profile = @$profiles[ 'none' ];
        page_feedback(
            'unknown memory profile! '
            . 'Using <i>no memory tuning</i> profile.', 'a_warning' 
        );
    }

    // calculate physical memory in GiB
    $physmem = ( int )common_sysctl('hw.physmem');
    $physmem_gib = round($physmem / ( 1024 * 1024 * 1024 ), 1);
    if ($physmem_gib < 0.75 ) {
        error(
            'Less than 768MiB physical memory. Memory tuning not possible; '
            . 'add more RAM!' 
        );
    }

    // process factors that multiply with the physical RAM in GiB
    foreach ( $profile[ 'multiply' ] as $loadervar => $factor ) {
        $loadersettings[ $loadervar ] = [
            'enabled' => true, 'value' =>
        round($physmem_gib * $factor, 1) . 'g'
        ];
    }

    // process static values (false value means disable)
    foreach ( $profile[ 'static' ] as $loadervar => $value ) {
        if ($value !== false) {
            $loadersettings[ $loadervar ] = ['enabled' => true, 'value' => $value];
        } else {
            $loadersettings[ $loadervar ][ 'enabled' ] = false;
        }
    }

    // save loadersettings
    loaderconf_update($loadersettings, $loaderconf);
    return $loadersettings;
}

/**
 * @param       $new_settings
 * @param false $filepath
 *
 * @return bool
 */
function loaderconf_update( $new_settings, $filepath = false )
{
    global $guru;
    // determine which file to work on
    $loaderpath = ( $filepath ) ?: '/boot/loader.conf';
    // read raw config
    $rawconf = @file_get_contents($loaderpath);
    // read current config
    $loaderconf = loaderconf_readsettings($filepath);
    // compare new config
    foreach ( $new_settings as $loadervar => $data ) {
        $preg_commented = '/^[\s]*\#[\s]*' . str_replace('.', '\.', $loadervar)
        . '[\s]*\=.*$/m';
        if (@$loaderconf[ $loadervar ][ 'enabled' ] ) {
            if ($data[ 'enabled' ] ) {
                // currently enabled but want different value; adjust!
                $preg = '/^[\s]*' . str_replace('.', '\.', $loadervar) . '[\s]*\=.*$/m';
                $newvar = $loadervar . '="' . $data[ 'value' ] . '"';
                $rawconf = preg_replace($preg, $newvar, $rawconf, 1);
            } else {
                // currently enabled but want to disable (comment out)
                $preg = '/^[\s]*' . str_replace('.', '\.', $loadervar) . '[\s]*\=.*$/m';
                $newvar = '#' . $loadervar . '="' . $data[ 'value' ] . '"';
                $rawconf = preg_replace($preg, $newvar, $rawconf, 1);
            }
        } elseif (preg_match($preg_commented, $rawconf) ) {
            // variable is commented out
            if ($data[ 'enabled' ] ) {
                // activate commented out variable
                $newvar = $loadervar . '="' . $data[ 'value' ] . '"';
                $rawconf = preg_replace($preg_commented, $newvar, $rawconf, 1);
            }
        }
        elseif ($data[ 'enabled' ] ) {
            // non-existent; append to loader.conf
            $rawconf .= chr(10) . '# added by ZFSguru web-interface' . chr(10)
            . $loadervar . '="' . $data[ 'value' ] . '"' . chr(10);
        }
    }

    // super privileges
    activate_library('super');

    // write configuration to disk
    $result = file_put_contents($guru[ 'tempdir' ] . '/newloader.conf', $rawconf);
    if (!$result ) {
        return false;
    }
    $result = super_execute(
        '/bin/mv ' . $guru[ 'tempdir' ] . '/newloader.conf '
        . $loaderpath 
    );
    if ($result[ 'rv' ] != 0 ) {
        return false;
    }
    super_execute('/usr/sbin/chown root:wheel ' . $loaderpath);
    super_execute('/bin/chmod 644 ' . $loaderpath);
    return true;
}
