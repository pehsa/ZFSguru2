<?php

/*
 ** ZFSguru Web-interface - zfs.php
 ** ZFS related function library
 */


/* query functions */

function zfs_version()
{
    $zpl = common_sysctl('vfs.zfs.version.zpl');
    $spa = common_sysctl('vfs.zfs.version.spa');
    return array( 'zpl' => $zpl, 'spa' => $spa );
}

function zfs_featureflags() 
{
    if (common_sysctl('vfs.zfs.version.spa') < 5000 ) {
        return array();
    }
    $upgradetxt = shell_exec("/sbin/zpool upgrade -v");
    preg_match(
        '/FEAT DESCRIPTION\n-+[\s]*(.*)\n\n'
        . 'The following legacy versions are also supported/sm', $upgradetxt, $match 
    );
    if (@strlen($match[ 1 ]) < 1 ) {
        return array();
    }
    preg_match_all(
        '/^(\w+)[\s]*(\((.*)\))?[\s]*\n[\s]*(.*\.?)$/m',
        $match[ 1 ], $matches 
    );
    $featureflags = array();
    if (@isset($matches[ 4 ]) ) {
        foreach ( $matches[ 1 ] as $id => $featureflag ) {
            $featureflags[ $featureflag ] = array(
                'name' => $featureflag,
                'desc' => $matches[ 4 ][ $id ],
            );
        }
    }
    return $featureflags;
}

function zfs_filesystem_versions() 
{
    return array(
    1 => 'Initial ZFS filesystem version',
    2 => 'Enhanced directory entries',
    3 => 'Case insensitive and File system unique identifier (FUID)',
    4 => 'userquota, groupquota properties',
    5 => 'System attributes'
    );
}

function zfs_pool_versions() 
{
    return array(
    1 => 'Initial ZFS version',
    2 => 'Ditto blocks (replicated metadata)',
    3 => 'Hot spares and double parity RAID-Z',
    4 => 'zpool history',
    5 => 'Compression using the gzip algorithm',
    6 => 'bootfs pool property',
    7 => 'Separate intent log devices',
    8 => 'Delegated administration',
    9 => 'refquota and refreservation properties',
    10 => 'Cache devices',
    11 => 'Improved scrub performance',
    12 => 'Snapshot properties',
    13 => 'snapused property',
    14 => 'passthrough-x aclinherit',
    15 => 'user/group space accounting',
    16 => 'stmf property support',
    17 => 'Triple-parity RAID-Z',
    18 => 'Snapshot user holds',
    19 => 'Log device removal',
    20 => 'Compression using zle (zero-length encoding)',
    21 => 'Deduplication',
    22 => 'Received properties',
    23 => 'Slim ZIL',
    24 => 'System attributes',
    25 => 'Improved scrub stats',
    26 => 'Improved snapshot deletion performance',
    27 => 'Improved snapshot creation performance',
    28 => 'Multiple vdev replacements',
    5000 => 'ZFS feature flags',
    );
}

function zfs_pool_versions_oracle()
{
    return array(
    29 => 'RAID-Z/mirror hybrid allocator',
    30 => 'Encryption',
    31 => 'Improved \'zfs list\' performance',
    // ^^ = Solaris 11 Express b151a Nov 2010
    32 => 'One MB blocksize',
    33 => 'Improved share support',
    // ^^ = Solaris 11 EA b173 Sep 2011
    );
}

function zfs_pool_reservednames() 
{
    return array(
    'mirror', 'raidz', 'raidz2', 'raidz3', 'cache', 'log', 'spare', 'raidz2-1',
    'bin', 'boot', 'cdrom', 'dev', 'entropy', 'etc', 'home', 'lib', 'libexec',
    'media', 'mnt', 'proc', 'rescue', 'root', 'sbin', 'services',
    'sys', 'system', 'tmp', 'tmpfs', 'usr', 'var', 'zfsguru' );
}

