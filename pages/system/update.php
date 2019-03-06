<?php

function content_system_update()
{
 global $guru;

 // required libraries
 activate_library('gurudb');

 // current version
 $currentversion = $guru['product_version_string'];
// $currentversion = '0.2.0-beta12';

 // call functions
 $interfaces = gurudb_interface();

 // table: webiversions
 $table_webiversions = table_webiversions($interfaces, $currentversion, 
  $guru['iso_date_format']);

 // easy update version
 $euversion = easyupdateversion($interfaces, $currentversion);

 // classes
 $class_eu_noupdate = ($euversion == $currentversion) ? 'normal' : 'hidden';
 $class_eu_update = (is_string($euversion) AND ($euversion != $currentversion)) 
  ? 'normal' : 'hidden';
 $class_eu_unknown = ($euversion == false) ? 'normal' : 'hidden';

 // craft new tags
 $newtags = array(
  'PAGE_ACTIVETAB'	=> 'Update',
  'PAGE_TITLE'		=> 'Update',
  'TABLE_WEBIVERSIONS'	=> $table_webiversions,
  'CLASS_EU_NOUPDATE'	=> $class_eu_noupdate,
  'CLASS_EU_UPDATE'	=> $class_eu_update,
  'CLASS_EU_UNKNOWN'	=> $class_eu_unknown,
  'CLASS_NOWEBI'	=> (empty($table_webiversions)) ? 'normal' : 'hidden',
  'CURRENT_VERSION'	=> $guru['product_version_string'],
  'EU_VERSION'		=> htmlentities($euversion),
  'EU_B64'		=> htmlentities(base64_encode($euversion)),
 );
 return $newtags;
}

function sort_webinterfaces($a, $b)
{
 if ($a['date'] > $b['date'])
  return -1;
 else
  return 1;
}

function table_webiversions($interfaces, $currentversion, $dateformat)
{
 $table = array();
 uasort($interfaces, 'sort_webinterfaces');
 $interfaces = array_slice($interfaces, 0, 10, true);
 foreach ($interfaces as $ifversion => $interface)
  $table[] = array(
   'CLASS_WEBI'		=> ($ifversion == $currentversion) ? 'activerow' : 'normal',
   'CLASS_BRANCH'	=> ($interface['branch'] == 'stable') ? 'green' : 'red',
   'WEBI_VERSION'	=> htmlentities($ifversion),
   'WEBI_B64'		=> htmlentities(base64_encode($ifversion)),
   'WEBI_BRANCH'	=> htmlentities($interface['branch']),
   'WEBI_DATE'		=> date($dateformat, $interface['date']),
  );
 return $table;
}

function easyupdateversion($interfaces, $currentversion)
{
 // detect currently running branch and upgrade obsolete to stable branch
 $branch = false;
 foreach ($interfaces as $ifversion => $interface)
  if ($ifversion == $currentversion)
   $branch = $interface['branch'];
 if (!$branch)
  return false;
 if ($branch == 'obsolete')
  $branch = 'stable';
 // now return latest version from the selected branch
 $latestdate = 0;
 foreach ($interfaces as $ifversion => $interface)
  if ($interface['branch'] == $branch)
   if ($interface['date'] > $latestdate)
   {
    $latestdate = $interface['date'];
    $euversion = $ifversion;
   }
 return $euversion;
}

function submit_system_update()
{
 // required library
 activate_library('gurudb');
 activate_library('server');
 activate_library('zfsguru');

 // redirect URL
 $url = 'system.php?update';

 // grab list of interfaces from GuruDB
 $interfaces = gurudb_interface();

 // update webGUI by server download
 foreach ($_POST as $name => $value)
  if (substr($name, 0, strlen('updatewebi_')) == 'updatewebi_')
  {
   // search for update version (button the user clicked)
   $version = @base64_decode(substr($name, strlen('updatewebi_')));
   if (!@isset($interfaces[$version]['filename']))
    friendlyerror('no web interface version found in GuruDB: "'.$version.'"', 
     $url);

   // download the file from any server
   $downloadedfile = server_download(
    server_uri('interface', $interfaces[$version]['filename']), 
    $interfaces[$version]['filesize'], $interfaces[$version]['sha512']);

   // sanity
   if ($downloadedfile === false)
    friendlyerror('could not download web-interface tarball!', $url);

   // update web-interface
   zfsguru_update_webinterface($downloadedfile);
  }

 // update webGUI by HTTP file upload
 $expectedmime = 'application/x-7z-compressed';
 if (@isset($_POST['submit_import_webgui']))
  if (@isset($_FILES['import_webgui']))
  {
   if ($_FILES['import_webgui']['error'] != 0)
    error('HTTP upload of web-interface resulted in error code '
     .$_FILES['import_webgui']['error']);
   if ($_FILES['import_webgui']['type'] != $expectedmime)
    error('HTTP uploaded file has wrong MIME type "'
     .htmlentities($_FILES['import_webgui']['type'])
     .'" instead of expected "'.$expectedmime.'"');
   zfsguru_update_webinterface($_FILES['tmp_name']);
  }

   // redirect
 redirect_url($url);
}

