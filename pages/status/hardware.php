<?php

function content_status_hardware() 
{
    // fetch DMI data
    $dmidata = dmi_decode();
    $dmi = dmi_analyse($dmidata);
    viewarray($dmi);
    echo( '<p>dmidata</p>' );
    $hw_status = 'X';
    viewarray($dmidata);
    // export tags
    return array(
    'PAGE_TITLE' => 'Hardware',
    'HW_STATUS' => $hw_status
    );
}

function dmi_decode() 
{
    // required library
    activate_library('super');

    // execute command
    $result = super_execute('/usr/local/sbin/dmidecode');

    $split = preg_split('/\nHandle /m', $result[ 'output_str' ]);
    if (count($split) < 2 ) {
        return array();
    }

    $dmidata = array();
    for ( $i = 1; $i <= count($split); $i++ ) {
        @preg_match('/^(0x[0-9a-fA-F]+), DMI type ([0-9]+), ([^\n]+)\n([^\n]+)\n(.+)$/s', $split[ $i ], $matches);
        @preg_match_all('/^[\s]*(.+)\: (.+)$/m', $matches[ 5 ], $datamatches);
        $data = array();
        if (@strlen($matches[ 1 ]) < 1 ) {
            continue;
        }
        if (@is_array($datamatches[ 1 ]) ) {
            foreach ( $datamatches[ 1 ] as $id => $name ) {
                $data[ trim($name) ] = trim($datamatches[ 2 ][ $id ]);
            }
        }
        $dmidata[ $matches[ 1 ] ] = array(
        'id' => $matches[ 1 ],
        'type' => $matches[ 2 ],
        'desc' => $matches[ 4 ],
        'data' => $data
        );
    }
    return $dmidata;
}

