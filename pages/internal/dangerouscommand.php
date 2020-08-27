<?php

function content_internal_dangerouscommand( $data ) 
{
    global $guru;

    // command array
    if (@is_string($data[ 'commands' ]) ) {
        $command_arr = array( $data[ 'commands' ] );
    } elseif (@is_array($data[ 'commands' ]) ) {
        $command_arr = $data[ 'commands' ];
    } else {
        $command_arr = array();
    }

    // command string
    $command_str = @implode(chr(10), $command_arr);

    // command list
    $commandlist = array();
    foreach ( $command_arr as $id => $command ) {
        $commandlist[] = array(
        'CMD_ID' => htmlentities($id),
        'CMD' => htmlentities($command)
        );
    }

    // command count
    $commandcount = count($command_arr);

    // redirect URL
    $redirect_url = @$data[ 'redirect_url' ];

    // check preferences for command confirmation setting
    if (@$guru[ 'preferences' ][ 'command_confirm' ] === false ) {
        // command confirmation disabled; execute commands right now
        activate_library('super');
        $results = array();
        foreach ( $command_arr as $id => $command ) {
            $results[ $id ] = super_execute($command);
        }

        // give feedback
        $error = false;
        foreach ( $results as $id => $result ) {
            if ($result[ 'rv' ] != 0 ) {
                $error = true;
                page_feedback(
                    'execution failed for command: '
                    . '<b>' . $command_arr[ $id ] . '</b>', 'a_failure' 
                );
                page_feedback(
                    'command output:<br />'
                    . nl2br(htmlentities($result[ 'output_str' ])), 'c_notice' 
                );
            }
        }

        // set friendly notice if no errors
        $count = ( count($command_arr) == 1 ) ? 'command' :
        count($command_arr) . ' commands';
        if (!$error ) {
            page_feedback($count . ' executed!', 'b_success');
        }

        // finally redirect
        redirect_url($redirect_url);
    }

    // export new tags
    return array(
    'PAGE_TITLE' => 'Dangerous command execution',
    'TABLE_COMMANDLIST' => $commandlist,
    'COMMAND_STR' => $command_str,
    'COMMAND_COUNT' => $commandcount,
    'REDIRECT_URL' => @$data[ 'redirect_url' ]
    );
}
