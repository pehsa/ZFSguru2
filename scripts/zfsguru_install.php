#!/usr/local/bin/php

<?php

/*
 *
 ** ZFSguru installation script
 ** installs Root-on-ZFS or Embedded distribution on target device
 ** part of ZFSguru distribution
 ** Copyright (C) 2010-2015, ZFSguru.com
 *
 */


// variables
$tag = '* ';
$data = array();
$scriptversion = 15;
$fileversion = 1;
$filelocation = '/tmp/guru_install.dat';
$temp_mp = '/mnt/';
$source = trim(`pwd`) . '/files';

// procedures
install_init();
install_summary();
install_verify();
if ($data[ 'dist' ] == 'RoZ' ) {
    install_roz();
} elseif ($data[ 'dist' ] == 'RoR' ) {
    install_ror();
} elseif ($data[ 'dist' ] == 'RoM' ) {
    install_rom();
} else {
    install_error('Invalid distribution!');
}

// functions

function install_init()
{
    global $data, $fileversion, $filelocation;
    // sanity checks
    if (!file_exists($filelocation) ) {
        install_error('Data file "' . $filelocation . '" not found!');
    }
    if (!is_readable($filelocation) ) {
        install_error('Data file "' . $filelocation . '" not readable!');
    }
    // file statistics
    clearstatcache();
    $stat = @stat($filelocation);
    // check if owner is root
    if (@$stat[ 'uid' ] != 0 ) {
        install_error('Data file "' . $filelocation . '" is not owned by root!');
    }
    // check if permissions are '0644' (only writeable by root)
    if (@substr(sprintf('%o', $stat[ 'mode' ]), -4) != '0644' ) {
        install_error('Data file "' . $filelocation . '" has invalid file permissions!');
    }

    // read configuration file and unserialize
    $filecontents = file_get_contents($filelocation);
    $data = @unserialize($filecontents);
    if (!is_array($data) ) {
        install_error('Data file "' . $filelocation . '" bad contents!');
    }

    // remove file again
    unlink($filelocation);
}

function install_summary()
{
    global $data, $tag, $scriptversion, $source;

    echo( $tag . 'Starting installation' . chr(10) );
    flush_buffer();
    echo( 'install script version: ' . $scriptversion . chr(10) );
    echo( 'distribution type: ' . $data[ 'dist' ] . chr(10) );
    echo( 'target device: ' . $data[ 'target' ] . chr(10) );
    echo( 'source data: ' . $source . chr(10) );
    if ($data[ 'dist' ] == 'RoZ' ) {
        echo( 'boot filesystem: ' . $data[ 'bootfs' ] . chr(10) );
    } elseif ($data[ 'dist' ] == 'RoR' ) {
    }
    elseif ($data[ 'dist' ] == 'RoM' ) {
        echo( 'MBR bootcode: ' . $data[ 'path_mbr' ] . chr(10) );
        echo( 'loader bootcode: ' . $data[ 'path_loader' ] . chr(10) );
    }
    echo( 'system version: ' . $data[ 'version' ] . chr(10) );
    echo( 'system location: ' . $data[ 'sysloc' ] . chr(10) );
    echo( 'system size: ' . $data[ 'sysimg_size' ] . ' bytes' . chr(10) );
    echo( 'system SHA512 checksum: ' . substr($data[ 'checksum_sha512' ], 0, 32)
    . '...' . chr(10) );
    echo( 'loader.conf: ' . $data[ 'loaderconf' ] . chr(10) );
    if ($data[ 'dist' ] == 'rootonzfs' ) {
        echo( 'preserve system image: ' . ( int )$data[ 'options' ][ 'copysysimg' ]
        . chr(10) );
        echo( 'ditto copies: ' . $data[ 'options' ][ 'copies' ] . chr(10) );
        echo( 'compression: ' . $data[ 'options' ][ 'compression' ] . chr(10) );
    }
    echo( chr(10) );
    flush_buffer();
}