function zfs_pool_list( $poolname = false )
{
    $zpools = array();
    if ($poolname == false ) {
        exec('/sbin/zpool list', $zpools_raw);
    } else {
        exec('/sbin/zpool list ' . $poolname, $zpools_raw);
    }
    $zpool_count = count($zpools_raw) - 1;
    for ( $i = 1; $i <= $zpool_count; $i++ ) {
        $chunks = preg_split('/\s/m', $zpools_raw[ $i ], -1, PREG_SPLIT_NO_EMPTY);
        $zpool_name = $chunks[ 0 ];
        $zpools[ $zpool_name ][ 'size' ] = $chunks[ 1 ];
        $zpools[ $zpool_name ][ 'used' ] = $chunks[ 2 ];
        $zpools[ $zpool_name ][ 'free' ] = $chunks[ 3 ];
        // most modern ZFS implementation has 11 columns
        if (count($chunks) == 11 ) {
            $zpools[ $zpool_name ][ 'ckpoint' ] = $chunks[ 4 ];
            $zpools[ $zpool_name ][ 'expandsz' ] = $chunks[ 5 ];
            $zpools[ $zpool_name ][ 'frag' ] = $chunks[ 6 ];
            $zpools[ $zpool_name ][ 'cap' ] = $chunks[ 7 ];
            $zpools[ $zpool_name ][ 'dedup' ] = $chunks[ 8 ];
            $zpools[ $zpool_name ][ 'status' ] = $chunks[ 9 ];
            $zpools[ $zpool_name ][ 'altroot' ] = $chunks[ 10 ];
        }
        // modern ZFS implementation has 10 columns
        elseif (count($chunks) == 10 ) {
            $zpools[ $zpool_name ][ 'expandsz' ] = $chunks[ 4 ];
            $zpools[ $zpool_name ][ 'frag' ] = $chunks[ 5 ];
            $zpools[ $zpool_name ][ 'cap' ] = $chunks[ 6 ];
            $zpools[ $zpool_name ][ 'dedup' ] = $chunks[ 7 ];
            $zpools[ $zpool_name ][ 'status' ] = $chunks[ 8 ];
            $zpools[ $zpool_name ][ 'altroot' ] = $chunks[ 9 ];
        }
        // older FreeBSD < August 2014 does not have FRAG and EXPANDSZ columns
        elseif (count($chunks) == 8 ) {
            $zpools[ $zpool_name ][ 'cap' ] = $chunks[ 4 ];
            $zpools[ $zpool_name ][ 'dedup' ] = $chunks[ 5 ];
            $zpools[ $zpool_name ][ 'status' ] = $chunks[ 6 ];
        }
        // oldest ZFS implementation (<=v20) also do not have DEDUP column
        elseif (count($chunks) == 7 ) {
            $zpools[ $zpool_name ][ 'cap' ] = $chunks[ 4 ];
            $zpools[ $zpool_name ][ 'status' ] = $chunks[ 5 ];
        }
        else {
            error(
                'detecting ZFS pools failed; unexpected output from zpool list! '
                . 'Update ZFSguru web-interface or downgrade system version!' 
            );
        }
    }
    if (( $poolname != false )AND( count($zpools) == 1 ) ) {
        return current($zpools);
    }

    return $zpools;
}

