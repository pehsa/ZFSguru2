<?php

function content_status_status() 
{
    global $guru;

    // required libraries
    activate_library('disk');
    activate_library('system');
    activate_library('zfs');

    // gather data
    $fbsdver = trim(shell_exec("/usr/bin/uname -r"));
    $cpu = common_sysctl('hw.model');
    $cpucount = common_sysctl('hw.ncpu');
    $arch = trim(shell_exec("/usr/bin/uname -p"));
    $currentver = common_systemversion();
    $syszfsver = zfs_version();
    $physdisks = disk_detect_physical();
    $physmem = system_detect_physmem();
    $vmsolution = system_detect_vmenvironment();
    $uptime = system_uptime();
    $systime = trim(shell_exec("date"));
    $network_speed = system_detect_networkspeed();

    // distributions
    $dist_type = common_distribution_type();
    $dist_name = common_distribution_name($dist_type);

    $dist_green = array(
    'RoZ' => '',
    'RoR' => '',
    'RoR+union' => '',
    'RoM' => '',
    );
    $dist_amber = array();
    $dist_red = array();

    // top status bar
    $unknownsys = ( $currentver[ 'sysver' ] === 'unknown' );
    $class_sysimg_official = ( !$unknownsys ) ? 'normal' : 'hidden';
    $class_sysimg_unknown = ( $unknownsys ) ? 'normal' : 'hidden';
    $class_dist_green = ( @isset($dist_green[ $dist_type ]) ) ? 'normal' : 'hidden';
    $class_dist_amber = ( @isset($dist_amber[ $dist_type ]) ) ? 'normal' : 'hidden';
    $class_dist_red = ( @isset($dist_red[ $dist_type ]) ) ? 'normal' : 'hidden';
    $class_dist_livecd = ( $dist_type === 'livecd' ) ? 'normal' : 'hidden';
    $class_dist_usb = ( $dist_type === 'usb' ) ? 'normal' : 'hidden';
    $class_dist_unknown = ( $dist_type === 'unknown' ) ? 'normal' : 'hidden';

    // top bar color
    if (!$unknownsys AND( $dist_type === 'RoZ' ) ) {
        $status_color = 'grey';
    } elseif (!$unknownsys AND(strpos($dist_type, 'RoR') === 0) ) {
        $status_color = 'red';
    } elseif (!$unknownsys AND( $dist_type === 'RoM' ) ) {
        $status_color = 'blue';
    } elseif (!$unknownsys AND( ( $dist_type === 'livecd' )OR( $dist_type === 'usb' ) ) ) {
        $status_color = 'amber';
    } elseif ($unknownsys ) {
        $status_color = 'red';
    } else {
        // known system version but unknown distribution
        $status_color = 'red';
    }

    // top bar data
    $zfs_spa = $syszfsver[ 'spa' ];
    $zfs_zpl = $syszfsver[ 'zpl' ];

    // detect virtualization environment
    $class_vm_esxi = ( $vmsolution === 'esxi' ) ? 'normal' : 'hidden';
    $class_vm_esxi_rdm = ( $vmsolution === 'esxi_rdm' ) ? 'normal' : 'hidden';
    $class_vm_esxi_vtd = ( $vmsolution === 'esxi_vtd' ) ? 'normal' : 'hidden';
    $class_vm_qemu = ( $vmsolution === 'qemu' ) ? 'normal' : 'hidden';
    $class_vm_vbox = ( $vmsolution === 'vbox' ) ? 'normal' : 'hidden';
    $class_vm_vmware = ( $vmsolution === 'vmware' ) ? 'normal' : 'hidden';
    $class_vm_xen = ( $vmsolution === 'xen' ) ? 'normal' : 'hidden';

    // processor
    if ($arch === 'amd64' ) {
        $arch = '64-bit';
    } elseif ($arch === 'i386' ) {
        $arch = '32-bit';
    }
    if ($cpucount == 1 ) {
        $processor_countstr = 'single core';
    } elseif ($cpucount == 1 ) {
        $processor_countstr = 'single core';
    } elseif ($cpucount == 2 ) {
        $processor_countstr = 'dual core';
    } elseif ($cpucount == 3 ) {
        $processor_countstr = 'triple core';
    } elseif ($cpucount == 4 ) {
        $processor_countstr = 'quad core';
    } elseif ($cpucount == 6 ) {
        $processor_countstr = 'hexa core';
    } elseif ($cpucount == 8 ) {
        $processor_countstr = 'octo core';
    } elseif ($cpucount == 12 ) {
        $processor_countstr = 'dodeca core';
    } elseif ($cpucount == 16 ) {
        $processor_countstr = 'hexadeca core';
    } else {
        $processor_countstr = ( int )$cpucount . '-core';
    }
    // cpu frequency
    $cpu_freq = common_sysctl('dev.cpu.0.freq');
    $class_cpu_freq = ( is_numeric($cpu_freq) ) ? 'normal' : 'hidden';
    $freqscaling = common_sysctl('dev.cpu.0.freq_levels');
    $freqscaling_arr = explode(' ', $freqscaling);
    $freqrange = array();
    foreach ( $freqscaling_arr as $rawtext ) {
        if (strpos($rawtext, '/') != false ) {
            $freqrange[] = ( int )substr($rawtext, 0, strpos($rawtext, '/'));
        }
    }
    $cpu_freq_min = @min($freqrange);
    $cpu_freq_max = @max($freqrange);
    $cpu_freq_scaling = ( ($cpu_freq_min != '')AND($cpu_freq_min != '') ) ? 'normal' : 'hidden';

    // memory
    $memory_installed = sizebinary($physmem[ 'installed' ], 1);
    $memory_usable = sizebinary($physmem[ 'usable' ], 1);

    // disk drives
    $disk_count = array();
    foreach ( $physdisks as $diskname => $diskdata ) {
        $disk_count[ disk_detect_type($diskname) ] =
        ( int )@$disk_count[ disk_detect_type($diskname) ] + 1;
    }
    foreach ( $disk_count as $disktype => $nrofdisks ) {
        page_injecttag(
            array(
            'COUNT_' . strtoupper($disktype) => $nrofdisks,
            'CLASS_' . strtoupper($disktype) => 'not' ) 
        );
    }

    // sensors
    if (stripos($cpu, 'amd') === 0) {
        exec('/sbin/kldstat -n amdtemp.ko', $output, $rv);
        if ($rv == 1 ) {
            system_loadkernelmodule('amdtemp');
        }
    } elseif (stripos($cpu, 'intel') === 0) {
        exec('/sbin/kldstat -n coretemp.ko', $output, $rv);
        if ($rv == 1 ) {
            system_loadkernelmodule('coretemp');
        }
    }
    $cputemp = array();
    for ( $i = 0; $i <= 7; $i++ ) {
        $rawtemp = common_sysctl('dev.cpu.' .$i. '.temperature');
        if (@strlen($rawtemp) > 1 ) {
            $cputemp[] = array(
            'CPUTEMP_CPUNR' => ( $i + 1 ),
            'CPUTEMP_TEMP' => substr($rawtemp, 0, -1)
            );
        } else {
            break;
        }
    }
    $cputemp_nosensor = ( empty($cputemp) ) ? 'normal' : 'hidden';

    // voltage sensors require mbmon to be installed
    $mbmon_path = '/usr/local/bin/mbmon';
    exec($mbmon_path . ' -c1 -r', $mbmon, $rv);
    $class_need_mbmon = 'hidden';
    $class_mbmon_nosensor = 'hidden';
    if (!file_exists($mbmon_path) ) {
        $class_need_mbmon = 'normal';
    } elseif ($rv != 0 ) {
        $class_mbmon_nosensor = 'normal';
    }
    $mbmon_str = implode(chr(10), $mbmon);
    $mbmon = array();
    preg_match('/^TEMP0 :(.*)$/m', $mbmon_str, $mbmon[ 'temp0' ]);
    preg_match('/^TEMP1 :(.*)$/m', $mbmon_str, $mbmon[ 'temp1' ]);
    preg_match('/^TEMP2 :(.*)$/m', $mbmon_str, $mbmon[ 'temp2' ]);
    preg_match('/^FAN0 {2}:(.*)$/m', $mbmon_str, $mbmon[ 'fan0' ]);
    preg_match('/^VC0 {3}:(.*)$/m', $mbmon_str, $mbmon[ 'vcore0' ]);
    preg_match('/^VC1 {3}:(.*)$/m', $mbmon_str, $mbmon[ 'vcore1' ]);
    preg_match('/^V33 {3}:(.*)$/m', $mbmon_str, $mbmon[ 'v33' ]);
    preg_match('/^V50P {2}:(.*)$/m', $mbmon_str, $mbmon[ 'v50' ]);
    preg_match('/^V12P {2}:(.*)$/m', $mbmon_str, $mbmon[ 'v120' ]);
    foreach ( $mbmon as $sensor_name => $pregdata ) {
        $value = @trim($pregdata[ 1 ]);
        $sensor[ $sensor_name ] = $value;
        $sensorclass[ $sensor_name ] = 'hidden';
        if ((strlen($value) > 1) && ( double )$value > 0) {
            if ($sensor_name == 'fan0'
                OR( ( double )$value < 99 )
            ) {
                if ($value {            0            } == '+'
                ) {
                    $sensor[ $sensor_name ] = substr($value, 1);
                    $sensorclass[ $sensor_name ] = 'normal';
                } else {
                    $sensorclass[ $sensor_name ] = 'normal';
                }
            }
        }
    }

    // export new tags
    return array(
    'CLASS_SYSIMG_OFFICIAL' => $class_sysimg_official,
    'CLASS_SYSIMG_UNKNOWN' => $class_sysimg_unknown,
    'CLASS_DIST_GREEN' => $class_dist_green,
    'CLASS_DIST_AMBER' => $class_dist_amber,
    'CLASS_DIST_RED' => $class_dist_red,
    'CLASS_DIST_LIVECD' => $class_dist_livecd,
    'CLASS_DIST_USB' => $class_dist_usb,
    'CLASS_DIST_UNKNOWN' => $class_dist_unknown,

    'CLASS_VM_ESXI' => $class_vm_esxi,
    'CLASS_VM_ESXI_RDM' => $class_vm_esxi_rdm,
    'CLASS_VM_ESXI_VTD' => $class_vm_esxi_vtd,
    'CLASS_VM_QEMU' => $class_vm_qemu,
    'CLASS_VM_VBOX' => $class_vm_vbox,
    'CLASS_VM_VMWARE' => $class_vm_vmware,
    'CLASS_VM_XEN' => $class_vm_xen,

    'STATUS_COLOR' => $status_color,
    'SYSTEM_DIST' => $currentver[ 'dist' ],
    'SYSTEM_VERSION' => $currentver[ 'sysver' ],
    'SYSTEM_SHA512' => $currentver[ 'sha512' ],
    'BSD_VERSION' => $fbsdver,
    'ZFS_SPA' => $zfs_spa,
    'ZFS_ZPL' => $zfs_zpl,
    'DIST_NAME' => $dist_name,

    'PROCESSOR_STRING' => $cpu,
    'PROCESSOR_ARCH' => $arch,
    'PROCESSOR_COUNTSTR' => $processor_countstr,
    'CLASS_CPU_FREQ' => $class_cpu_freq,
    'CPU_FREQ' => $cpu_freq,
    'CPU_FREQ_SCALING' => $cpu_freq_scaling,
    'CPU_FREQ_MIN' => $cpu_freq_min,
    'CPU_FREQ_MAX' => $cpu_freq_max,
    'MEMORY_INSTALLED' => $memory_installed,
    'MEMORY_USABLE' => $memory_usable,
    'NETWORK_SPEED' => $network_speed,

    'TABLE_STATUS_CPUTEMP' => $cputemp,
    'CPUTEMP_NOSENSOR' => $cputemp_nosensor,
    'CLASS_NEED_MBMON' => $class_need_mbmon,
    'CLASS_MBMON_NOSENSOR' => $class_mbmon_nosensor,
    'CLASS_TEMP0' => $sensorclass[ 'temp0' ],
    'CLASS_TEMP1' => $sensorclass[ 'temp1' ],
    'CLASS_TEMP2' => $sensorclass[ 'temp2' ],
    'CLASS_FAN0' => $sensorclass[ 'fan0' ],
    'CLASS_VCORE0' => $sensorclass[ 'vcore0' ],
    'CLASS_VCORE1' => $sensorclass[ 'vcore1' ],
    'CLASS_V33' => $sensorclass[ 'v33' ],
    'CLASS_V50' => $sensorclass[ 'v50' ],
    'CLASS_V120' => $sensorclass[ 'v120' ],
    'SENSOR_TEMP0' => $sensor[ 'temp0' ],
    'SENSOR_TEMP1' => $sensor[ 'temp1' ],
    'SENSOR_TEMP2' => $sensor[ 'temp2' ],
    'SENSOR_FAN0' => $sensor[ 'fan0' ],
    'SENSOR_VCORE0' => $sensor[ 'vcore0' ],
    'SENSOR_VCORE1' => $sensor[ 'vcore1' ],
    'SENSOR_V33' => $sensor[ 'v33' ],
    'SENSOR_V50' => $sensor[ 'v50' ],
    'SENSOR_V120' => $sensor[ 'v120' ],

    'SYSTEM_TIME' => $systime,
    'SYSTEM_UPTIME' => $uptime[ 'uptime' ],
    'SYSTEM_LOADAVG' => $uptime[ 'loadavg' ],
    'SYSTEM_CPUUSAGE' => $uptime[ 'cpupct' ],
    );
}
