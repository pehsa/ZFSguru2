<?php

/* page.php */

// start tags
$tags = [
    'PAGE_TITLE' => @$guru[ 'product_name' ],
    'PRODUCT_NAME' => @$guru[ 'product_name' ],
    'PRODUCT_VERSION_STRING' => @$guru[ 'product_version_string' ],
    'PRODUCT_URL' => @$guru[ 'product_url' ],
];


// functions

/**
 * @param $content
 */
function page_handle( $content )
{
    global $guru, $tags;
    timerstart('page');
    // set visual theme
    $theme = page_themepath();
    // push content in tags array
    if (is_array($content) ) {
        foreach ( $content as $newtag => $value ) {
            $tags[ $newtag ] = $value;
        } 
    } else {
        $tags[ 'CONTENT' ] = $content;
    }
    // grab layout
    $layout = content_handle_path(
        'theme/' . $theme . '/layout', 'layout', 'layout',
        false, true 
    );
    // process tags
    $processed = page_processtags($layout);
    // output page as processed string
    echo( $processed );
}

/**
 * @param $source
 *
 * @return string|string[]|null
 */
function page_processtags( $source )
{
    // get all tags
    $preg_tag = '/[^\%]?\%\%([^\%]+)\%\%[^\%]?/m';
    preg_match_all($preg_tag, $source, $rawtags);
    $requestedtags = @$rawtags[ 1 ];
    unset($rawtags);

    // process table tags
    foreach ( $requestedtags as $id => $tag ) {
        if ((strncmp($tag, 'TABLE_', 6) === 0) && substr($tag, -4) !== '_END') {
            $startpos = strpos($source, $tag);
            $endpos = strpos($source, $tag);
            $preg_table = '/\%\%(' . $tag . ')\%\%(.*)\%\%(' . $tag . '_END)\%\%/Usm';
            $source = preg_replace_callback(
                $preg_table, 'page_callback_tableprocessor',
                $source
            );
            unset($requestedtags[ $id ]);
            $endid = @array_search($tag.'_END', $requestedtags, true);
            if (@is_int($endid) ) {
                unset($requestedtags[ $endid ]);
            }
        }
    }

    // get all tags again (the table tags stripped away this time)
    preg_match_all($preg_tag, $source, $rawtags);
    $requestedtags = @array_unique($rawtags[ 1 ]);
    unset($rawtags);

    // page debug if ?pagedebug is appended to URL
    if (@isset($_GET[ 'pagedebug' ]) ) {
        viewarray($requestedtags);
    }

    // start output string by copying the original
    $processed = $source;
    // resolve and substitute all the tags
    foreach ( $requestedtags as $tag ) {
        // call function to get the contents of the tag
        $resolved = page_resolvetag($tag);
        // now replace the tag with the contents instead
        if (is_array($resolved) ) {
            page_feedback(
                'internal error: could not process tag "' . $tag . '" because '
                . 'it contains an array' 
            );
        }
        // replace tags with new contents
        $processed = @str_replace('%%' . $tag . '%%', $resolved, $processed);
    }
    return $processed;
}

/**
 * @param $newtags
 */
function page_injecttag( $newtags )
{
    global $tags;
    if (is_array($newtags) ) {
        foreach ( $newtags as $tagname => $tagvalue ) {
            $tags[ $tagname ] = $tagvalue;
        }
    }
}

/**
 * @param $tag
 *
 * @return mixed|string
 */
function page_resolvetag($tag)
{
    global $tags;
    if (@isset($tags[ $tag ]) ) {
        return $tags[ $tag ];
    }

    return '';
}

/**
 * @param $data
 *
 * @return string
 */
function page_callback_tableprocessor( $data )
{
    global $tags;
    $tabletag = @( string )$data[ 1 ];
    $tablebody = @( string )$data[ 2 ];
    // get all table tags
    $preg_tag = '/[^\%]?\%\%([^\%]+)\%\%[^\%]?/m';
    preg_match_all($preg_tag, $tablebody, $tabletags_raw);
    $tabletags = @array_unique($tabletags_raw[ 1 ]);

    // begin assembling the $output string row by row
    $output = '';
    if (@is_array($tags[ $tabletag ]) ) {
        foreach ( $tags[ $tabletag ] as $id => $rowtags ) {
            // inject rowtags into tags for page_processtags function
            if (is_array($rowtags) ) {
                foreach ( $rowtags as $tag => $value ) {
                    $tags[ $tag ] = $value;
                }
            }
            // append processed row to output string
            $output .= page_processtags($tablebody);
        }
    }
    return $output;
}

/**
 * @param $filepath
 */
function page_rawfile( $filepath )
{
    global $guru;
    // set themepath
    page_themepath();
    // parse content
    $rawcontents = file_get_contents($guru[ 'docroot' ] . $filepath);
    // output processed content (not using layout page handler)
    $processed = page_processtags($rawcontents);
    echo( $processed );
}

/**
 * @return mixed|string
 */
function page_themepath()
{
    global $guru, $tags;
    // set theme
    $theme = ( @$guru[ 'preferences' ][ 'theme' ] ) ?
    $guru[ 'preferences' ][ 'theme' ] : 'default';
    // set themepath
    for ( $i = 0; $i <= 3; $i++ ) {
        $deeper = '';
        for ( $y = 0; $y < $i; $y++ ) {
            $deeper .= '../';
        }
        $clientpath = $deeper . '/theme/' . $theme;
        $serverpath = realpath(dirname($_SERVER[ 'SCRIPT_FILENAME' ]) . '/' . $deeper . '/theme/' . $theme);
        if (is_dir($serverpath) ) {
            $tags[ 'THEMEPATH' ] = $clientpath;
            break;
        }
        if ($i == 3 ) {
            $tags[ 'THEMEPATH' ] = '/theme/' . $theme;
        }
    }
    return $theme;
}

