<?php

/* ZFSguru-specific functionality */

function zfsguru_mountlivecd()
{
    global $guru;

    // locations
    $mountpoint = '/media/' . basename($guru[ 'dev_livecd' ]);
    $systemimage = $mountpoint . '/system.ufs.uzip';

    // check whether already mounted
    if (file_exists($systemimage) ) {
        return $systemimage;
    }

    // try to mount LiveCD, return pathname if successful, false otherwise
    if (!file_exists($guru[ 'dev_livecd' ]) ) {
        return false;
    }

    // commands require elevated privileges
    activate_library('super');
    $commands = array(
    'UMOUNT' => '/sbin/umount ' . escapeshellarg($mountpoint),
    'MKDIR' => '/bin/mkdir -p ' . escapeshellarg($mountpoint),
    'MOUNT' => '/sbin/mount -r -t cd9660 '
    . escapeshellarg($guru[ 'dev_livecd' ]) . ' ' . escapeshellarg($mountpoint),
    );
    foreach ( $commands as $command ) {
        super_execute($command);
    }

    // return string with pathname to system image (ufs.uzip) - or boolean false
    clearstatcache();
    if (@file_exists($systemimage) ) {
        return $systemimage;
    }
    return false;
}

function zfsguru_mountusb()
{
    // requires elevated privileges
    activate_library('super');

    // search for available GPT devices
    $gptdevices = array();
    $ls_gpt = shell_exec('/bin/ls -1 /dev/gpt/');
    $gptnames = explode(chr(10), $ls_gpt);

    // try mounting all gpt devices
    $systemimages = array();
    if (is_array($gptnames) ) {
        foreach ( $gptnames as $gptname ) {
            // sanity
            if (strlen(trim($gptname)) < 1 ) {
                continue;
            }

            // variables
            $devnode = '/dev/gpt/' . trim($gptname);
            $mountpoint = '/media/' . trim($gptname);
            $systemimage = $mountpoint . '/system.ufs.uzip';

            // mount GPT device
            $commands = array(
            'SYNC' => '/bin/sync',
            'UMOUNT' => '/sbin/umount ' . escapeshellarg($mountpoint),
            'MKDIR' => '/bin/mkdir -p ' . escapeshellarg($mountpoint),
            'MOUNT' => '/sbin/mount -r -t ufs ' . escapeshellarg($devnode)
            . ' ' . escapeshellarg($mountpoint),
            );
            foreach ( $commands as $command ) {
                super_execute($command);
            }
            clearstatcache();
            if (@file_exists($systemimage) ) {
                $systemimages[] = $systemimage;
            }
        }
    }

    // return array of available system images from mounted USB sticks
    return $systemimages;
}

function zfsguru_unmountmedia()
{
    global $guru;

    // requires elevated privileges
    activate_library('super');

    // determine current mountpoints
    $mountpoints = shell_exec('/sbin/mount');
    $preg = '/^(.+) on (' . preg_quote('/dist', '/') . ')?('
    . preg_quote('/media/', '/') . ')(.+) \((.+)\)$/m';
    preg_match_all($preg, $mountpoints, $matches);
    clearstatcache();

    // unmount all found mountpoints beginning with /media/
    if (@is_array($matches[ 4 ]) ) {
        foreach ( $matches[ 4 ] as $id => $match ) {
            $mp = @$matches[ 3 ][ $id ] . $matches[ 4 ][ $id ];
            if (!is_dir($mp) ) {
                continue;
            }
            super_execute('/sbin/umount ' . escapeshellarg($mp));
        }
    }
}