function zfs_pool_status( $poolname, $parameters = '' )
{
    // execute zpool status command (does not need root)
    if ($parameters != '') {
        activate_library('super');
        $result = super_execute('/sbin/zpool status ' . $parameters . ' ' . $poolname);
        $zpool_status = $result[ 'output_str' ];
    } else {
        $zpool_status = shell_exec("/sbin/zpool status \$poolname");
    }

    // pool data
    preg_match('/^[\s]*state: (.*)$/m', $zpool_status, $state);
    preg_match('/^[\s]*status: (.*)^action: /sm', $zpool_status, $status);
    preg_match('/^[\s]*action: (.*)$/m', $zpool_status, $action);
    preg_match('/^[\s]*see: (.*)$/m', $zpool_status, $see);
    preg_match('/^[\s]*scrub: (.*)$/m', $zpool_status, $scrub);
    preg_match('/^[\s]*config: (.*)$/m', $zpool_status, $config);

    // split data
    $split_regexp = '/^[\s]+NAME[\s]+STATE[\s]+READ[\s]+WRITE[\s]+CKSUM[\s]*$/m';
    $split = preg_split($split_regexp, $zpool_status);
    if (strpos(@$split[ 1 ], 'errors: ') !== false ) {
        $memberchunk = substr(@$split[ 1 ], 0, strpos(@$split[ 1 ], 'errors: '));
    } else {
        $memberchunk = @$split[ 1 ];
    }
    $errors = substr(@$split[ 1 ], strpos(@$split[ 1 ], 'errors: '));

    // retrieve pool details
    $details = array();
    $dsplit = preg_split(
        '/^[\s]*([a-zA-Z]+):/m', @$split[ 0 ], null,
        PREG_SPLIT_DELIM_CAPTURE 
    );
    for ( $i = 1; $i < 99; $i++ ) {
        if (@isset($dsplit[ $i ]) ) {
            $details[ trim($dsplit[ $i ]) ] = trim($dsplit[ ++$i ]);
        } else {
            break;
        }
    }
    // rename scan to scrub for compatibility with ZFS v15 data format
    if (@isset($details[ 'scan' ]) ) {
        $details[ 'scrub' ] = $details[ 'scan' ];
        unset($details[ 'scan' ]);
    }

    // retrieve pool members
    $poolmembers = array();
    if (@strlen($memberchunk) > 0 ) {
        $status_arr = explode(chr(10), $memberchunk);
        $regexp_string = '/^[\s]*([^\s]+)[\s]+([^\s]+)[\s]+'
        // this is quite a beast, it probably is not flawless, either.
        // old code, does not detect hot spares properly, keeping for future reference
        //   .'(([0-9]+[^\s]*)[\s]+([0-9]+[^\s]*)[\s]+([0-9]+[^\s]*)[\s]*(.*))?/';
        . '((([0-9]+[^\s]*)[\s]+([0-9]+[^\s]*)[\s]+([0-9]+[^\s]*)[\s]*(.*))$'
        . '|([a-zA-Z\/]+[\s]+[a-zA-Z\/]+.*)?$)/';
        foreach ( $status_arr as $line ) {
            // calculate depth first
            preg_match('/^[\s]+/m', $line, $spaces);
            $depth = @strlen($spaces[ 0 ]);
            // continue when regexp matches
            if (preg_match($regexp_string, $line, $memberdata) ) {
                $poolmembers[] = @array( 'name' => $memberdata[ 1 ],
                'state' => $memberdata[ 2 ], 'read' => $memberdata[ 5 ],
                'write' => $memberdata[ 6 ], 'cksum' => $memberdata[ 7 ],
                'extra' => $memberdata[ 8 ] . $memberdata[ 9 ], 'depth' => $depth );
            } elseif (strpos(trim($line), 'cache') === 0) {
                $poolmembers[] = array( 'name' => 'cache', 'depth' => $depth );
            } elseif (strpos(trim($line), 'log') === 0) {
                $poolmembers[] = array( 'name' => 'log', 'depth' => $depth );
            } elseif (strpos(trim($line), 'spares') === 0) {
                $poolmembers[] = array( 'name' => 'hot spares', 'depth' => $depth );
            }
        }
    }

    // construct list of (potential) corrupted files on pool
    $corrupted = array();
    preg_match_all('/^[\s]*(\/.*)[\s]*$/m', $errors, $matches);
    foreach ($matches[1] as $iValue) {
        if (@strlen($iValue) > 0 ) {
            $corrupted[] = trim($iValue);
        }
    }

    // assemble and return data
    $pool_info = $details;
    $pool_info[ 'errors' ] = $corrupted;
    $pool_info[ 'members' ] = $poolmembers;
    return $pool_info;
}

