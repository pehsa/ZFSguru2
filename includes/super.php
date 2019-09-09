<?php

/* privileged super user functions */

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
        return array(
        'rv' => $rv,
        'output_arr' => $result,
        'output_str' => $result_str
        );
    } else {
        // raw output
        if ($redirect_output ) {
            system('/usr/local/bin/sudo ' . $command . ' 2>&1', $rv);
        } else {
            system('/usr/local/bin/sudo ' . $command, $rv);
        }
        return $rv;
    }
}

function super_script( $script_name, $parameters = '' )
{
    global $guru;
    if (@strlen($script_name) < 1 ) {
        error('HARD ERROR: no script name!');
    }
    $command = '/scripts/' . $script_name . '.sh ' . $parameters;
    $result = super_execute($guru[ 'docroot' ] . $command);
    return $result;
}
