<?php

/**
 * @param bool $webnfs
 *
 * @return string[]
 */
function nfs_configuration_list( $webnfs = true )
{
    $config = [
    'alldirs' => 'Allow subdirectories to be mounted',
    'mapall' => 'Treat all remote users as one local user',
    'maproot' => 'Treat the remote root user as given local user',
    'network' => 'Restrict access to the specified network',
    'mask' => 'Restrict access to the specified network mask',
    'sec' => 'NFSv4 security flavor',
    'ro' => 'Read only NFS share',
    'quiet' => 'Surpress error logging for this share',
    ];
    if ($webnfs ) {
        $config[ 'webnfs' ] = 'WebNFS share (advanced)';
        $config[ 'index' ] = 'WebNFS directory filehandle';
        $config[ 'public' ] = 'WebNFS public access';
    }
    return $config;
}

/**
 * @param false $filesystems
 *
 * @return array|false
 */
function nfs_sharenfs_list( $filesystems = false )
{
    // required library
    activate_library('zfs');

    $sharenfs = zfs_filesystem_properties(
        $filesystems, 'sharenfs',
        'filesystem' 
    );
    // in case queried filesystem is not in the list, do a separate query
    if ($filesystems AND!@$sharenfs[ $filesystems ] ) {
        $sharenfs2 = zfs_filesystem_properties(
            $filesystems, 'sharenfs',
            'filesystem' 
        );
        // return false if queried filesystem does not exist it all
        if (!@isset($sharenfs2[ $filesystems ]) ) {
            return false;
        }
        $sharenfs3[ $filesystems ] = $sharenfs2[ $filesystems ];
        // do some tricks to sort properly
        foreach ( $sharenfs as $var => $val ) {
            if ($var != $filesystems ) {
                $sharenfs3[ $var ] = $val;
            }
        }
        $sharenfs = $sharenfs3;
    }
    if (!is_array($sharenfs) ) {
        return [];
    }
    $fs = [];
    foreach ( $sharenfs as $fsname => $fsdata ) {
        $fs[ $fsname ] = $fsname;
    }
    $mp = zfs_filesystem_properties(
        implode(' ', $fs),
        'mountpoint', 'filesystem' 
    );
    // assemble nfslist array
    $nfslist = [];
    foreach ( $sharenfs as $fsname => $fsdata ) {
        if (@$mp[ $fsname ][ 'mountpoint' ][ 'value' ] {        0        } === '/'
        ) {
            if (( $fsname == $filesystems )OR( @$fsdata[ 'sharenfs' ][ 'value' ] !== 'off' ) ) {
                $inherited = !in_array(
                    @$fsdata[ 'sharenfs' ][ 'source' ],
                    ['local', 'received']
                );
                $parent = ( !$inherited ) ? $fsname : substr(
                    $fsdata[ 'sharenfs' ][ 'source' ],
                    strlen('inherited from ') 
                );
                $options = [];
                // parse either by comma-separated string or by options begining with '-'
                if (strpos($fsdata[ 'sharenfs' ][ 'value' ], ',') !== false ) {
                    preg_match_all(
                        '/(.+)( (.+))?,|$/U', $fsdata[ 'sharenfs' ][ 'value' ],
                        $matches 
                    );
                } else {
                    preg_match_all(
                        '/-([^\s=\- ]+)(([= ])([^\s=\-]+))?/',
                        $fsdata[ 'sharenfs' ][ 'value' ], $matches 
                    );
                }
                foreach ( $matches[ 1 ] as $id => $optionname ) {
                    if (trim($optionname) !== '') {
                        $options[ trim($optionname) ][] = trim($matches[ 4 ][ $id ]);
                    }
                }
                $nfslist[ $fsname ] = [
                'mountpoint' => $mp[ $fsname ][ 'mountpoint' ][ 'value' ],
                'sharenfs' => $fsdata[ 'sharenfs' ][ 'value' ],
                'inherited' => $inherited,
                'parent' => $parent,
                'options' => $options,
                ];
            }
        }
    }
    return $nfslist;
}

/**
 * @return array
 */
function nfs_showmount_list()
{
    $nfscmd = 'showmount -e';
    exec($nfscmd, $output, $rv);
    preg_match_all('/^(\/.+) (.+)$/m', implode(chr(10), $output), $matches);
    $array_showmount = [];
    if (@is_array($matches[ 1 ]) ) {
        foreach ( $matches[ 1 ] as $id => $mountpoint ) {
            $array_showmount[ trim($mountpoint) ] = trim(@$matches[ 2 ][ $id ]);
        }
    }
    return $array_showmount;
}

/* NFS get/set functions */

/**
 * @param $sharenfs_fs
 *
 * @return string
 */
function nfs_getprofile( $sharenfs_fs )
{
    if ($sharenfs_fs[ 'sharenfs' ] === 'off' ) {
        return 'notshared';
    }
    $options = @$sharenfs_fs[ 'options' ];
    if (@$options[ 'mask' ][ 0 ] === '0.0.0.0' ) {
        return 'public';
    }
    if (@$options[ 'network' ][ 0 ] === '0.0.0.0/0' ) {
        return 'public';
    }
    if (@count($options[ 'network' ]) < 1 ) {
        return 'public';
    }
    if (( @count($options[ 'network' ]) == 1 )AND( @strpos($options[ 'network' ][ 0 ], '/32') !== false ) ) {
        return 'private';
    }
    return 'protected';
}