function zfs_pool_status_all()
{
    global $guru;
    // check for cached version
    if (@is_array($guru[ 'cache' ][ 'zfs_pool_status_all' ]) ) {
        return $guru[ 'cache' ][ 'zfs_pool_status_all' ];
    }
    // no cache available; create status_all array
    $status_all = array();
    // list of pool
    $poollist = zfs_pool_list();
    if (@is_array($poollist) ) {
        foreach ( $poollist as $poolname => $pooldata ) {
            $status_all[ $poolname ] = zfs_pool_status($poolname);
        }
    }
    // save cache and return array
    $guru[ 'cache' ][ 'zfs_pool_status_all' ] = $status_all;
    return $status_all;
}

function zfs_pool_version( $poolname )
{
    $prop = zfs_pool_properties($poolname, 'version');
    return ( @$prop[ $poolname ][ 'version' ][ 'value' ] == '-' ) ? 5000 :
    @$prop[ $poolname ][ 'version' ][ 'value' ];
}

function zfs_pool_features( $poolname )
{
    $featureflags = zfs_featureflags();
    $poolprop = zfs_pool_properties($poolname, false);
    if (!@is_array($poolprop[ $poolname ]) ) {
        return array();
    }
    foreach ( $poolprop[ $poolname ] as $property ) {
        if (strpos($property['property'], 'feature@') === 0) {
            $feature = substr($property[ 'property' ], strlen('feature@'));
            $features[ $poolname ][ $feature ] = array(
            'name' => $feature,
            'status' => @$property[ 'value' ],
            'source' => @$property[ 'source' ],
            'desc' => @$featureflags[ $feature ][ 'desc' ],
            );
        }
    }
    return $features;
}

function zfs_pool_history( $poolname )
{
    // elevated privileges
    activate_library('super');

    // start history array
    $history = array();

    // execute zpool history command
    $result = super_execute('/sbin/zpool history ' . $poolname);
    if (count(@$result[ 'output_arr' ]) > 1 ) {
        preg_match_all(
            '/^([0-9\-]*)\.([0-9\-:]*)[\s]*(.*)$/m',
            @$result[ 'output_str' ], $matches 
        );
        if (@count($matches[ 3 ]) > 0 ) {
            foreach ( $matches[ 1 ] as $id => $date ) {
                $history[] = @array(
                'date' => $matches[ 1 ][ $id ],
                'time' => $matches[ 2 ][ $id ],
                'event' => $matches[ 3 ][ $id ]
                );
            }
        }
    }
    return $history;
}

function zfs_pool_getbootfs( $poolname )
{
    // requires root privileges ?
    activate_library('super');
    // fetch bootfs property
    $result = super_execute('/sbin/zpool get bootfs ' . $poolname);
    if (@strlen($result[ 'output_arr' ][ 1 ]) > 0 ) {
        $preg_string = '/^\S+[\s]+\S+[\s]+(\S+)[\s]+\S+$/m';
        preg_match($preg_string, $result[ 'output_arr' ][ 1 ], $matches);
        if (@strlen($matches[ 1 ]) > 0 ) {
            return $matches[ 1 ];
        }
    }
    return false;
}

function zfs_pool_isbeingscrubbed( $pool )
{
    $status_output = array();
    $status_str = '';
    exec('/sbin/zpool status ' . $pool, $status_output);
    foreach ( $status_output as $line ) {
        $status_str .= $line;
    }

    return !(strpos($status_str, 'scrub in progress') === false);
}