function zfsguru_init_dirs()
{
    // requires elevated privileges
    activate_library('super');

    // retrieve distribution type
    $dist = common_distribution_type();

    // treat anything except rootonzfs as legacy behavior with fixed /services dir
    if ($dist != 'RoZ' ) {
        // assume tmpfs filesystem or unknown -- default to just creating directories
        clearstatcache();
        if (!is_dir('/tmp') ) {
            super_execute('/bin/mkdir -p /tmp');
            super_execute('/bin/chmod 1777 /tmp');
        }
        if (!is_dir('/download') ) {
            super_execute('/bin/mkdir -p /download');
            super_execute('/usr/sbin/chown root:888 /download');
            super_execute('/bin/chmod 0775 /download');
        }
        if (!is_dir('/services') ) {
            super_execute('/bin/mkdir -p /services');
        }
        // return array with pathnames
        return array(
        'services' => '/services',
        'download' => '/download',
        'temp' => '/tmp',
        );
    }

    // Root-on-ZFS distribution; create ZFS filesystems for download and services

    // required library
    activate_library('zfs');

    // gather data
    $fslist = zfs_filesystem_list();

    // search for zfsguru filesystems
    // note: only required for one sanity check
    $zfsguru_fs = array();
    if (is_array($fslist) ) {
        foreach ( $fslist as $fsname => $fsdata ) {
            if (preg_match('/^([^\/]+)\/zfsguru$/', $fsname, $matches) ) {
                $zfsguru_fs[] = @$matches[ 0 ];
            }
        }
    }

    // retrieve current boot pool and bootfs from mount output
    $mount = shell_exec('/sbin/mount');
    // <pool>/zfsguru/<bootfs> on / (zfs, local, noatime, nfsv4acls)
    preg_match(
        '/^([^\/]+)\/zfsguru\/([^\/]+) on \/ \(.*\)[\s]*$/m',
        $mount, $matches 
    );
    if (@strlen($matches[ 1 ]) > 0 ) {
        $bootpool = trim($matches[ 1 ]);
    } else {
        error('could not determine boot pool for Root-on-ZFS dist!');
    }
    if (@strlen($matches[ 2 ]) > 0 ) {
        $bootfs = trim($matches[ 2 ]);
    } else {
        error('could not determine boot filesystem for Root-on-ZFS dist!');
    }
    if (!in_array($bootpool . '/zfsguru', $zfsguru_fs) ) {
        error('boot filesystem not properly detected in ZFS list output!');
    }
    // set filesystems which should exist on bootpool
    $fs_services = $bootpool . '/zfsguru/services';
    $fs_services_sys = $bootpool . '/zfsguru/services/' . $bootfs;
    $fs_download = $bootpool . '/zfsguru/download';

    // fetch data again (CACHE warning!)
    $fslist = zfs_filesystem_list();

    // create filesystems if nonexistent
    $r = array();
    if (!@isset($fslist[ $fs_services ]) ) {
        $r[] = super_execute('/sbin/zfs create ' . $fs_services);
    }
    if (!@isset($fslist[ $fs_services_sys ]) ) {
        $r[] = super_execute('/sbin/zfs create ' . $fs_services_sys);
        super_execute('/sbin/zfs set compression=off ' . $fs_services_sys);
        super_execute('/sbin/zfs set dedup=off ' . $fs_services_sys);
    }
    if (!@isset($fslist[ $fs_download ]) ) {
        $r[] = super_execute('/sbin/zfs create ' . $fs_download);
        super_execute('/sbin/zfs set compression=off ' . $fs_download);
        super_execute('/sbin/zfs set dedup=off ' . $fs_download);
    }

    // check permissions
    clearstatcache();
    $perms = substr(decoct(fileperms('/' . $fs_download)), 2);
    if ($perms != 775 ) {
        $r[] = super_execute('/bin/chmod 775 /' . $fs_download);
    }
    // check user/group
    $owner = fileowner('/' . $fs_download);
    $group = filegroup('/' . $fs_download);
    if (( $owner != 0 )OR( $group != 888 ) ) {
        $r[] = super_execute('/usr/sbin/chown root:888 /' . $fs_download);
    }

    // check if /services is an old directory
    if (!is_link('/services')AND is_dir('/services') ) {
        // check whether it is an empty directory; remove in that case
        $scandir = scandir('/services');
        if (@count($scandir) <= 2 ) {
            $r[] = super_execute('/bin/rmdir /services');
        } else {
            // backup /services directory to different name: /services.backup.<$i>
            $backupname = '/services.backup';
            $backupdir = $backupname;
            $i = 1;
            if (file_exists($backupdir)OR is_link($backupdir) ) {
                while ( $i <= 999 ) {
                    $backupdir = $backupname . '.' . $i++;
                    if (!file_exists($backupdir)AND!is_link($backupdir) ) {
                        break;
                    }
                }
            }
            $r[] = super_execute('/bin/mv /services ' . $backupdir);
        }
        clearstatcache();
    }

    // check if /services is a symbolic link
    if (!is_link('/services') ) {
        $r[] = super_execute('/bin/ln -s /' . $fs_services_sys . ' /services');
    } else {
        // check whether link points to $fs_services_sys
        $linkloc = readlink('/services');
        if ($linkloc != '/' . $fs_services_sys ) {
            if ($linkloc != '/' . $fs_services_sys . '/' ) {
                // create symbolic link again
                $r[] = super_execute('/bin/rm -f /services');
                $r[] = super_execute('/bin/ln -s /' . $fs_services_sys . ' /services');
            }
        }
    }

    // check if /download is a symbolic link
    if (!is_link('/download') ) {
        super_execute('/bin/rmdir /download');
        $r[] = super_execute(
            '/bin/ln -s ' . escapeshellarg('/' . $fs_download)
            . ' /download' 
        );
    }

    // if we changed something, give feedback to the user
    if (!empty($r) ) {
        $errortxt = '';
        $error = false;
        foreach ( $r as $rvdata ) {
            if ($rvdata[ 'rv' ] != 0 ) {
                $error = true;
                $errortxt .= chr(10) . nl2br($rvdata[ 'output_str' ]);
            }
        }
        if ($error ) {
            page_feedback(
                'creation of ZFSguru specific filesystems has failed!',
                'a_error' 
            );
            page_feedback('error output: ' . $errortxt, 'c_notice');
        } else {
            page_feedback(
                'ZFSguru specific filesystems have been created on '
                . 'pool: <b>' . htmlentities($bootpool) . '</b>', 'c_notice' 
            );
        }
    }

    // return array with pathnames
    return array(
    'services' => '/' . $fs_services_sys,
    'download' => '/' . $fs_download,
    'temp' => '/tmp',
    );
}