function install_verify()
{
    global $data, $tag;

    // check if system image is set, actually exists and is readable
    echo( $tag . 'Verifying system image' . chr(10) );
    flush_buffer();
    if (@strlen($data[ 'sysloc' ]) < 1 ) {
        install_error('Data file contains invalid system image location');
    }
    if (!@file_exists($data[ 'sysloc' ]) ) {
        install_error('System image "' . $data[ 'sysloc' ] . '" not found');
    }
    if (!is_readable($data[ 'sysloc' ]) ) {
        install_error('System image "' . $data[ 'sysloc' ] . '" not readable');
    }

    // check for proper size
    if (filesize($data[ 'sysloc' ]) < 1 ) {
        install_error('System image "' . $data[ 'sysloc' ] . '" has invalid size');
    }
    if (filesize($data[ 'sysloc' ]) != $data[ 'sysimg_size' ] ) {
        install_error('System image "' . $data[ 'sysloc' ] . '" has incorrect size');
    }

    // verify checksum of system image file
    echo( $tag . 'Verifying SHA512 checksum' . chr(10) );
    flush_buffer();
    $sha512 = shell_exec('/sbin/sha512 -q ' . escapeshellarg($data[ 'sysloc' ]));
    if ($data[ 'checksum_sha512' ] != $sha512 ) {
        install_error(
            'System image "' . $data[ 'sysloc' ]
            . '" failed SHA512 checksum! Expected: ' . $data[ 'checksum_sha512' ] 
        );
    }
}

