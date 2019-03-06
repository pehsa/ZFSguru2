<?php

function internal_serviceicon()
// TODO - SECURITY
{
 global $guru;
 $dirs = common_dirs();
 $svcname = str_replace('..', '', $_GET['serviceicon']);
 $iconpath = $dirs['services'].'/'.$svcname.'/service_icon.';
 $extensions = array('bmp', 'gif', 'png', 'jpg', 'jpeg', 'tiff');
 foreach ($extensions as $extension)
  if (file_exists($iconpath.$extension))
   internal_sendimage($iconpath.$extension);
 // default service icon
 $icondefault = $guru['docroot'].'/theme/default/pango/service-default-16.png';
 internal_sendimage($icondefault);
}

function internal_servicebigicon()
// TODO - SECURITY
{
 global $guru;
 $dirs = common_dirs();
 $svcname = str_replace('..', '', $_GET['servicebigicon']);
 $iconpath = $dirs['services'].'/'.$svcname.'/service_bigicon.';
 $extensions = array('bmp', 'gif', 'png', 'jpg', 'jpeg', 'tiff');
 foreach ($extensions as $extension)
  if (file_exists($iconpath.$extension))
   internal_sendimage($iconpath.$extension);
 // default service icon
 $icondefault = $guru['docroot'].'/theme/default/pango/service-default-64.png';
 internal_sendimage($icondefault);
}

function internal_sendimage($imagepath)
{
 // disable ZLIB output compression
 if (ini_get('zlib.output_compression'))
  ini_set('zlib.output_compression', 'Off');
 // check existence
 $realpath = str_replace('..', '', $imagepath);
 if (!file_exists($realpath))
 {
  header('HTTP/1.0 404 Not Found', true, 404);
  header('Status: 404 Not Found');
 }
 // check extension
 $extension = substr($imagepath, strrpos($imagepath, '.')+1);
 if (!$extension)
 {
  header('HTTP/1.0 500 Internal Server Error', true, 500);
  header('Status: 500 Internal Server Error');
  die();
 }
 // cache status
 $cachetime = 3600;
 $expiredate = time() + $cachetime;
 $dateformat = 'D, d M Y H:i:s';
 $mtime = filemtime($realpath);
 $mdate = gmdate($dateformat, $mtime).' GMT';
 $edate = gmdate($dateformat, $expiredate).' GMT';
 // set HTTP headers
 header('Content-Type: image/'.$extension, true);
 header('Content-Length: '.filesize($realpath), true);
 header('Cache-Control: max-age='.$cachetime, true);
 header('Expires: '.$edate, true);
 header('Last-Modified: '.$mdate, true);
 header('Pragma: ',true);
 // check user-supplied If-Modified-Since header
 if (@strlen($_SERVER['HTTP_IF_MODIFIED_SINCE']) > 0)
  if (@$_SERVER['HTTP_IF_MODIFIED_SINCE'] == $mdate)
  {
   header('Status: 304 Not Modified: '.$mdate, true, 304);
   die();
  }
 // flush headers to client
 ob_clean();
 flush();
 // send raw file
 readfile($realpath);
 // stop script execution
 die();
}

