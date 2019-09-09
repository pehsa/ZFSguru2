#!/usr/local/bin/php

<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 * ZFSguru - simple benchmark script
 * version 1
 * (C) 2012, zfsguru.com
 * http://zfsguru.com
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

// default variables
$chartdefault = array(
    'test_seq' => true,
    'test_rio' => false,
    'testsize_gib' => 100,
    'testrounds' => 3,
    'cooldown' => 2,
    'seq_blocksize' => 1024 * 1024,
    'rio_requests' => 5000,
    'rio_scalezvol' => false,
    'rio_alignment' => 4096,
    'rio_queuedepth' => 32,
    'sectorsize_override' => 0,
    'secure_erase' => false );

// command line parameters
$diskname = @$argv[ 1 ];
$mediasize = @$argv[ 2 ];

// internal variables
$benchmark = array();
$dd_preg = '/^[0-9]+ bytes transferred in [0-9\.]+ secs '
    . '\(([0-9]+) bytes\/sec\)$/m';
$rt_preg = '/^Requests per second\: ([0-9]+)$/m';


// determine manual or web-gui test and set variables accordingly
if (true ) {
    // start benchmark initiated via web-interface

    // variables
    $blocksize = 8 * 1024 * 1024;
    $blockcount = 8;
    $totalcount = 100;
    $hundredpercent = floor($mediasize / $blocksize);
    $onepercent = round($hundredpercent / 100);
    $alignment = 4096;

    // scores array
    $scores = array();

    // run benchmarks and store result in $scores array
    for ( $i = 0; $i < 100; $i++ ) {
        // dd read chunk
        $calculatedoffset = $i * $onepercent;
        $alignedoffset = floor($calculatedoffset / $alignment) * $alignment;

        $ddread = 'dd if=/dev/' . $diskname . ' of=/dev/null iseek=' . $alignedoffset
        . ' bs=' . $blocksize . ' count=' . ( int )$blockcount . ' 2>&1';
        $output = array();
        exec($ddread, $output);
        // process result
        if (strpos(@$output[ 2 ], 'bytes transferred in') === false ) {
            break;
            // inactive code
            zfsguru_benchmark_error('benchmarking dd transferred');
        }
        preg_match('/^[0-9]+.*\(([0-9]+) bytes\/sec/', $output[ 2 ], $matches);
        if (!is_numeric(@$matches[ 1 ]) ) {
            zfsguru_benchmark_error('no score detected in dd output');
        } else {
            $score = ( int )$matches[ 1 ];
        }
        $scores[ $i ] = round(( double )$score / ( 1024 * 1024 ), 1);
        // create chart while benchmark is running
        if ($i % 10 == 0 ) {
            zfsguru_benchmark_createchart();
        }
    }
    if (count($scores) > 0 ) {
        zfsguru_benchmark_createchart(true);
    } else {
        zfsguru_benchmark_error('no scores found!');
    }
}


// simple benchmark functions

