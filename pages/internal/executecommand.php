<?php

function submit_executecommand() 
{
    // elevated privileges
    activate_library('super');

    // craft command array from POST data
    $command_arr = array();
    foreach ( $_POST as $name => $value ) {
        if ((strpos($name, 'zfs_command_') === 0) && $value != '') {
            $command_arr[] = $value;
        }
    }

    // execute commands
    $results = array();
    foreach ( $command_arr as $id => $command ) {
        $results[ $id ] = super_execute($command);
    }

    // check results
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
        page_feedback($count . ' executed', 'b_success');
    }

    $url = ( @$_POST[ 'redirect_url' ] ) ? $_POST[ 'redirect_url' ] : 'status.php';
    redirect_url($url);
}