function zfs_pool_ismember( $disk, $strict_comparison = true )
{
    // get (cached) pool status of all pools
    $status_all = zfs_pool_status_all();
    foreach ( $status_all as $poolname => $poolstatus ) {
        foreach ( $poolstatus[ 'members' ] as $data ) {
            if ($data[ 'name' ] === $disk) {
                return $poolname;
            }

            if (!$strict_comparison && strpos($data['name'], $disk) === 0) {
                return $poolname;
            }
        }
    }
    return false;

    /*if (is_array($poolstatus[ 'members' ]) ) {
        foreach ( $poolstatus[ 'members' ] as $data ) {
            if ($data[ 'name' ] == $disk) {
                return true;
            }

            if (!$strict_comparison) {
                if (substr($data[ 'name' ], 0, strlen($disk)) == $disk ) {
                    return true;
                }
            }
        }
    }
    return false;*/
}

function zfs_pool_memberdetails( $poolstatus, $poolname )
{
    // start memberdisks array
    $memberdisks = array();

    // initial settings
    $vdevtype = 'pool';
    $vdevtypes = array( 'mirror', 'raidz', 'cache', 'log', 'spare', 'hot spares' );
    $lastdepth = -1;
    // process pool members
    if (is_array($poolstatus[ 'members' ]) ) {
        foreach ( $poolstatus[ 'members' ] as $member ) {
            $special = false;
            if ($member[ 'name' ] == $poolname ) {
                $vdevtype = 'pool';
                $special = true;
            } else {
                foreach ( $vdevtypes as $vtype ) {
                    if (strpos($member['name'], $vtype) === 0) {
                        $vdevtype = $vtype;
                        $special = true;
                    }
                }
            }
            if (!$special and $vdevtype === 'pool' ) {
                $vdevtype = 'stripe';
            }
            if (!@$special AND @$member[ 'depth' ] < $lastdepth ) {
                $memberdisks[] = array(
                'name' => $member[ 'name' ],
                'type' => $vdevtype,
                'special' => $special
                );
                $vdevtype = 'stripe';
            }
            $lastdepth = @$member[ 'depth' ];
            // add row
            $memberdisks[] = array(
            'name' => $member[ 'name' ],
            'type' => $vdevtype,
            'special' => $special
            );
        }
    }

    // return result
    return $memberdisks;
}

function zfs_pool_ashift( $poolname )
{
    activate_library('super');
    $result = super_execute(
        '/usr/sbin/zdb -eC ' . escapeshellarg($poolname)
        . ' | grep ashift' 
    );
    if (preg_match(
        '/^[\s]*ashift=(\d+)/m', $result[ 'output_str' ],
        $matches
    )) {
        return $matches[ 1 ];
    }

    if (preg_match(
        '/^[\s]*ashift:[\s]*(\d+)/m', $result[ 'output_str' ],
        $matches
    )
    ) {
        return $matches[ 1 ];
    }

    return false;
}

function zfs_pool_isreservedname( $poolname )
{
    $reservednames = zfs_pool_reservednames();
    return ( in_array(strtolower($poolname), $reservednames) );
}

function zfs_pool_properties( $poolname, $property = false )
{
    if (!$property ) {
        $property = 'all';
    }
    $command = "/sbin/zpool get $property $poolname";
    exec($command, $output, $rv);
    if ($rv != 0 ) {
        return false;
    }
    $prop = array();
    if (@is_array($output) ) {
        for ($i = 1, $iMax = count($output); $i < $iMax; $i++ ) {
            $split = preg_split('/[\s]+/m', $output[ $i ]);
            $name = trim($split[ 0 ]);
            $property = trim($split[ 1 ]);
            $prop[ $name ][ $property ][ 'name' ] = trim($split[ 0 ]);
            $prop[ $name ][ $property ][ 'property' ] = $property;
            $prop[ $name ][ $property ][ 'value' ] = trim($split[ 2 ]);
            $prop[ $name ][ $property ][ 'source' ] = trim($split[ 3 ]);
        }
    }
    return $prop;
}