/**
 * @param        $fs
 * @param        $profile
 * @param string $privateip
 *
 * @return string[]
 */
function nfs_setprofile( $fs, $profile, $privateip = '0.0.0.0' )
{
    if ($profile === 'public' ) {
        return nfs_setsharenfs(
            $fs, [
                   'network' => ['0.0.0.0/0'],
            'mask' => ['0.0.0.0']
               ]
        );
    }
    if ($profile === 'protected' ) {
        return nfs_setsharenfs(
            $fs, [
            'network' => [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            ]
        ], ['mask']
        );
    }
    if ($profile === 'private' ) {
        return nfs_setsharenfs(
            $fs, ['network' => [$privateip . '/32']],
            ['mask']
        );
    }
    if ($profile === 'notshared' ) {
        return nfs_setsharenfs($fs, 'off');
    }
}

/**
 * @param $options
 *
 * @return bool
 */
function nfs_geteasypermissions( $options )
{
    return !@isset($options['maproot']) && @isset($options['alldirs']) && @$options['mapall'][0] === '1000:1000';
}

/**
 * @param      $fs
 * @param bool $enable
 *
 * @return string[]
 */
function nfs_seteasypermissions( $fs, $enable = true )
{
    if ($enable ) {
        return nfs_setsharenfs(
            $fs, [
            'alldirs' => [],
            'mapall' => ['1000:1000'],
        ], [
            'maproot',
            ]
        );
    }

    return nfs_setsharenfs(
        $fs, [], [
        'alldirs',
        'mapall',
        'maproot',
           ]
    );
}

/**
 * @param $options
 *
 * @return bool
 */
function nfs_getreadonly( $options )
{
    return ( @isset($options[ 'ro' ]) );
}

/**
 * @param      $fs
 * @param bool $on
 *
 * @return string[]
 */
function nfs_setreadonly( $fs, $on = true )
{
    if ($on ) {
        return nfs_setsharenfs($fs, ['ro' => []]);
    }

    return nfs_setsharenfs($fs, [], ['ro']);
}

/**
 * @param $fs
 *
 * @return string[]
 */
function nfs_resetpermissions( $fs )
{
    // required library
    activate_library('zfs');

    // get zfs mountpoint for given filesystem
    $zfsprop = zfs_filesystem_properties($fs, 'mountpoint,mounted');
    if (@$zfsprop[ $fs ][ 'mounted' ][ 'value' ] !== 'yes' ) {
        error('cannot reset permissions, filesystem "' . htmlentities($fs) . '" is not mounted!');
    }
    $mp = @$zfsprop[ $fs ][ 'mountpoint' ][ 'value' ];
    if (!is_dir($mp) ) {
        error('cannot reset permissions: "' . $mp . '" not a directory');
    }

    // permissions
    $uid = 1000;
    $gid = 1000;
    $dirperms = '0775';
    $fileperms = '0664';

    // return array with commands to reset permissions of all files and dirs
    return [
    '/usr/bin/chown -R ' . $uid . ':' . $gid . ' ' . $mp,
    '/usr/bin/find ' . $mp . ' -type d -print0'
    . ' | /usr/local/bin/sudo /usr/bin/xargs -0 /bin/chmod ' . $dirperms,
    '/usr/bin/find ' . $mp . ' -type f -print0'
    . ' | /usr/local/bin/sudo /usr/bin/xargs -0 /bin/chmod ' . $fileperms,
    ];
}

/**
 * @param       $fs
 * @param false $explicit
 *
 * @return string[]
 */
function nfs_removeshare( $fs, $explicit = false )
{
    $commandprefix = ( $explicit ) ? '/sbin/zfs set sharenfs="off" ' :
    '/sbin/zfs inherit sharenfs ';
    return [$commandprefix . $fs];
}

/* helper functions */

/**
 * @param       $fs
 * @param       $options
 * @param array $unset_options
 *
 * @return string[]
 */
function nfs_setsharenfs( $fs, $options, $unset_options = [])
{
    // disable sharing if $options is not an array
    if (!is_array($options) ) {
        return ['/sbin/zfs set sharenfs="off" ' . $fs];
    }
    // merge existing options with new ones
    $oldshare = nfs_sharenfs_list($fs);
    $sharenfs = [];
    if (@is_array($oldshare[ $fs ][ 'options' ]) ) {
        foreach ( $oldshare[ $fs ][ 'options' ] as $oldoption => $oldoptionvalue ) {
            if (!@isset($options[ $oldoption ]) ) {
                $options[ $oldoption ] = $oldoptionvalue;
            }
        }
    }
    // unset options
    foreach ( $unset_options as $opt ) {
        if (@isset($options[ $opt ]) ) {
            unset($options[ $opt ]);
        }
    }
    // create string from options
    $sharenfs = [];
    foreach ( $options as $option => $optiondata ) {
        if (in_array($option, ['alldirs', 'public', 'quiet', 'ro', 'webnfs']) ) {
            $sharenfs[] = "-$option";
        } elseif (count($optiondata) > 1 ) {
            foreach ( $optiondata as $optiondataone ) {
                $sharenfs[] = "-$option=$optiondataone";
            } 
        } elseif (@strlen($optiondata[ 0 ]) > 0 ) {
                $sharenfs[] = "-$option=$optiondata[0]";
        }
    }
        // return zfs set command
        return ['/sbin/zfs set sharenfs="' . implode(' ', $sharenfs) . '" ' . $fs];
}
