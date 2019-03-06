<?php

// import main lib
require('includes/main.php');

// navtabs
$tabs = array(
 'Formatting' => 'disks.php',
 'Memory disks' => 'disks.php?mem',
 'SMART' => 'disks.php?smart',
 'Advanced' => 'disks.php?advanced',
 'I/O monitor' => 'disks.php?monitor',
 'Benchmark' => 'disks.php?benchmark');
if (@$guru['preferences']['advanced_mode'] !== true)
 unset($tabs['Memory disks'], $tabs['Benchmark']);

// select page
if (isset($_GET['smart']))
 $content = content_handle('disks', 'smart');
elseif (isset($_GET['advanced']))
 $content = content_handle('disks', 'advanced');
elseif (isset($_GET['monitor']))
 $content = content_handle('disks', 'monitor');
elseif (isset($_GET['benchmark']))
 $content = content_handle('disks', 'benchmark');
elseif (@isset($_GET['query']))
 $content = content_handle('disks', 'query');
elseif (@isset($_GET['mem']))
 $content = content_handle('disks', 'memory');
else
 $content = content_handle('disks', 'disks');

// serve page
page_handle($content);

