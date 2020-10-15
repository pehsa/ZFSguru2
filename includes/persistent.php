<?php

/**
 * @param false $section
 *
 * @return array|false|mixed
 */
function persistent_read( $section = false )
{
    global $guru;
    // read serialized data from file
    $filename = $guru[ 'docroot' ] . 'config/persistent.dat';
    $contents = @file_get_contents($filename);
    $arr = @unserialize($contents);
    if (!is_array($arr)) {
        return false;
    }

    if ($section == false) {
        return $arr;
    }

    if (@!isset($arr[ $section ])) {
        return false;
    }

    return $arr[ $section ];
}

/**
 * @param $arr
 *
 * @return bool|int
 */
function persistent_write( $arr )
{
    global $guru;
    // write serialized array to file
    $filename = $guru[ 'docroot' ] . 'config/persistent.dat';
    if (is_array($arr)AND empty($arr) ) {
        return @unlink($filename);
    }

    $ser = serialize($arr);

    return file_put_contents($filename, $ser);
}

/**
 * @param $sectionname
 * @param $data
 *
 * @return bool|int
 */
function persistent_store( $sectionname, $data )
{
    // read data
    $arr = persistent_read();
    // add new section
    $arr[ $sectionname ] = $data;
    // write data
    return persistent_write($arr);
}

/**
 * @param false $sectionname
 *
 * @return bool|int
 */
function persistent_remove( $sectionname = false )
{
    // read data
    $arr = persistent_read();
    if (!is_array($arr) ) {
        $arr = [];
    }
    // remove section
    if (@isset($arr[ $sectionname ]) ) {
        unset($arr[ $sectionname ]);
    }
    // write data
    return persistent_write($arr);
}