// filesystem functions

function zfs_filesystem_list( $fs = '', $arguments = '' )
{
    // generate data
    $fsarr = array();
    $command = '/sbin/zfs list ' . $arguments . ' ' . $fs;
    exec($command, $result, $rv);
    if (( @count($result) > 1 )AND( $rv == 0 ) ) {
        // extract data from output
        // note that with $i starting at index 1 (not 0) we skip first line
        for ( $i = 1; $i <= count($result) - 1; $i++ ) {
            $split = preg_split('/[\s]+/m', @$result[ $i ], 5);
            $newarr = @array(
            'name' => $split[ 0 ],
            'used' => $split[ 1 ],
            'avail' => $split[ 2 ],
            'refer' => $split[ 3 ],
            'mountpoint' => $split[ 4 ]
            );
            $fsarr[ $newarr[ 'name' ] ] = $newarr;
        }
        return $fsarr;
    }

    return false;
}

function zfs_filesystem_list_one( $fs = '', $arguments = '' )
{
    $fsarr = zfs_filesystem_list($fs, $arguments);
    return current($fsarr);
}

function zfs_filesystem_properties( $fs, $property = false, $fstype = false,
    $source = false 
) {
    // get properties of ZFS filesystem(s) and return array
    if ($property == false ) {
        $property = 'all';
    }
    if ($fstype != false ) {
        $fstype = '-t ' . $fstype;
    }
    if ($source != false ) {
        $source = '-s ' . $source;
    }
    $command = "/sbin/zfs get -rH $fstype $source $property $fs";
    exec($command, $output, $rv);
    if ($rv != 0 ) {
        return false;
    }
    $prop = array();
    if (@is_array($output) ) {
        foreach ($output as $iValue) {
            $split = preg_split('/[\t]+/m', $iValue);
            $name = trim($split[ 0 ]);
            $property = trim($split[ 1 ]);
            $prop[ $name ][ $property ][ 'name' ] = trim($split[ 0 ]);
            $prop[ $name ][ $property ][ 'property' ] = $property;
            $prop[ $name ][ $property ][ 'value' ] = trim($split[ 2 ]);
            $prop[ $name ][ $property ][ 'source' ] = $source;
        }
    }
    return $prop;
}

function zfs_filesystem_volumes()
{
    $zvols = array();
    exec('/sbin/zfs list -t volume', $output, $rv);
    if ($rv != 0 ) {
        return $rv;
    }
    for ($i = 1, $iMax = count($output); $i < $iMax; $i++ ) {
        $split = preg_split('/[\s]/m', $output[ $i ], null, PREG_SPLIT_NO_EMPTY);
        $zvolname = @$split[ 0 ];
        if (@strlen($zvolname) > 0 ) {
            // requires disk library
            activate_library('disk');
            $diskinfo = disk_info('/dev/zvol/' . $zvolname);
            $zvols[ $zvolname ] = @array( 'zvol' => $split[ 0 ], 'used' => $split[ 1 ],
            'avail' => $split[ 2 ], 'refer' => $split[ 3 ], 'mountpoint' => $split[ 4 ],
            'diskinfo' => $diskinfo );
        }
    }
    return $zvols;
}

function zfs_filesystem_issystemfs( $fsname )
{
    // determine whether $fsname is system filesystem
    $fsbase = @substr($fsname, strpos($fsname, '/') + 1);
    if ($basepos = strpos($fsbase, '/') ) {
        $fsbase = @substr($fsbase, 0, $basepos);
    }

    return ($fsbase === 'zfsguru') or (strpos($fsbase, 'zfsguru-system') === 0) or ($fsbase === 'SWAP001');
}


/* active functions that change or influence something */