function install_roz()
{
    global $data, $tag, $temp_mp, $source;

    // variables
    $root = $data[ 'target' ] . '/' . $data[ 'bootfs' ];
    $proot = '/' . $root;
    // $umount_fs = array($root.'/usr', $root.'/var', $root);
    $umount_fs = array( $root );

    // zfsguru specific filesystems
    $fs_zfsguru = $data[ 'target' ] . '/zfsguru';
    $fs_download = $data[ 'target' ] . '/zfsguru/download';

    install_error('DIE ROZ!');
    die(1);
    // destroy existing boot filesystem
    // REMOVE?!
    exec('/sbin/zfs destroy -r ' . $root . ' > /dev/null 2>&1');

    // create filesystems
    echo( $tag . 'Creating ZFS filesystems' . chr(10) );
    flush_buffer();
    // create standard zfs filesystem structure
    exec('/sbin/zfs create ' . $root);
    // TODO: optionally create additional filesystems + allowing quota on /var/log
    // exec('/sbin/zfs create '.$root.'/usr');
    // exec('/sbin/zfs create '.$root.'/var');
    // exec('/sbin/zfs create '.$root.'/var/log');
    // create zfsguru specific filesystems
    exec('/sbin/zfs create ' . $fs_zfsguru . ' > /dev/null 2>&1');
    exec('/sbin/zfs create ' . $fs_download . ' > /dev/null 2>&1');
    exec('/usr/sbin/chown root:www /' . $fs_download);
    exec('/bin/chmod 775 /' . $fs_download);

    // synchronous writes
    exec('/sbin/zfs set sync=standard ' . $root);

    // deduplication
    exec('/sbin/zfs set dedup=off ' . $root);
    exec('/sbin/zfs set dedup=off ' . $fs_download);

    // ditto copies ('additional protection against bad blocks')
    exec('/sbin/zfs set copies=' . $data[ 'options' ][ 'copies' ] . ' ' . $root);
    // exec('/sbin/zfs set copies=1 '.$root.'/var/log');

    // compression
    exec('/sbin/zfs set compression=' . $data[ 'options' ][ 'compression' ] . ' ' . $root);
    // exec('/sbin/zfs set compression=off '.$fs_download);
    // $compression = $data['options']['compression'];
    // if ($compression != 'off')
    // {
    //  exec('/sbin/zfs set compression='.$compression.' '.$root.'/usr');
    //  exec('/sbin/zfs set compression='.$compression.' '.$root.'/var');
    // }

    // set quota on <root>/var/log
    // exec('/sbin/zfs set quota=128m '.$root.'/var/log');

    // check if filesystems were created
    // if ((!@is_dir($proot.'/usr')) OR (!@is_dir($proot.'/var')))
    //  install_error('Could not create boot filesystems');

    // mount system image
    echo( $tag . 'Mounting system image' . chr(10) );
    flush_buffer();
    exec(
        '/sbin/mdmfs -P -F ' . $data[ 'sysloc' ] . ' -o ro md.uzip ' . $temp_mp,
        $output, $rv 
    );
    if (!@is_dir($temp_mp . '/boot') ) {
        install_error('Mounting system image to ' . $temp_mp . ' was unsuccessful!');
    }

    // install system image
    echo( $tag . 'Installing ZFSguru system image' . chr(10) );
    flush_buffer();
    exec(
        '/usr/bin/tar cPf - ' . $temp_mp . ' | tar x -C ' . $proot
        . ' --strip-components 2 -f -', $output, $rv 
    );
    if ($rv != 0 ) {
        install_error(
            'Transferring data from system image to ' . $proot
            . ' filesystem has failed!', $rv 
        );
    }
    exec('/sbin/umount ' . $temp_mp);

    // activate boot filesystem, making it bootable
    echo( $tag . 'Activating boot filesystem' . chr(10) );
    flush_buffer();
    exec('/sbin/zpool set bootfs=' . $root . ' ' . $data[ 'target' ], $output, $rv);
    if ($rv != 0 ) {
        install_error('Activating boot filesystem ' . $root . ' has failed!', $rv);
    }

    // write system distribution file to boot filesystem
    $distfile = $proot . '/zfsguru.dist';
    echo( $tag . 'Writing distribution file ' . $distfile . chr(10) );
    flush_buffer();
    file_put_contents($distfile, $data[ 'checksum_sha512' ]);

    // transfer configuration files
    echo( $tag . 'Copying system configuration files' . chr(10) );
    flush_buffer();
    $rv = array();
    exec(
        '/bin/cp -p ' . $data[ 'loaderconf' ] . ' ' . $proot . '/boot/loader.conf',
        $output, $rv[] 
    );
    exec(
        '/bin/cp -p ' . $source . '/roz_rc.conf ' . $proot . '/etc/rc.conf', $output,
        $rv[] 
    );
    exec('/bin/cp -p ' . $source . '/roz_motd ' . $proot . '/etc/motd', $output, $rv[]);
    exec('/bin/rm -f /' . $root . '/etc/rc.d/zfsguru', $output, $rv[]);
    exec(
        '/bin/cp -p /usr/local/etc/smb4.conf ' . $proot . '/usr/local/etc/',
        $output, $rv[] 
    );
    exec('/bin/cp -p /boot/zfs/zpool.cache ' . $proot . '/boot/zfs/', $output, $rv[]);
    foreach ( $rv as $returnvalue ) {
        if ($returnvalue !== 0 ) {
            install_error(
                'Got return value ' . ( int )$returnvalue
                . ' while copying file', $returnvalue 
            );
        }
    }

    // write fstab file
    echo( $tag . 'Creating new fstab on boot filesystem:' . chr(10) );
    flush_buffer();
    $fstab = $root . '	/		zfs	rw 0 0' . chr(10);
    //  .$root.'/usr        /usr        zfs    rw 0 0'.chr(10)
    //  .$root.'/var        /var        zfs    rw 0 0'.chr(10)
    //  .$root.'/var/log    /var/log    zfs    rw 0 0'.chr(10);
    echo( $fstab );
    flush_buffer();
    file_put_contents($proot . '/etc/fstab', $fstab);
    if (!file_exists($proot . '/etc/fstab') ) {
        install_error('Could not create ' . $proot . '/etc/fstab file');
    }

    // copy web interface
    echo( $tag . 'Copying ZFSguru web interface' . chr(10) );
    flush_buffer();
    exec('/bin/mkdir -p ' . $proot . '/usr/local/www/zfsguru');
    exec('/bin/cp -Rp /usr/local/www/zfsguru/* ' . $proot . '/usr/local/www/zfsguru/');

    // copy system image (optional)
    if (@$data[ 'options' ][ 'copysysimg' ] ) {
        echo( $tag . 'Copying system image (optional)' . chr(10) );
        flush_buffer();
        exec('/bin/cp -p ' . $data[ 'sysloc' ] . ' /' . $fs_download . '/');
        exec('/bin/cp -p ' . $data[ 'sysloc' ] . '.sha512 /' . $fs_download . '/');
    }

    // unmount filesystems
    echo( $tag . 'Unmounting boot filesystem' . chr(10) );
    flush_buffer();
    foreach ( $umount_fs as $fs ) {
        exec('/sbin/zfs umount ' . $fs, $output, $rv);
        if ($rv != 0 ) {
            install_error('Could not unmount filesystem: ' . $fs, $rv);
        }
    }

    // sync
    exec('/bin/sync');
    exec('/bin/sync');
    exec('/bin/sync');
    sleep(0.5);

    // mark boot filesystem as legacy mountpoint
    echo( $tag . 'Setting legacy mountpoint on ' . $root . chr(10) );
    flush_buffer();
    exec('/sbin/zfs set mountpoint=legacy ' . $root, $output, $rv);
    if ($rv != 0 ) {
        install_error('Could not set legacy mountpoint on ' . $root, $rv);
    }

    // done
    echo( chr(10) );
    echo( '*** Done! *** Reboot system now and boot from any of the pool members' );
    flush_buffer();
}