function zfsguru_locatesystem()
{
    // required library
    activate_library('gurudb');

    // call functions
    $platform = common_systemplatform();
    $dirs = common_dirs();
    $system = gurudb_system();

    // mount LiveCD and USB media
    $livecd = zfsguru_mountlivecd();
    $usb = zfsguru_mountusb();

    // clear the PHP filestat cache to avoid misdetection of existing files
    clearstatcache();

    // populate $locate array with all known system versions and their location
    $locate = array();
    if (is_array($system) ) {
        foreach ( $system as $sysver => $platforms ) {
            if (!@isset($platforms[ $platform ]) ) {
                continue;
            }
            $sysdata = $platforms[ $platform ];

            // check download/LiveCD/USB
            $download = $dirs[ 'download' ] . '/' . $sysdata[ 'filename' ];
            $source = 'unknown';
            if (@file_exists($download)AND( @filesize($download) == $sysdata[ 'filesize' ] ) ) {
                $path = $download;
                $source = 'download';
            } elseif (@file_exists($livecd)AND( @filesize($livecd) == $sysdata[ 'filesize' ] ) ) {
                $path = $livecd;
                $source = 'livecd';
            }
            elseif (is_array($usb) ) {
                foreach ( $usb as $path ) {
                    if (@file_exists($path)AND( @filesize($path) == $sysdata[ 'filesize' ] ) ) {
                        $source = 'usb';
                        break;
                    }
                }
                if ($source != 'usb' ) {
                    continue;
                }
            }
            else {
                continue;
            }

            // add system image to locate array
            $size = @filesize($path);
            $avail = ( @file_exists($path)AND( $size == $sysdata[ 'filesize' ] ) );
            $locate[ 'checksum' ][ $sysdata[ 'sha512' ] ] = $sysver;
            $locate[ 'name' ][ $sysver ] = array(
            'avail' => $avail,
            'path' => $path,
            'source' => $source,
            'size' => $size,
            'sha512' => $sysdata[ 'sha512' ],
            );
        }
    }

    // look for unknown system version on LiveCD
    $sha512_livecd = @trim(file_get_contents($livecd . '.sha512'));
    if (strlen($sha512_livecd) == 128 ) {
        if (!in_array($locate[ 'checksum' ], $sha512) ) {
            if (@is_readable($livecd) ) {
                $name = 'LiveCD-unknown';
                $locate[ 'checksum' ][ $sha512_livecd ] = $name;
                $locate[ 'name' ][ $name ] = array(
                'avail' => true,
                'path' => $livecd,
                'source' => 'livecd',
                'size' => ( int )@filesize($livecd),
                'sha512' => $sha512_livecd,
                );
            }
        }
    }

    // look for unknown system versions on USB media
    foreach ( $usb as $number => $path ) {
        if (@is_readable($path) ) {
            $sha512_usb = @trim(file_get_contents($path . '.sha512'));
            if (strlen($sha512_livecd) == 128 ) {
                if (!in_array($sha512_usb, $sha512) ) {
                    $name = 'USB-unknown-' . ( int )$number;
                    $locate[ 'checksum' ][ $sha512_usb ] = $name;
                    $locate[ 'name' ][ $name ] = array(
                    'avail' => true,
                    'path' => $path,
                    'source' => 'usb',
                    'size' => ( int )@filesize($path),
                    'sha512' => $sha512_usb,
                    );
                }
            }
        }
    }

    // unmount LiveCD or USB media
    zfsguru_unmountmedia();

    // return locate array
    return $locate;

    /*
    ** TODO: scan for dynamic system image filenames on LiveCD/USB media
    **

    global $guru;
    // fetch directory locations
    $dirs = guru_locate_dirs();
    // search for locations specified in scanlocations array (need .md5 file)
    $scanlocations = array(
    $dirs['download'].'/',
    $guru['path_media_mp']
    );
    // construct array with new unknown system versions
    $newsysver = array();
    foreach ($scanlocations as $location)
    {
    unset($ls);
    exec('/usr/bin/find '.$location, $ls);
    if (@is_array($ls))
       foreach ($ls as $line)
        if (preg_match('/^(.+)\.ufs\.uzip$/', $line, $matches))
         if (@strlen($matches[1]) > 0)
          if (is_readable($matches[1].'.ufs.uzip.md5'))
          {
           $md5 = @trim(file_get_contents($matches[1].'.ufs.uzip.md5'));
           if (strlen($md5) < 1)
            continue;
           $isknown = false;
           foreach ($systemversions as $sysver)
            if ($sysver['md5hash'] == $md5)
             $isknown = true;
           if (!$isknown)
            $newsysver['U'.substr($md5, 0, 6)] = array(
             'name' => 'U'.substr($md5, 0, 6), 'bsdversion' => '???',
             'branch' => '???', 'platform' => '???', 'spa' => '???', 'notes' => '',
             'md5hash' => @trim(file_get_contents($matches[1].'.ufs.uzip.md5')),
             'sha1hash' => @trim(file_get_contents($matches[1].'.ufs.uzip.sha1')),
             'sha512' => @trim(file_get_contents($matches[1].'.ufs.uzip.sha512')),
             'filesize' => @filesize($matches[1].'.ufs.uzip')
            );
           unset($matches);
          }
    }
    return $newsysver;
    */
}