function dmi_analyse( $dmi ) 
{
    // BIOS
    foreach ( $dmi as $id => $dmidata ) {
        if (( int )$dmidata[ 'type' ] == 0 ) {
            break;
        }
    }
    //viewarray($dmidata); die('z');
    $bios = array();
    $bios[ 'vendor' ] = $dmidata[ 'data' ][ 'Vendor' ];
    $bios[ 'version' ] = $dmidata[ 'data' ][ 'Version' ];
    $bios[ 'date' ] = $dmidata[ 'data' ][ 'Release Date' ];
    $bios[ 'size' ] = $dmidata[ 'data' ][ 'ROM Size' ];

    // motherboard
    foreach ( $dmi as $id => $dmidata ) {
        if (( int )$dmidata[ 'type' ] == 2 ) {
            break;
        }
    }
    $motherboard = array();
    $motherboard[ 'brand' ] = $dmidata[ 'data' ][ 'Manufacturer' ];
    $motherboard[ 'model' ] = $dmidata[ 'data' ][ 'Product Name' ];
    // processor
    foreach ( $dmi as $id => $dmidata ) {
        if (( int )$dmidata[ 'type' ] == 4 ) {
            break;
        }
    }
    $processor = array();
    $processor[ 'brand' ] = $dmidata[ 'data' ][ 'Manufacturer' ];
    $processor[ 'family' ] = $dmidata[ 'data' ][ 'Family' ];
    $processor[ 'model' ] = $dmidata[ 'data' ][ 'Version' ];
    $processor[ 'status' ] = $dmidata[ 'data' ][ 'Status' ];
    $processor[ 'voltage' ] = $dmidata[ 'data' ][ 'Voltage' ];
    $processor[ 'frontsidebus' ] = $dmidata[ 'data' ][ 'External Clock' ];
    $processor[ 'speed_nominal' ] = $dmidata[ 'data' ][ 'Current Speed' ];
    $processor[ 'speed_turbo' ] = $dmidata[ 'data' ][ 'Max Speed' ];
    $processor[ 'socket' ] = $dmidata[ 'data' ][ 'Upgrade' ];
    $processor[ 'serial' ] = $dmidata[ 'data' ][ 'Serial Number' ];
    $processor[ 'cores' ] = $dmidata[ 'data' ][ 'Core Enabled' ];
    $processor[ 'threads' ] = $dmidata[ 'data' ][ 'Thread Count' ];
    $processor[ 'hyperthreading' ] =
    ( ( int )$processor[ 'threads' ] > ( int )$processor[ 'cores' ] );
    // cache info

    // memory
    $memory = array();
    foreach ( $dmi as $id => $dmidata ) {
        if (( int )$dmidata[ 'type' ] == 17 ) {
            $data_width = ( int )substr(
                $dmidata[ 'data' ][ 'Data Width' ], 0,
                strpos($dmidata[ 'data' ][ 'Data Width' ], ' ') 
            );
            $total_width = ( int )substr(
                $dmidata[ 'data' ][ 'Data Width' ], 0,
                strpos($dmidata[ 'data' ][ 'Total Width' ], ' ') 
            );
            $memory[ 'modules' ][ $id ] = array(
            'size' => $dmidata[ 'data' ][ 'Size' ],
            'type' => $dmidata[ 'data' ][ 'Form Factor' ],
            'class' => $dmidata[ 'data' ][ 'Type' ],
            'location' => $dmidata[ 'data' ][ 'Locator' ],
            'data_width' => $data_width,
            'total_width' => $total_width,
            'ecc' => ( $total_width > $data_width ),
            'rank' => $dmidata[ 'data' ][ 'Rank' ],
            'speed' => $dmidata[ 'data' ][ 'Configured Clock Speed' ],
            'speed_max' => $dmidata[ 'data' ][ 'Speed' ],
            'speed_ismax' =>
            ( $dmidata[ 'data' ][ 'Configured Clock Speed' ] == $dmidata[ 'data' ][ 'Speed' ] ),
            'partnumber' => $dmidata[ 'data' ][ 'Part Number' ],
            'errors' => $dmidata[ 'data' ][ 'Error Information Handle' ]
            );
        }
    }
    $memory[ 'count' ] = count($memory[ 'modules' ]);

    // extra
    $extra = array();

    // return final data
    return array(
    'BIOS' => $bios,
    'Motherboard' => $motherboard,
    'Processor' => $processor,
    'Memory' => $memory,
    'Extra' => $extra
    );
}

function dmi_types() 
{
    return array(
    0 => 'BIOS',
    1 => 'System',
    2 => 'Base Board',
    3 => 'Chassis',
    4 => 'Processor',
    5 => 'Memory Controller',
    6 => 'Memory Module',
    7 => 'Cache',
    8 => 'Port Connector',
    9 => 'System Slots',
    10 => 'On Board Devices',
    11 => 'OEM Strings',
    12 => 'System Configuration Options',
    13 => 'BIOS Language',
    14 => 'Group Associations',
    15 => 'System Event Log',
    16 => 'Physical Memory Array',
    17 => 'Memory Device',
    18 => '32-bit Memory Error',
    19 => 'Memory Array Mapped Address',
    20 => 'Memory Device Mapped Address',
    21 => 'Built-in Pointing Device',
    22 => 'Portable Battery',
    23 => 'System Reset',
    24 => 'Hardware Security',
    25 => 'System Power Controls',
    26 => 'Voltage Probe',
    27 => 'Cooling Device',
    28 => 'Temperature Probe',
    29 => 'Electrical Current Probe',
    30 => 'Out-of-band Remote Access',
    31 => 'Boot Integrity Services',
    32 => 'System Boot',
    33 => '64-bit Memory Error',
    34 => 'Management Device',
    35 => 'Management Device Component',
    36 => 'Management Device Threshold Data',
    37 => 'Memory Channel',
    38 => 'IPMI Device',
    39 => 'Power Supply',
    40 => 'Additional Information',
    41 => 'Onboard Device',
    126 => 'Disabled',
    127 => 'End of table marker'
    );
}
