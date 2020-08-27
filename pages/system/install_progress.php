<?php

function content_system_install_progress() 
{
    // required library
    activate_library('zfsguru');

    // fetch installation progress
    $installation = zfsguru_install_progress($activetask, $installtasks);

    // if no installation, redirect
    if ($installation === null ) {
        friendlyerror('no installation is active at this time', 'system.php?install');
    }

    // automatic refresh during installation
    $refresh_sec = 2;
    if ($activetask ) {
        page_refreshinterval($refresh_sec);
    }

    // table: progress
    $table_progress = table_progress($installtasks);

    // classes
    $class_success = ( $installation AND!$activetask ) ? 'normal' : 'hidden';
    $class_failure = ( !$installation ) ? 'normal' : 'hidden';
    $class_installing = ( $activetask ) ? 'normal' : 'hidden';
    $class_progress = ( !$installation OR $activetask ) ? 'normal' : 'hidden';

    // export new tags
    return array(
    'PAGE_ACTIVETAB' => 'Install',
    'PAGE_TITLE' => 'Installing ZFSguru',
    'TABLE_PROGRESS' => $table_progress,
    'CLASS_SUCCESS' => $class_success,
    'CLASS_FAILURE' => $class_failure,
    'CLASS_INSTALLING' => $class_installing,
    'CLASS_PROGRESS' => $class_progress,
    'INSTALL_ACTIVETASK' => htmlentities($activetask),
    );
}

function table_progress( $installtasks )
{
    $table = array();
    $lastitem = false;
    foreach ( $installtasks as $tagname => $subtasks ) {
        $firsttask = reset($subtasks);
        // traverse all subtasks to determine status
        $tasks = '';
        foreach ( $subtasks as $number => $task ) {
            if (!is_numeric($task[ 'rv' ]) ) {
                if ($lastitem ) {
                    $status = 'queued';
                } else {
                    $status = 'active';
                    $lastitem = true;
                    break;
                }
            } elseif ($task[ 'rv' ] != 0 ) {
                $status = 'failed';
                $lastitem = true;
                // note: we break, so $task will be the task that failed
                break;
            }
            else {
                $status = 'done';
            }
        }
        $table[] = array(
        'CLASS_DONE' => ( $status === 'done' ) ? 'normal' : 'hidden',
        'CLASS_ACTIVE' => ( $status === 'active' ) ? 'normal' : 'hidden',
        'CLASS_FAILED' => ( $status === 'failed' ) ? 'normal' : 'hidden',
        'CLASS_QUEUED' => ( $status === 'queued' ) ? 'normal' : 'hidden',
        'CLASS_DEBUG' => ( $status === 'failed' ) ? 'normal' : 'hidden',
        'PROG_NAME' => htmlentities($firsttask[ 'name' ]),
        'PROG_TASKS' => $tasks,
        'PROG_RV' => htmlentities($task[ 'rv' ]),
        'PROG_COMMAND' => htmlentities($task[ 'command' ]),
        'PROG_OUTPUT' => htmlentities($task[ 'output' ]),
        );
    }
    return $table;
}