function zfs_pool_setbootfs( $poolname, $bootfs = false, $redirect_url = false )
{
    $zpools = zfs_detect_zpools();
    if (@!isset($zpools[ $poolname ]) ) {
        error('Invalid poolname "' . $poolname . '"; pool does not exist.');
    }
    if ($bootfs == false ) {
        $bootfs = '';
    }
    if ($redirect_url === false ) {
        $redirect_url = 'pools.php?boot=' . urlencode($poolname);
    }
    dangerous_command(
        '/sbin/zpool set bootfs=' . $bootfs . ' ' . $poolname,
        $redirect_url 
    );
    error('HARD ERROR: unhandled exit zfs_pool_setbootfs');
}

function zfs_pool_import_list( $deleted = false )
{
    // requires root privileges
    activate_library('super');

    // device search paths
    $searchpaths = array(
    '-d /dev/gpt',
    '-d /dev/label',
    '-d /dev/gpt -d /dev/label',
    '-d /dev'
    );

    // search only for deleted pools if $deleted is true
    $opt_deleted = ( $deleted ) ? '-D' : '';

    // execute zpool import command for all existent searchpaths
    $results = array();
    foreach ( $searchpaths as $searchpath ) {
        $sp_split = explode('-d ', $searchpath);
        unset($sp_split[ 0 ]);
        foreach ( $sp_split as $sp_single ) {
            if (!@is_dir(trim($sp_single)) ) {
                continue 2;
            }
        }
        $results[ $searchpath ] = super_execute(
            '/sbin/zpool import '
            . $opt_deleted . ' ' . $searchpath 
        );
    }

    // dig through output to construct importables array
    $importables = array();
    foreach ( $results as $searchpath => $result ) {
        if ($result[ 'rv' ] == 0 ) {
            $split = preg_split('/^[\s]*pool: /m', $result[ 'output_str' ]);
            if (is_array($split) ) {
                foreach ( $split as $splitid => $poolchunk ) {
                    if (@( int )$splitid > 0 ) {
                        // preg_match('/^[\s]*pool\: (.*)$/m', $poolchunk, $preg_pool);
                        preg_match('/^[\s]*id: (\d*)$/m', $poolchunk, $preg_id);
                        preg_match('/^[\s]*state: (.*)$/m', $poolchunk, $preg_state);
                        // $pool = (@$preg_pool[1]) ? $preg_pool[1] : false;
                        $pool = trim(substr($poolchunk, 0, strpos($poolchunk, chr(10))));
                        $id = ( @$preg_id[ 1 ] ) ? $preg_id[ 1 ] : false;
                        $status = ( @$preg_state[ 1 ] ) ? $preg_state[ 1 ] : 'UNKNOWN';
                        $canbeimported = ( $status === 'ONLINE'
                        OR $status === 'ONLINE (DESTROYED)' );
                        preg_match('/^[\s]*/', $result[ 'output_str' ], $whitespace);
                        $rawoutput = @$whitespace[ 0 ] . 'pool: ' . $poolchunk;
                        if (($pool !== '')AND($id != '') ) {
                            if (( !isset($importables[ $id ]) )OR( @$importables[ $id ][ 'status' ] !== 'ONLINE' ) ) {
                                $importables[ $id ] = array(
                                'pool' => $pool,
                                'id' => $id,
                                'status' => $status,
                                'canimport' => $canbeimported,
                                'searchpath' => $searchpath,
                                'rawoutput' => rtrim($rawoutput)
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    // return importables array
    return $importables;
}

function zfs_pool_import( $poolid, $import_deleted = false )
{
    // super privileges
    activate_library('super');

    // query importables for correct search path
    $importables = zfs_pool_import_list($import_deleted);
    if (!@isset($importables[ $poolid ]) ) {
        return false;
    }
    $searchpath = $importables[ $poolid ][ 'searchpath' ];

    // import actual pool
    if ($import_deleted ) {
        $command = '/sbin/zpool import ' . $searchpath . ' -D -f ' . $poolid;
    } else {
        $command = '/sbin/zpool import ' . $searchpath . ' -f ' . $poolid;
    }

    // TODO: shouldn't we invoke dangerouscommand function for this?
    $result = super_execute($command);

    return $result['rv'] == 0;
}

function zfs_pool_scrub( $poolname, $stop_scrub = false )
{
    // super privileges
    activate_library('super');
    if ($stop_scrub ) {
        $command = '/sbin/zpool scrub -s ' . $poolname;
    } else {
        $command = '/sbin/zpool scrub ' . $poolname;
    }
    $result = super_execute($command);
    return ( $result[ 'rv' ] == 0 );
}


/* ZFS-related POST extract */

function zfs_extractsubmittedvdevs( $url )
{
    $member_disks = array();
    foreach ( $_POST as $id => $val ) {
        if (($val === 'on') && preg_match('/^addmember_(.*)$/', $id, $addmember) && @strlen($addmember[1]) > 0) {
            $member_disks[] = $addmember[ 1 ];
        }
    }
    if (empty($member_disks) ) {
        friendlyerror(
            'looks like you forgot to select member disks, please correct!',
            $url 
        );
    }
    $member_str = '';
    // TODO - SECURITY
    foreach ( $member_disks as $disklabel ) {
        $member_str .= $disklabel . ' ';
    }
    $member_arr = array();
    $member_arr[ 'member_str' ] = trim($member_str);
    $member_arr[ 'member_disks' ] = $member_disks;
    $member_arr[ 'member_count' ] = @count($member_disks);
    return $member_arr;
}

function zfs_extractsubmittedredundancy( $redundancy, $member_count, $url )
{
    if (( int )$member_count < 1 ) {
        friendlyerror('please select one or more disks', $url);
    }

    switch ( $redundancy ) {
    case "stripe":
        $red = '';
        break;
    case "mirror":
        if (( int )$member_count < 2 ) {
            friendlyerror(
                'you have chosen RAID1 (mirroring) but have selected less '
                . 'than two disks. Please select at least two disks. Note that you can '
                . 'transform a single disk into a mirror later, and vice versa.', $url 
            );
        }
        $red = 'mirror';
        break;
    case "mirror2":
        if (( int )$member_count % 2 != 0 ) {
            friendlyerror(
                'you chose 2-way mirroring so please select '
                . 'a multiple of 2 disks', $url 
            );
        }
        $red = 'mirror2';
        break;
    case "mirror3":
        if (( int )$member_count % 3 != 0 ) {
            friendlyerror(
                'you chose 3-way mirroring so please select '
                . 'a multiple of 3 disks', $url 
            );
        }
        $red = 'mirror3';
        break;
    case "mirror4":
        if (( int )$member_count % 4 != 0 ) {
            friendlyerror(
                'you chose 4-way mirroring so please select '
                . 'a multiple of 4 disks', $url 
            );
        }
        $red = 'mirror4';
        break;
    case "raidz1":
        if ($member_count < 2 ) {
            friendlyerror(
                'you have chosen RAID-Z1 (single parity) but have selected '
                . 'less than two disks. Please select at least 2 disks.', $url 
            );
        }
        $red = 'raidz';
        break;
    case "raidz2":
        if ($member_count < 3 ) {
            friendlyerror(
                'you have chosen RAID-Z2 (double parity) but have selected '
                . 'less than three disks. Please select at least 3 disks.', $url 
            );
        }
        $red = 'raidz2';
        break;
    case "raidz3":
        if ($member_count < 4 ) {
            friendlyerror(
                'you have chosen RAID-Z3 (triple parity) but have selected '
                . 'less than four disks. Please select at least 4 disks.', $url 
            );
        }
        $red = 'raidz3';
        break;
    default:
        error(
            'internal error: unable to format redundancy string '
            . '(extractsubmittedredundancy;' . htmlentities($redundancy) . ';'
            . htmlentities($member_count) . ')' 
        );
    }
    return $red;
}