function zfsguru_benchmark_createchart( $finalscore = false )
{
    global $diskname, $mediasize, $scores;

    // first analyze $scores to determine maxscore
    $maxscore = 0;
    foreach ( $scores as $score ) {
        if ($score > $maxscore ) {
            $maxscore = $score;
        }
    }

    // max score -> score_y_mib
    if ($maxscore > 1000 ) {
        $score_y_mib = 500;
    } elseif ($maxscore > 500 ) {
        $score_y_mib = 200;
    } elseif ($maxscore > 250 ) {
        $score_y_mib = 100;
    } elseif ($maxscore > 200 ) {
        $score_y_mib = 50;
    } elseif ($maxscore > 100 ) {
        $score_y_mib = 20;
    } elseif ($maxscore > 20 ) {
        $score_y_mib = 10;
    } else {
        $score_y_mib = 1;
    }

    // variables
    $chart_name = $diskname;
    $testcount = ( int )count($scores);
    $units_x = 10;
    $units_y = ( int )ceil($maxscore / ( double )$score_y_mib);
    $units_y_mib = $units_y * $score_y_mib;
    $resolution_x = 50;
    $resolution_y = 50;
    $pitch_box = 12;
    $margin = array( 'left' => 10, 'top' => 10, 'right' => 20, 'bottom' => 10 );
    $frame = array();
    $frame[ 'start' ] = array( 'x' => 20, 'y' => '20' );
    $frame[ 'end' ] = array(
    'x' => ( $frame[ 'start' ][ 'x' ] + ( $units_x * $resolution_x ) ),
    'y' => ( $frame[ 'start' ][ 'y' ] + ( $units_y * $resolution_y ) )
    );
    $font = 'files/liberationsans.ttf';
    $width = $frame[ 'end' ][ 'x' ] + $margin[ 'right' ];
    $height = $frame[ 'end' ][ 'y' ] + $margin[ 'bottom' ] + 22;

    // scorepx
    $scorepx = array();
    $maxpx = ( $units_y * $resolution_y );
    $maxpxfactor = 1;
    $scorefactor = 500 / 100;
    foreach ( $scores as $id => $score ) {
        $scorepx[ $id ] = array(
        'x' => ( int )( $frame[ 'start' ][ 'x' ] + ( $id * $scorefactor ) ),
        'y' => ( int )( $frame[ 'start' ][ 'y' ] + ( $maxpx - ( ( $score / $maxscore ) * $maxpx ) ) ),
        'sc' => ( int )$score
        );
    }

    // create image
    $image = imagecreatetruecolor($width, $height);
    // fail if image was not created (memory problems?)
    if (!is_resource($image) ) {
        return false;
    }

    // set colors
    $colors = array(
    'bg' => imagecolorallocate($image, 250, 250, 250),
    'title' => imagecolorallocate($image, 100, 100, 100),
    'txt' => imagecolorallocate($image, 100, 100, 100),
    'frame' => imagecolorallocate($image, 0, 0, 0),
    'databg' => imagecolorallocate($image, 255, 255, 255),
    'running' => imagecolorallocate($image, 255, 0, 0),
    'wm1' => imagecolorallocate($image, 255, 255, 255),
    'wm2' => imagecolorallocate($image, 220, 220, 220),
    'grid' => imagecolorallocate($image, 220, 220, 220),
    'RAID0' => imagecolorallocate($image, 233, 14, 91),
    'RAID1' => imagecolorallocate($image, 114, 114, 120),
    'RAID1+0' => imagecolorallocate($image, 233, 214, 91),
    'RAIDZ' => imagecolorallocate($image, 14, 14, 120),
    'RAIDZ2' => imagecolorallocate($image, 133, 55, 171),
    'RAIDZ+0' => imagecolorallocate($image, 100, 255, 171),
    'RAIDZ2+0' => imagecolorallocate($image, 20, 255, 171)
    );

    // begin working on image
    @imageantialias($image, true);
    imagefill($image, 0, 0, $colors[ 'bg' ]);

    // draw border
    imagerectangle($image, 0, 0, $width - 1, $height - 1, $colors[ 'txt' ]);

    // draw watermark
    $watermark = 'ZFSguru';
    imagettftext(
        $image, 7, 0, $width - 41, 11, $colors[ 'wm1' ],
        $font, $watermark 
    );
    imagettftext(
        $image, 7, 0, $width - 40, 10, $colors[ 'wm2' ],
        $font, $watermark 
    );

    // write chart name
    imagettftext($image, 11, 0, 20, 15, $colors[ 'title' ], $font, $chart_name);

    // draw grid
    for ( $i = $frame[ 'start' ][ 'x' ]; $i <= $frame[ 'end' ][ 'x' ]; $i += $resolution_x ) {
        imageline(
            $image, $i, $frame[ 'start' ][ 'y' ] + 1, $i,
            $frame[ 'end' ][ 'y' ] - 1, $colors[ 'grid' ] 
        );
    }
    for ( $i = $frame[ 'start' ][ 'y' ]; $i <= $frame[ 'end' ][ 'y' ]; $i += $resolution_y ) {
        imageline(
            $image, $frame[ 'start' ][ 'x' ] + 1, $i, $frame[ 'end' ][ 'x' ] - 1,
            $i, $colors[ 'grid' ] 
        );
    }
    imageline(
        $image, $frame[ 'start' ][ 'x' ], $frame[ 'start' ][ 'y' ],
        $frame[ 'start' ][ 'x' ], $frame[ 'end' ][ 'y' ], $colors[ 'frame' ] 
    );
    imageline(
        $image, $frame[ 'start' ][ 'x' ], $frame[ 'end' ][ 'y' ],
        $frame[ 'end' ][ 'x' ], $frame[ 'end' ][ 'y' ], $colors[ 'frame' ] 
    );

    // draw horizontal units
    $increment = 1;
    for ( $i = 1; $i <= $units_x; $i = $i + $increment ) {
        imagettftext(
            $image, 9, 0, $frame[ 'start' ][ 'x' ] - 3 + ( $i * $resolution_x ),
            $frame[ 'end' ][ 'y' ] + 15, $colors[ 'txt' ], $font, $i 
        );
    }

    // draw vertical units
    $start = array( 'x' => 2, 'y' => 305 );
    $step = array( 'x' => 0, 'y' => ( -1 * $resolution_y ) );
    $increment = 1;
    for ( $i = 0; $i <= $units_y; $i = $i + $increment ) {
        imagettftext(
            $image, 7, 0, 2,
            $frame[ 'end' ][ 'y' ] + 4 + ( $i * $step[ 'y' ] ), $colors[ 'txt' ], $font,
            $i * $score_y_mib 
        );
    }

    // add "benchmark running" watermark if applicable
    if (!$finalscore ) {
        imagettftext(
            $image, 7, 0, $frame[ 'start' ][ 'x' ] + 5, $frame[ 'end' ][ 'y' ] - 6,
            $colors[ 'running' ], $font, 'Benchmark still running' 
        );
    }

    // process benchmark data
    $startoffset = $frame[ 'start' ][ 'x' ] + $resolution_x;
    $pitch = $resolution_x;
    foreach ( $scorepx as $id => $pixel ) {
        // next score
        $nextscore = @$scorepx[ $id + 1 ];
        if (!$nextscore ) {
            continue;
        }
        // lines
        $x1 = $pixel[ 'x' ];
        $x2 = $nextscore[ 'x' ];
        $y1 = $pixel[ 'y' ];
        $y2 = $nextscore[ 'y' ];
        // draw two lines
        imageline($image, $x1, $y1, $x2, $y2, $colors[ 'RAID0' ]);
        imageline($image, $x1 + 1, $y1, $x2 + 1, $y2, $colors[ 'RAID0' ]);
    }

    // write png file to disk
    $filename = trim(`realpath .`) . '/benchmarks/simplebench_' . $diskname . '.png';
    imagepng($image, $filename);
    @imagedestroy($image);
}

// helper functions

function zfsguru_benchmark_error( $tag, $rv = -1 )
{
    global $poolname;
    echo( chr(10) );
    echo( '* ERROR during "' . $tag . '"; got return value ' . $rv );
    echo( chr(10) );
    usleep(1000);
    exit($rv);
}