function zfsguru_system_sha512( $version, $source, $locate = false )
{
    // call functions
    if (!$locate ) {
        $locate = zfsguru_locatesystem();
    }
    $sysloc = @$locate[ 'name' ][ $version ][ 'path' ];

    // look for .sha512 file
    $sha512file = $sysloc . '.sha512';
    if (@file_exists($sha512file) ) {
        $sha512 = trim(file_get_contents($sha512file));
        if ($sha512 ) {
            return $sha512;
        }
    }

    // look in GuruDB
    activate_library('gurudb');
    $system = gurudb_system();
    $platform = common_systemplatform();
    $sha512 = @$system[ $version ][ $platform ][ 'sha512' ];
    if ($sha512 ) {
        return $sha512;
    }

    // calculate the checksum ourselves
    // NOTE: mount media (LiveCD/USB) to be able to access the checksum file
    if ($source == 'livecd' ) {
        zfsguru_mountlivecd();
    }
    if ($source == 'usb' ) {
        zfsguru_mountusb();
    }
    if (@function_exists('hash_file') ) {
        $sha512 = hash_file('sha512', $sysloc);
    } else {
        $sha512 = trim(shell_exec('/sbin/sha512 -q ' . escapeshellarg($sysloc)));
    }
    if (( $source == 'livecd' )OR( $source == 'usb' ) ) {
        zfsguru_unmountmedia();
    }
    if ($sha512 ) {
        return $sha512;
    } else {
        return false;
    }
}

