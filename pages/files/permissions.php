<?php

function content_files_permissions()
{
 // required library
 activate_library('system');
 activate_library('zfs');

 // tabbar
 $tabbar = array(
  'ownership' => 'Ownership',
  'chmod' => 'Advanced'
 );
 $tabbar_url = 'files.php?permissions';
 $tabbar_tab = 'ownership';
 foreach ($tabbar as $tab => $name)
  if (@isset($_GET[$tab]))
   $tabbar_tab = $tab;

 // system users & groups
 $sysusers = system_users();
 $sysgroups = system_groups();

 // hide some users and groups
 $hidesystem = (@isset($_GET['displaysystem'])) ? false : true;
 if ($hidesystem)
 {
  foreach ($sysusers as $name => $data)
   if ($data['userid'] < 1000)
    if ($name != 'root')
     unset($sysusers[$name]);
  foreach ($sysgroups as $name => $data)
   if ($data['groupid'] < 1000)
    if ($name != 'wheel')
     unset($sysgroups[$name]);
 }

 // filesystem properties
 $prop = zfs_filesystem_properties(false, 'mountpoint');

 // filesystem list (only filesystems no volumes or snapshots)
 $fslist = zfs_filesystem_list(false, '-t filesystem');
 if (!is_array($fslist))
  $fslist = array();
 
 // hide system filesystems
 foreach ($fslist as $fsname => $fsdata)
  if (($pos = strpos($fsname, '/')) !== false)
   if (substr($fsname, $pos+1, strlen('zfsguru')) == 'zfsguru')
    unset($fslist[$fsname]);
   elseif (substr($fsname, $pos+1) == 'SWAP001')
    unset($fslist[$fsname]);

 // table ownership
 $table_ownership = array();
 foreach ($fslist as $fsname => $fsdata)
 {
  // retrieve mountpoint and skip this filesystem when no mountpoint is found
  $mountpoint = @$prop[$fsname]['mountpoint']['value'];
  // sanity on mountpoint
  if (!is_dir($mountpoint))
   continue;
  if ($mountpoint{0} != '/')
   continue;
  // acquire other data
  $owner = @$sysusers[fileowner($mountpoint)]['username'];
  $group = @$sysgroups[filegroup($mountpoint)]['groupname'];
  $perms = substr(decoct(fileperms($mountpoint)), 2);
  $userlist = '';
  foreach ($sysusers as $userdata)
   $userlist .= '   <option value="'.$userdata['username'].'">'
    .htmlentities($userdata['username']).'</option>'.chr(10);
  $grouplist = '';
  foreach ($sysgroups as $groupdata)
   $grouplist .= '   <option value="'.$groupdata['groupname'].'">'
    .htmlentities($groupdata['groupname']).'</option>'.chr(10);
  $table_ownership[] = array(
   'OWN_FS'	=> htmlentities($fsname),
   'OWN_MP'	=> htmlentities($mountpoint),
   'OWN_OWNER'	=> $owner,
   'OWN_GROUP'	=> $group,
   'OWN_PERMS'	=> $perms,
   'USERLIST'	=> $userlist,
   'GROUPLIST'	=> $grouplist
  );
 }

 // classes
 $class_ownership = ($tabbar_tab == 'ownership') ? 'normal' : 'hidden';
 $class_chmod = ($tabbar_tab == 'chmod') ? 'normal' : 'hidden';
 $class_displaysystem = ($hidesystem) ? 'normal' : 'hidden';
 $class_hidesystem = (!$hidesystem) ? 'normal' : 'hidden';

 // export new tags
 return array(
  'PAGE_ACTIVETAB'	=> 'Permissions',
  'PAGE_TITLE'		=> 'Permissions',
  'PAGE_TABBAR'         => $tabbar,
  'PAGE_TABBAR_URL'     => $tabbar_url,
  'PAGE_TABBAR_URLTAB'  => $tabbar_url .'&'. $tabbar_tab,
  'TABLE_OWNERSHIP'	=> $table_ownership,
  'CLASS_OWNERSHIP'	=> $class_ownership,
  'CLASS_CHMOD'		=> $class_chmod,
  'CLASS_DISPLAYSYSTEM'	=> $class_displaysystem,
  'CLASS_HIDESYSTEM'	=> $class_hidesystem
 );
}

