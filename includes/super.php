<?php

/* privileged super user functions */

/**
 * @param       $command
 * @param false $raw_output
 * @param bool  $redirect_output
 *
 * @return array
 */
function super_execute( $command, $raw_output = false, $redirect_output = true )
{
    // sanity
    if (!is_string($command) ) {
        error('super_execute only accepts command strings');
    }

    if ($raw_output === false ) {
        if ($redirect_output ) {
            exec('/usr/local/bin/sudo ' . $command . ' 2>&1', $result, $rv);
        } else {
            exec('/usr/local/bin/sudo ' . $command, $result, $rv);
        }

        $result_str = implode(chr(10), $result);
        return [
        'rv' => $rv,
        'output_arr' => $result,
        'output_str' => $result_str
        ];
    }

    // raw output
    if ($redirect_output ) {
        system('/usr/local/bin/sudo ' . $command . ' 2>&1', $rv);
    } else {
        system('/usr/local/bin/sudo ' . $command, $rv);
    }

    return $rv;
}

/**
 * @param        $script_name
 * @param string $parameters
 *
 * @return array
 */
function super_script( $script_name, $parameters = '' )
{
    global $guru;
    if (@strlen($script_name) < 1 ) {
        error('HARD ERROR: no script name!');
    }
    $command = '/scripts/' . $script_name . '.sh ' . $parameters;

    return super_execute($guru[ 'docroot' ] . $command);
}