function zfsguru_install( $postdata )
{
    global $guru;

    // required libraries
    activate_library('background');
    activate_library('gurudb');

    // variables
    $script_install = $guru[ 'docroot' ] . '/scripts/zfsguru_install.php';
    $version = $postdata[ 'version' ];
    $source = $postdata[ 'source' ];
    $target = $postdata[ 'target' ];
    $dist = $postdata[ 'dist' ];

    // redirect URLs
    $url_base = 'system.php?install';
    $url_backtostep3 = $url_base . '&version=' . $version . '&source=' . $source . '&target='
    . $target . '&dist=' . $dist;
    $url_installing = $url_base . '&progress';

    // sanity
    $required_postvars = array( 'version', 'source', 'target', 'dist' );
    foreach ( $required_postvars as $required_postvar ) {
        if (!@isset($postdata[ $required_postvar ]) ) {
            error('missing required POST variable: ' . htmlentities($required_postvar));
        }
    }
    if (disk_free_space($guru[ 'tempdir' ]) < 64 * 1024 ) {
        error(
            'not enough free space available in <b>' . $guru[ 'tempdir' ]
            . '</b> - you may need to reboot or increase RAM size!' 
        );
    }

    // TODO: move $sysloc to this function ?!

    // determine distribution and hand-off to library function
    if ($dist == 'RoZ' ) {
        $commands = zfsguru_install_roz($postdata, $version, $source, $target);
    } elseif ($dist == 'RoR' ) {
        $commands = zfsguru_install_ror($postdata, $version, $source, $target);
    } elseif ($dist == 'RoM' ) {
        $commands = zfsguru_install_rom($postdata, $version, $source, $target);
    } else {
        error('invalid distribution: "' . htmlentities($dist) . '"');
    }

    // register background job
    background_remove('ZFSguru-install');
    background_register(
        'ZFSguru-install', array(
        'commands' => $commands,
        'super' => true,
        'combinedoutput' => true,
        ) 
    );

    // redirect to progress page
    redirect_url($url_installing);
}