/* content handler */

/**
 * @param       $cat
 * @param       $pagename
 * @param false $data
 * @param false $skip_submit
 *
 * @return string|string[]|null
 */
function content_handle( $cat, $pagename, $data = false, $skip_submit = false )
{
    timerstart('content');
    $pagepath = 'pages/' . $cat . '/' . $pagename;
    $c = content_handle_path($pagepath, $cat, $pagename, $data, $skip_submit);
    timerend('content');
    return $c;
}

/**
 * @param       $pagepath
 * @param       $cat
 * @param       $pagename
 * @param false $data
 * @param false $skip_submit
 *
 * @return string|string[]|null
 */
function content_handle_path( $pagepath, $cat, $pagename,
    $data = false, $skip_submit = false 
) {
    // processes specific page into content string
    // TODO: SECURITY
    // TODO: translation page select
    global $guru, $tags;

    // set visual theme
    $theme = page_themepath();

    // working in docroot or not?
    $prefix = ( $pagepath {
    0
    } === '/' ) ? '' : $guru[ 'docroot' ];

    // read page file
    $page = @file_get_contents($prefix . $pagepath . '.page');

    // process content file
    $contentfile = $prefix . $pagepath . '.php';
    $contenttags = [];
    if (@is_readable($contentfile) ) {
        // include contentpage (.php)
        include_once $contentfile;
    }

    // call submit function if applicable
    if (@isset($_POST['handle']) && @function_exists('submit_'.$_POST['handle']) && !$skip_submit) {
        if ($data !== false) {
            $submittags = call_user_func('submit_' . $_POST[ 'handle' ], $data);
        } else {
            $submittags = call_user_func('submit_' . $_POST[ 'handle' ]);
        }
    }
                // inject all content tags into main $tags array
    if (@is_array($submittags) ) {
        foreach ( $submittags as $newtag => $value ) {
            $tags[ $newtag ] = $value;
        }
    }

    // call page function if applicable
    $pagefunction = 'content_' . $cat . '_' . $pagename;
    if (@function_exists($pagefunction) ) {
        if ($data ) {
            $contenttags = $pagefunction($data);
        } else {
            $contenttags = $pagefunction();
        }
    }

    // inject all content tags into main $tags array
    if (@is_array($contenttags) ) {
        foreach ( $contenttags as $newtag => $value ) {
            $tags[ $newtag ] = $value;
        }
    }

    // process tabbar tags
    $tabbar_tag = false;
    if (@is_array($contenttags[ 'PAGE_TABBAR' ]) ) {
        foreach ( $contenttags[ 'PAGE_TABBAR' ] as $tbtag => $tbname ) {
            if (@isset($_GET[ $tbtag ]) ) {
                $tabbar_tag = true;
                $tags[ 'TAB_' . strtoupper($tbtag) ] = 'normal';
            } else {
                $tags[ 'TAB_' . strtoupper($tbtag) ] = 'hidden';
            }
        }
        if (!$tabbar_tag ) {
            $tags[ 'TAB_' . strtoupper(key($contenttags[ 'PAGE_TABBAR' ])) ] = 'normal';
        }
    }

    // process stylesheet if existent
    $stylepath = $pagepath . '.css';
    if (@is_readable($stylepath) ) {
        if (strncmp($stylepath, '/', 1) === 0
        ) {
            // use inline CSS for absolute pathnames
            $css_code = @file_get_contents($stylepath);
            page_register_inlinestyle($css_code);
        } else {
            page_register_stylesheet($stylepath);
        }
    }

    // process tags
    // output processed string handled by page handler as %%CONTENT%% tag
    return page_processtags($page);
}


/* head and body handlers */

/**
 * @param $element_raw
 */
function page_register_headelement( $element_raw )
{
    global $tags;
    $newtag = @$tags[ 'HEAD' ] . ' ' . $element_raw . chr(10);
    $tags[ 'HEAD' ] = $newtag;
}

/**
 * @param $element_raw
 */
function page_register_bodyelement( $element_raw )
{
    global $tags;
    $newtag = @$tags[ 'BODY' ] . ' ' . $element_raw;
    $tags[ 'BODY' ] = $newtag;
}

/**
 * @param $relative_path
 */
function page_register_stylesheet( $relative_path )
{
    $str = '<link rel="stylesheet" type="text/css" href="' . $relative_path . '" />';
    page_register_headelement($str);
}

/**
 * @param $css_code
 */
function page_register_inlinestyle( $css_code )
{
    $str = '<style type="text/css">' . chr(10);
    $str .= $css_code;
    $str .= chr(10) . '</style>' . chr(10);
    page_register_headelement($str);
}

/**
 * @param $js_file
 */
function page_register_javascript( $js_file )
{
    $str = '<script type="text/javascript" src="'
    . htmlentities($js_file) . '"></script>';
    page_register_headelement($str);
}

/**
 * @param        $feedback
 * @param string $style
 */
function page_feedback( $feedback, $style = 'c_notice' )
{
    if (!@in_array($_SESSION['feedback'][$style], $feedback, true)) {
        $_SESSION[ 'feedback' ][ $style ][ $feedback ] = $feedback;
    }
}

/**
 * @param       $seconds
 * @param false $url
 */
function page_refreshinterval( $seconds, $url = false )
{
    if (( int )$seconds <= 0 ) {
        error('cannot refresh page with interval <= 0');
    }
    $newelement = '<meta http-equiv="refresh" content="' . ( int )$seconds;
    if ($url ) {
        $newelement .= '; url=' . htmlentities($url);
    }
    page_register_headelement($newelement . '" />');
}
