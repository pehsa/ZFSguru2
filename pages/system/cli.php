<?php

function content_system_cli() 
{
    global $tags;

    // hostname
    $hostname = trim(shell_exec("hostname"));

    // visible classes
    $class_output = ( @isset($tags[ 'CLASS_OUTPUT' ]) ) ?
    $tags[ 'CLASS_OUTPUT' ] : 'hidden';
    $class_rv = ( @isset($tags[ 'CLASS_RV' ]) ) ?
    $tags[ 'CLASS_RV' ] : 'hidden';
    $class_nooutput = ( @isset($tags[ 'CLASS_NOOUTPUT' ]) ) ?
    $tags[ 'CLASS_NOOUTPUT' ] : 'hidden';

    // export new tags
    $newtags = array(
    'PAGE_ACTIVETAB' => 'Command line',
    'PAGE_TITLE' => 'Command line',
    'CLI_HOSTNAME' => $hostname,
    'CLASS_OUTPUT' => @$class_output,
    'CLASS_RV' => @$class_rv,
    'CLASS_NOOUTPUT' => @$class_nooutput
    );
    return $newtags;
}

function submit_cli_execute() 
{
    // sanity
    $url = 'system.php?cli';
    $command = @$_POST[ 'cli_command' ];
    if (@strlen($command) < 1 ) {
        friendlyerror('you forgot to enter a command to execute!', $url);
    }

    // security: make sure the user submitted form on correct URL
    $referer = $_SERVER[ 'HTTP_REFERER' ];
    $servername = $_SERVER[ 'SERVER_NAME' ];
    $serverport = $_SERVER[ 'SERVER_PORT' ];
    $expected_prefix = ( $serverport == 443 ) ?
    'https://' . $servername : 'http://' . $servername;
    $actual_prefix = substr($referer, 0, strpos($referer, '/', 8));
    $expected_suffix = '/system.php?cli';
    $actual_suffix = substr($referer, strrpos($referer, '/'));
    if (strpos($actual_suffix, '&') !== false ) {
        $actual_suffix = substr($actual_suffix, 0, strpos($actual_suffix, '&'));
    }
    if ($expected_prefix != $actual_prefix OR $expected_suffix != $actual_suffix ) {
        friendlyerror(
            $expected_suffix . 'Z ' . $actual_suffix
            . 'a security exception occured; you did not submit the form '
            . 'using conventional means. Supplied command not executed.', $url 
        );
    }

    // hostname
    $hostname = trim(shell_exec("hostname"));

    // check for root or normal execution
    if (@$_POST[ 'cli_root' ] === 'on' ) {
        activate_library('super');
        $result = super_execute($command);
        $rv = $result[ 'rv' ];
        $output = '<b>' . $hostname . '$</b> <i>' . htmlentities($command) . '</i>'
        . chr(10) . $result[ 'output_str' ];
        $class_output = 'normal';
        $class_rv = ( $rv == 0 ) ? 'hidden' : 'normal';
        $class_nooutput = ( @empty($result[ 'output_arr' ])AND $rv == 0 ) ? 'normal' : 'hidden';
        $cliroot = 'checked="checked"';
    } else {
        exec($command, $output_arr, $rv);
        $output = '<b>' . $hostname . '$</b> <i>' . htmlentities($command) . '</i>'
        . chr(10) . @implode(chr(10), $output_arr);
        $class_output = 'normal';
        $class_rv = ( $rv == 0 ) ? 'hidden' : 'normal';
        $class_nooutput = ( @empty($output_arr)AND $rv == 0 ) ? 'normal' : 'hidden';
        $cliroot = '';
    }

    // export as tags
    $newtags = array(
    'CLI_COMMAND' => $command,
    'CLI_OUTPUT' => @$output,
    'CLI_RV' => @$rv,
    'CLASS_OUTPUT' => @$class_output,
    'CLASS_RV' => @$class_rv,
    'CLASS_NOOUTPUT' => @$class_nooutput,
    'CHECKED_CLIROOT' => @$cliroot
    );
    return $newtags;
}