function zfsguru_install_roz( $postdata, $version, $source, $target )
{
    global $guru;

    // required library
    activate_library('zfs');

    // variables
    $poolname = substr($target, strlen('ZFS: '));
    $fs_zfsguru = $poolname . '/zfsguru';
    $fs_zfsguru_download = $fs_zfsguru . '/download';
    $fs_root = $fs_zfsguru . '/' . $postdata[ 'targetfs' ];
    $mp_root = '/' . $fs_root;
    $mp_uzip = '/tmp/zfsguru_install_uzip';
    $mp_files = $guru[ 'docroot' ] . '/files';
    $copies = ( ( int )@$postdata[ 'copies' ] > 0 ) ? $postdata[ 'copies' ] : 1;
    $compression = ( @isset($postdata[ 'compression' ]) ) ?
    $postdata[ 'compression' ] : 'lzjb';
    $swapsize = @$postdata[ 'configureswap_size' ];
    $layout = @$postdata[ 'filesystem_layout' ];
    $cam_boot_delay = ( @$postdata[ 'cam_boot_delay' ] == 'on' ) ?
    ( int )@$postdata[ 'cam_boot_delay_sec' ] * 1000 : false;
    $copysysimg = ( @$postdata[ 'copysysimg' ] == 'on' );
    $distfile = $mp_root . '/zfsguru.dist';
    $sha512file = $mp_root . '/zfsguru.sha512';
    $dist_type = 'RoZ';
    $conf_dist = $guru[ 'docroot' ] . '/files/install/zfsguru-dist';
    $conf_tuning = $guru[ 'docroot' ] . '/files/install/zfsguru-tuning';

    // other pools (needed to disable bootfs on all but the active boot pool)
    $otherpools = array();
    $zpools = zfs_pool_list();
    foreach ( $zpools as $zpoolname => $zpool ) {
        if ($zpoolname != $poolname ) {
            if (in_array($zpool[ 'status' ], array( 'ONLINE', 'DEGRADED' )) ) {
                $otherpools[] = $zpoolname;
            }
        }
    }

    // determine extra filesystems to create
    $zfs_extra_filesystems = array();
    if (strpos($layout, 'usr') !== false ) {
        $zfs_extra_filesystems[] = 'usr';
    }
    if (strpos($layout, 'var') !== false ) {
        $zfs_extra_filesystems[] = 'var';
    }
    if (strpos($layout, 'varlog') !== false ) {
        $zfs_extra_filesystems[] = 'var/log';
    }
    $quota_on_varlog = ( strpos($layout, 'quota') !== false );

    // locate system image
    $locate = zfsguru_locatesystem();
    $sysloc = @$locate[ 'name' ][ $version ][ 'path' ];
    $sha512 = zfsguru_system_sha512($version, $source, $locate);

    // mount media again (zfsguru_locatesystem mounts but also unmounts)
    if ($source == 'livecd' ) {
        zfsguru_mountlivecd();
    }
    if ($source == 'usb' ) {
        zfsguru_mountusb();
    }

    // sanity
    if (!$sysloc ) {
        error('system image ' . htmlentities($version) . ' (' . $source . ') not found!');
    }
    if (!@is_readable($sysloc) ) {
        error('system image "' . htmlentities($sysloc) . '" not found or not readable!');
    }
    if (!$sha512 ) {
        error('could not find checksum file (.sha512) for system image!');
    }

    // start commands array
    $commands = array();

    // verify system image
    $commands[ 'VERIFY-1' ] = 'if [ "`/sbin/sha512 -q ' . escapeshellarg($sysloc)
    . '`" = ' . escapeshellarg($sha512)
    . ' ]; then /usr/bin/true; else /usr/bin/false; fi';

    // create ZFSguru filesystems
    $i = 1;
    if (!@is_dir('/' . $fs_zfsguru) ) {
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs create ' . $fs_zfsguru . ' > /dev/null 2>&1';
    }
    if (!@is_dir('/' . $fs_zfsguru_download) ) {
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs create ' . $fs_zfsguru_download . ' > /dev/null 2>&1';
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set compression=off '
        . escapeshellarg($fs_zfsguru_download);
        $commands[ 'CREATEFS-' . $i++ ] = '/usr/sbin/chown root:888 /' . $fs_zfsguru_download;
        $commands[ 'CREATEFS-' . $i++ ] = '/bin/chmod 775 /' . $fs_zfsguru_download;
    }

    // new root filesystem
    $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs create ' . escapeshellarg($fs_root);
    foreach ( $zfs_extra_filesystems as $extrafs ) {
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs create ' . escapeshellarg($fs_root . '/' . $extrafs);
    }

    // set quota on var/log filesystem if applicable
    if ($quota_on_varlog ) {
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set quota="128m" ' . escapeshellarg($fs_root . '/var/log');
    }

    // no access times
    $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set atime=off ' . escapeshellarg($fs_root);

    // synchronous writes
    $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set sync=standard ' . escapeshellarg($fs_root);

    // deduplication
    $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set dedup=off ' . escapeshellarg($fs_root);

    // ditto blocks (copies=n)
    $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set copies=' . ( int )$copies . ' ' . escapeshellarg($fs_root);
    if (in_array('var/log', $zfs_extra_filesystems) ) {
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set copies=1 ' . escapeshellarg($fs_root . '/var/log');
    }

    // compression
    $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set compression=' . escapeshellarg($compression) . ' '
    . escapeshellarg($fs_root);

    // swap volume (optional)
    if (( double )$swapsize > 0 ) {
        // swap volume (/<pool>/zfsguru/SWAP)
        $swapvol = $fs_zfsguru . '/SWAP';
        // size in binary gigabytes (can be fractions like 0.5)
        $swapsize_gib = ( double )$swapsize . 'g';
        // create ZVOL (-s means sparse zvol - so doesn't take space initially)
        if (!file_exists('/dev/zvol/' . $swapvol) ) {
            $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs create -V ' . $swapsize_gib . ' -s ' . escapeshellarg($swapvol);
            $commands[ 'CREATEFS-' . $i++ ] = '/sbin/zfs set org.freebsd:swap=on ' . escapeshellarg($swapvol);
        }
        $commands[ 'CREATEFS-' . $i++ ] = '/sbin/swapon ' . escapeshellarg('/dev/zvol/' . $swapvol) . '; /usr/bin/true';
    }

    // mounting system image
    $commands[ 'INSTALL-1' ] = '/bin/mkdir -p ' . escapeshellarg($mp_uzip);
    $commands[ 'INSTALL-2' ] = '/sbin/mdmfs -P -F ' . escapeshellarg($sysloc) . ' -o ro md.uzip ' . escapeshellarg($mp_uzip);

    // install system image
    $commands[ 'INSTALL-3' ] = '/usr/bin/tar cPf - ' . escapeshellarg($mp_uzip)
    . ' | /usr/bin/tar x -C ' . escapeshellarg($mp_root) . ' --strip-components 3 -f -';

    // unmount system image
    $commands[ 'INSTALL-4' ] = '/bin/sync';
    $commands[ 'INSTALL-5' ] = '/sbin/umount ' . escapeshellarg($mp_uzip);
    $commands[ 'INSTALL-6' ] = '/bin/ls -l ' . escapeshellarg($mp_root);

    // write configuration files
    // TODO: require tmpfs and other kmods for USB/RoR distribution
    // TODO: remove /boot/zfs/zpool.cache ?
    $i = 1;
    // omit zfsguru-dist for RoZ installations?
    // $commands['CONFIGURE-'.$i++] = '/bin/cp -p '.escapeshellarg($conf_dist).' '
    //  .escapeshellarg($mp_root.'/etc/rc.d/');
    $commands[ 'CONFIGURE-' . $i++ ] = '/bin/cp -p ' . escapeshellarg($conf_tuning) . ' '
    . escapeshellarg($mp_root . '/usr/local/etc/rc.d/');

    // append fstab file
    $fstab = chr(10) . '# Added by ZFSguru installation' . chr(10);
    $fstab .= $fs_root . '       /               zfs     rw 0 0' . chr(10);
    foreach ( $zfs_extra_filesystems as $extrafs ) {
        $fstab .= $fs_root . '/' . $extrafs . '	/' . $extrafs . '	zfs	rw 0 0' . chr(10);
    }
    $commands[ 'CONFIGURE-' . $i++ ] = 'cat <<EOF >' . $mp_root . '/etc/fstab'
    . chr(10) . $fstab . chr(10) . 'EOF' . chr(10) . '/usr/bin/true';

    // CAM boot delay (optional)
    if (is_int($cam_boot_delay) ) {
        $commands[ 'CONFIGURE-' . $i++ ] = '/usr/sbin/sysrc -f ' . escapeshellarg(
            $mp_root . '/boot/loader.conf' 
        ) . ' kern.cam.boot_delay=' . ( int )$cam_boot_delay;
    }

    // write system distribution file
    $commands[ 'CONFIGURE-' . $i++ ] = 'echo -n ' . escapeshellarg($dist_type) . ' > '
    . escapeshellarg($distfile);
    $commands[ 'CONFIGURE-' . $i++ ] = 'echo -n ' . escapeshellarg($sha512) . ' > '
    . escapeshellarg($sha512file);

    // copy web interface
    $commands[ 'CONFIGURE-' . $i++ ] = '/bin/cp -Rp '
    . escapeshellarg(realpath($guru[ 'docroot' ]) . '/') . ' '
    . escapeshellarg($mp_root . '/usr/local/www/zfsguru');

    // unmount filesystems
    $commands[ 'CONFIGURE-' . $i++ ] = '/bin/sync';
    foreach ( array_reverse($zfs_extra_filesystems) as $extrafs ) {
        $commands[ 'CONFIGURE-' . $i++ ] = '/sbin/zfs umount '
        . escapeshellarg($fs_root . '/' . $extrafs);
    }
    $commands[ 'CONFIGURE-' . $i++ ] = '/sbin/zfs umount ' . escapeshellarg($fs_root);

    // mark boot filesystem as legacy mountpoint
    $commands[ 'CONFIGURE-' . $i++ ] = '/sbin/zfs set mountpoint=legacy '
    . escapeshellarg($fs_root);

    // activate boot filesystem
    $commands[ 'CONFIGURE-' . $i++ ] = '/sbin/zpool set bootfs='
    . escapeshellarg($fs_root) . ' ' . escapeshellarg($poolname);

    // deactivate boot filesystems of other pools
    foreach ( $otherpools as $otherpool ) {
        $commands[ 'CONFIGURE-' . $i++ ] = '/sbin/zpool set bootfs= '
        . escapeshellarg($otherpool);
    }

    // copy system image (optional)
    if ($copysysimg ) {
        $platform = common_systemplatform();
        $copysys_filename = 'ZFSguru-system-' . $version . '-' . $platform . '.ufs.uzip';
        $commands[ 'COPYSYS-1' ] = '/bin/cp -p ' . escapeshellarg($sysloc) . ' '
        . escapeshellarg('/' . $fs_zfsguru_download . '/' . $copysys_filename);
        $commands[ 'COPYSYS-2' ] = 'echo -n ' . escapeshellarg($sha512) . ' > '
        . escapeshellarg('/' . $fs_zfsguru_download . '/' . $copysys_filename . '.sha512');
    }

    // finish
    if (( $source == 'livecd' )OR( $source == 'usb' ) ) {
        $commands[ 'FINISH-1' ] = '/bin/sync; /sbin/umount -f ' . escapeshellarg(dirname($sysloc));
    } else {
        $commands[ 'FINISH-1' ] = '/usr/bin/true';
    }

    // return set of commands back to main zfsguru_install function
    return $commands;
}

