<?php

function content_services_iscsi()
{
 // required library
 activate_library('internalservice');
 activate_library('iscsi');

 // include stylesheet from filesystems page
// page_register_stylesheet('pages/files/filesystems.css');

 // call functions
 $ctldautostart = internalservice_queryautostart('ctld');
 $ctldrunning = internalservice_querystart('ctld');
 $iscsi = iscsi_readconfig();

 // queried iSCSI share
 $queryfs = (@strlen($_GET['query']) > 0) ? $_GET['query'] : false;
 if ($queryfs)
 {
 }

 // zfs filesystem list (for creating new iSCSI share)
 if (!$queryfs)
 {
 }

 // classes
 $class_notrunning = (!$ctldrunning) ? 'normal' : 'hidden';
 $class_noautostart = (!$ctldautostart) ? 'normal' : 'hidden';
 $class_noshares = (count($table_iscsi_sharelist) == 0) ? 'normal' : 'hidden';
 $class_query = ($queryfs) ? 'normal' : 'hidden';
 $class_noquery = (!$queryfs) ? 'normal' : 'hidden';

 // export new tags
 return @array(
  'PAGE_TITLE'		=> 'iSCSI',
  'PAGE_ACTIVETAB'	=> 'iSCSI',
  'CLASS_NOTRUNNING'	=> $class_notrunning,
  'CLASS_NOAUTOSTART'	=> $class_noautostart,
  'CLASS_NOSHARES'	=> $class_noshares,
  'CLASS_QUERY'		=> $class_query,
  'CLASS_NOQUERY'	=> $class_noquery,
  'NFS_ZFSFSLIST_HIDE'	=> $nfs_zfsfslist_hide,
  'NFS_ZFSFSLIST_NOHIDE'=> $nfs_zfsfslist_nohide,
  'QUERY_FSNAME'	=> $queryfs,
  'QUERY_MP'		=> $query_mp,
  'QUERY_SHARENFS'	=> $query_sharenfs,
  'QUERY_SHOWMOUNT'	=> $query_showmount
 );
}

