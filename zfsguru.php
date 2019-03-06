<?php

// import main lib
require('includes/main.php');

// navtabs
$tabs = array(
 'About'		=> 'zfsguru.php',
 'Bulletin'		=> 'zfsguru.php?bulletin',
 'Future'		=> 'zfsguru.php?future',
);

// select page
if (@isset($_GET['bulletin']))
 $content = content_handle('zfsguru', 'bulletin');
elseif (@isset($_GET['future']))
 $content = content_handle('zfsguru', 'future');
else
 $content = content_handle('zfsguru', 'about');

// serve page
page_handle($content);