function zfsguru_install_ror( $postdata )
{
    viewarray($postdata);
    die('RoR install');
}

function zfsguru_install_rom( $postdata )
{
    viewarray($postdata);
    die('RoM install');

    // legacy:
    $idata[ 'path_mbr' ] = @$_POST[ 'path_boot_mbr' ];
    $idata[ 'path_loader' ] = @$_POST[ 'path_boot_loader' ];
    $idata[ 'loaderconf' ] = $guru[ 'docroot' ] . '/files/emb_loader.conf';
}

function zfsguru_install_progress( & $activetask, & $installtasks )
{
    // required library
    activate_library('background');

    // query background job
    $query = background_query('ZFSguru-install');

    // array of description
    $desc = array(
    'INIT' => 'Initialize installation',
    'VERIFY' => 'Verify system image integrity',
    'CREATEFS' => 'Create ZFS filesystems',
    'INSTALL' => 'Install system image',
    'CONFIGURE' => 'Configure installation',
    'COPYSYS' => 'Copy system image',
    'FINISH' => 'Finish installation',
    );

    // list of installation tasks
    $installtasks = array();
    if (@is_array($query[ 'storage' ][ 'commands' ]) ) {
        foreach ( $query[ 'storage' ][ 'commands' ] as $ctag => $cdata ) {
            $prefix = substr($ctag, 0, strpos($ctag, '-'));
            if (!@isset($desc[ $prefix ]) ) {
                continue;
            }
            $number = ( int )substr($ctag, strpos($ctag, '-') + 1);
            $xtra = array(
            'name' => $desc[ $prefix ],
            'command' => $query[ 'storage' ][ 'commands' ][ $ctag ],
            );
            $installtasks[ $prefix ][ $number ] = array_merge($query[ 'ctag' ][ $ctag ], $xtra);
        }
    }

    $activetask = false;
    if (!@$query[ 'exists' ] ) {
        // non-existent
        return null;
    } elseif (!$query[ 'running' ] ) {
        // completed, return true when successful, or false when failed
        background_remove('ZFSguru-install');
        if ($query[ 'error' ] ) {
            return false;
        } else {
            return true;
        }
    }
    else {
        // running
        $activetask = $desc[ 'INIT' ];
        foreach ( $query[ 'storage' ][ 'commands' ] as $tag => $data ) {
            $prefix = ( strpos($tag, '-') !== false ) ?
            substr($tag, 0, strpos($tag, '-')) : $tag;
            if (!( strlen($query[ 'ctag' ][ $tag ][ 'rv' ]) > 0 ) ) {
                $activetask = $desc[ $prefix ];
                break;
            }
        }
        return true;
    }
}

function zfsguru_update_webinterface( $tarball )
{
    global $guru;

    // requires elevated privileges
    activate_library('super');

    // redirect URL
    $url = 'status.php';

    // sanity checks
    if (!file_exists($tarball) ) {
        error('could not find tarball: "' . htmlentities($tarball) . '"');
    }
    if (!is_readable($tarball) ) {
        error('no read privileges for tarball: "' . htmlentities($tarball) . '"');
    }

    // extract tarball to web-interface directory (overwriting the scripts on spot)
    $command = '/usr/bin/tar x -C ' . escapeshellarg($guru[ 'docroot' ])
    . ' -f ' . escapeshellarg($tarball);
    $result = super_execute($command);
    $rv = $result[ 'rv' ];
    if ($rv != 0 ) {
        error('got error code ' . ( int )$rv . ' when trying to update web-interface!');
    }

    // success, redirect user after sleeping; but first register a notification
    page_feedback(
        'web-interface updated from: ' . htmlentities(basename($tarball)),
        'b_success' 
    );
    page_feedback(
        'please press F5 or click the refresh page icon - otherwise '
        . 'pages may not be displayed properly', 'c_notice' 
    );
    sleep(1);
    redirect_url($url);
}