function submit_permissions_ownership()
{
 // redirect url
 $url = 'files.php?permissions';

 // process
 $resetfs = processfilesystemsubmit('ownership');

 // change ownership
 $tag = 'ownership';
 $commands = array();
 if (@isset($_POST['submit_ownership']))
  foreach ($resetfs as $fsname => $fsmountpoint)
   if (@isset($_POST[$tag.'_change_'.$fsname]))
   {
    $user = @$_POST[$tag.'_user_'.$fsname];
    $group = @$_POST[$tag.'_group_'.$fsname];
    $action = @$_POST[$tag.'_action'];
    if (strlen($user) < 1)
     continue;
    if (strlen($group) > 0)
     $perm = $user.':'.$group;
    else
     $perm = $user;
    // add command to commands array
    if ($action == 'everything')
     $commands[] = '/usr/sbin/chown -R '.$perm.' '.$fsmountpoint;
    elseif ($action == 'directory')
    {
     $commands[] = '/usr/sbin/chown '.$perm.' '.$fsmountpoint.'/*';
     $commands[] = '/usr/sbin/chown '.$perm.' '.$fsmountpoint;
    }
    elseif ($action == 'filesystem')
     $commands[] = '/usr/sbin/chown '.$perm.' '.$fsmountpoint;
   }

 // execute commands
 if (count($commands) > 0)
  dangerouscommand($commands, $url);
 else
  page_feedback('nothing done. Remember: you must select the checkboxes to '
   .'change ownership of a filesystem', 'c_notice');

 // default redirect
 redirect_url($url);
}

function submit_permissions_advanced()
{
 // fetch data
 $url = 'files.php?permissions&chmod';

 // process
 $resetfs = processfilesystemsubmit('advanced');
 
 // change permissions (advanced tab)
 $tag = 'advanced';
 $action = @$_POST[$tag.'_action'];
 $commands = array();
 if (@isset($_POST['submit_'.$tag]))
  foreach ($resetfs as $fsname => $fsmountpoint)
   if (@isset($_POST[$tag.'_change_'.$fsname]))
   {
    $perms = @$_POST[$tag.'_permissions_'.$fsname];
    $dirperms = substr($perms, 0, strpos($perms, '@'));
    $fileperms = substr($perms, strpos($perms, '@') + 1);
    // skip if no permissions can be found
    if (!is_numeric($dirperms) OR !is_numeric($fileperms))
     continue;
    if ($action == 'everything')
     $maxdepth = '';
    elseif ($action == 'directory')
     $maxdepth = ' -maxdepth 1';
    elseif ($action == 'filesystem')
     $maxdepth = ' -maxdepth 0';
    else
     error('unknown action type');
    // defer to dangerous command function
    $commands[] = '/usr/bin/find '.$fsmountpoint.$maxdepth.' -type d -print0'
     .' | /usr/local/bin/sudo /usr/bin/xargs -0 /bin/chmod '.$dirperms;
    $commands[] = '/usr/bin/find '.$fsmountpoint.$maxdepth.' -type f -print0'
     .' | /usr/local/bin/sudo /usr/bin/xargs -0 /bin/chmod '.$fileperms;
   }

 // execute commands
 if (count($commands) > 0)
  dangerouscommand($commands, $url);
 else
  page_feedback('nothing done. Remember: you must select the checkboxes to '
   .'change ownership of a filesystem', 'c_notice');

 // default redirect
 redirect_url($url);
}

function processfilesystemsubmit($tag)
{
 // required library
 activate_library('zfs');

 // fetch data
 $prop = zfs_filesystem_properties(false, 'mountpoint');

 // reset filesystem array
 $resetfs = array();
 foreach ($_POST as $name => $value)
  if (strlen($value) > 0)
   if (substr($name, 0, strlen($tag.'_change_')) == $tag.'_change_')
    $resetfs[substr($name, strlen($tag.'_change_'))] = 
     @$prop[substr($name, strlen($tag.'_change_'))]['mountpoint']['value'];
 // sanity check on mountpoint
 foreach ($resetfs as $fsname => $fsmountpoint)
  if ($fsmountpoint{0} != '/')
   unset($resetfs[$fsname]);

 // return filesystem array
 return $resetfs;
}