function install_ror()
{
    global $data, $tag, $temp_mp, $source;

    // todo
    echo( $tag . 'NOT WORKING YET' . chr(10) );
    die(1);
}

function install_rom()
{
    global $data, $tag, $temp_mp, $source;
    // todo
    echo( $tag . 'NOT WORKING YET' . chr(10) );
    die(1);

    $target = $data[ 'target' ];
    $tdev = '/dev/' . $target;
    $createdatapartition = false;
    $size_syspartition = 655360;
    $size_datapartition = 0;
    $gpt_system_label = 'GURU-EMBEDDED';
    $gpt_data_label = 'EMBEDDED-DATA';
    $path_syslabel = '/dev/gpt/' . $gpt_system_label;
    $path_datalabel = '/dev/gpt/' . $gpt_data_label;
    $webinterface_name = 'ZFSguru-webinterface.tgz';
    $webinterface_source = `realpath .`;

    // zero write target device
    echo( $tag . 'Zero-writing first 100MiB of target device ' . $tdev . chr(10) );
    flush_buffer();
    exec('/bin/dd if=/dev/zero of=' . $tdev . ' bs=1m count=100', $result, $rv);
    if ($rv != 0 ) {
        install_error('Could not zero-write first 100MiB of target device', $rv);
    }

    // create GPT partition scheme
    echo( $tag . 'Creating GPT partition scheme on target' . chr(10) );
    flush_buffer();
    exec('/sbin/gpart create -s GPT ' . $target);

    // create GPT partitions
    echo( $tag . 'Creating partitions' . chr(10) );
    flush_buffer();
    exec('/sbin/gpart add -b 128 -s 512 -t freebsd-boot ' . $target);
    exec(
        '/sbin/gpart add -b 2048 -s ' . $size_syspartition . ' -t freebsd-ufs -l '
        . $gpt_system_label . ' ' . $target, $output, $rv 
    );
    if ($createdatapartition ) {
        exec(
            '/sbin/gpart add -b 2048 -s ' . $size_datapartition . ' -t freebsd-ufs -l '
            . $gpt_data_label . ' ' . $target, $output, $rv 
        );
    }
    if ($rv != 0 ) {
        install_error('Failed adding GPT partitions to target device', $rv);
    }

    // boot code
    echo( $tag . 'Inserting bootcode to target device' . chr(10) );
    flush_buffer();
    exec(
        '/sbin/gpart bootcode -b ' . $data[ 'path_mbr' ] . ' -p ' . $data[ 'path_loader' ]
        . ' -i 1 ' . $target, $output, $rv 
    );
    if ($rv != 0 ) {
        install_error('Failed adding bootcode to target device', $rv);
    }

    // create UFS filesystem on system partition
    echo( $tag . 'Creating UFS2 filesystem on system partition' . chr(10) );
    flush_buffer();
    exec(
        '/sbin/newfs -U -m 2 -i 2048 ' . $path_syslabel . ' > /dev/null', $output,
        $rv 
    );
    if ($rv != 0 ) {
        install_error('Failed to create UFS filesystem on ' . $path_syslabel, $rv);
    }

    // mount the created UFS filesystem
    echo( $tag . 'Mounting ' . $path_syslabel . ' to temporary mountpoint' . chr(10) );
    flush_buffer();
    exec('/bin/mkdir -p /usb');
    exec('/bin/mount -t ufs -o noatime ' . $path_syslabel . ' ' . $temp_mp, $output, $rv);
    if ($rv != 0 ) {
        install_error('Failed to mount UFS2 filesystem to ' . $temp_mp, $rv);
    }

    // populate mounted filesystem
    echo( $tag . 'Populating mounted filesystem' . chr(10) );
    flush_buffer();

    echo( '* copying boot directory' . chr(10) );
    exec('/usr/bin/tar c -C / -f - boot | tar x -C ' . $temp_mp . ' -f -');

    echo( '* copying rescue directory' . chr(10) );
    exec('/usr/bin/tar c -C / -f - rescue | tar x -C ' . $temp_mp . ' -f -');

    echo( '* copying /boot/loader.conf' . chr(10) );
    exec('/bin/cp -p ' . $source . '/emb_loader.conf ' . $temp_mp . '/boot/loader.conf');

    echo( '* copying system image and checksums' . chr(10) );
    exec('/bin/cp -p ' . $data[ 'sysloc' ] . '* ' . $temp_mp);

    echo( '* creating config directory' . chr(10) );
    exec('/bin/mkdir -p ' . $temp_mp . '/config');
    exec('/usr/sbin/chown www:www ' . $temp_mp . '/config');
    exec('/bin/chmod 770 ' . $temp_mp . '/config');

    echo( '* copying mod directory to /mod' . chr(10) );
    exec('/bin/cp -Rp ' . $source . '/mod ' . $temp_mp . '/');
    exec('/usr/sbin/chown -R root:wheel ' . $temp_mp . '/mod');
    exec('/bin/chmod 750 ' . $temp_mp . '/mod');

    echo( '* compressing web interface' . chr(10) );
    exec(
        '/usr/bin/tar cfz ' . $temp_mp . '/' . $webinterface_name . ' -C '
        . $webinterface_source . ' *', $output, $rv 
    );
    if ($rv != 0 ) {
        install_error('Could not copy web-interface', $rv);
    }
    echo( '* done populating system filesystem' . chr(10) );

    /*
    TUNE=${SCRIPT}/tunables
    TMPFS=/tmpfs
    SYSTEM=${TMPFS}/system
    SYSTEM_NAME=system.ufs
    CDROM=${TMPFS}/cdrom
    CDROM_MBR=${CDROM}/boot/pmbr
    CDROM_GPT=${CDROM}/boot/gptboot
    PRELOADED=${TMPFS}/preloaded
    USB=${TMPFS}/usb
    SCHEME=GPT
    SIZE_MB=325
    #SIZE_PARTITION=819200          # = 400MiB
    SIZE_PARTITION=655360           # = 320MiB
    MD_NR=9
    MD="md${MD_NR}"
    MD_DEV="/dev/${MD}"
    LABEL=GURU-USB
    LABEL_DEV=/dev/gpt/${LABEL}
    NAME=guruusb
    WEBINTERFACE_SOURCE="`realpath ${SCRIPT}/zfsguru017`"
    WEBINTERFACE_NAME="ZFSguru-webinterface.tgz"
    */

}

function install_error( $errormsg, $rv = false )
{
    global $tag;
    echo( chr(10) . chr(10) );
    echo( 'ERROR: ' . $errormsg . chr(10) );
    if ($rv !== false ) {
        echo( 'Return value: ' . ( int )$rv . chr(10) . chr(10) );
    }
    echo( $tag . 'Script execution halted!' . chr(10) );
    die(1);
}

function flush_buffer() 
{
    @ob_end_flush();
    @ob_flush();
    @flush();
    @ob_start();
}
