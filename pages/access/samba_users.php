<?php

function content_access_samba_users()
{
 // include javascript + stylesheet
 page_register_stylesheet('pages/access/widget_userdrag.css');
 page_register_javascript('pages/access/widget_userdrag.js');

 // export new tags
 return content_access_samba_users_tags();
}

function content_access_samba_users_tags()
{
 // required library
 activate_library('samba');
 activate_library('system');

 // system users & groups
 $sysusers = system_users();
 $sysgroups = system_groups();

 // samba user list
 $samba_userlist = '';
 foreach ($sysusers as $userdata)
  if ($userdata['username'] != 'root' AND $userdata['username'] != 'nobody')
   $samba_userlist .= '   <option value="'.$userdata['username'].'">'
    .htmlentities($userdata['username']).'</option>'.chr(10);
 // samba userid list
 $samba_useridlist = '';
 for ($i = 1000; $i <= 1200; $i++)
  if (!@isset($sysusers[$i]))
   $samba_useridlist .= "   <option value=\"$i\">$i</option>";
 // fetch samba list containing groups and users and create table
 $grouplist = samba_usergroups();
 $table_samba_groups = table_samba_groups($grouplist);

 // export new tags
 return @array(
  'PAGE_TITLE'                  => 'Samba users',
  'PAGE_ACTIVETAB'              => 'Users',
  'TABLE_SAMBA_GROUPS'		=> $table_samba_groups,
  'SAMBA_USERIDLIST'		=> $samba_useridlist,
  'SAMBA_USERLIST'		=> $samba_userlist,
 );
}

function table_samba_groups($grouplist)
{
 $table_sambagroups = array();
 foreach ($grouplist as $groupname => $users)
 {
  $table_users = array();
  foreach ($users as $user)
   $table_users[] = array(
    'SAMBAUSER_USERNAME'	=> htmlentities($user),
    'SAMBAUSER_USERUCFIRST'	=> htmlentities(ucfirst($user))
   );
  $class_hasusers = (!empty($table_users)) ? 'normal' : 'hidden';
  $stdgroup = ($groupname == 'share');
  $display_shares = ($stdgroup) ? 'Everyone' :
   htmlentities(ucfirst($groupname));
  $display_users = ($stdgroup) ? 'Samba users' :
   htmlentities(ucfirst($groupname));
  $specialgroup = ($stdgroup) ? 'normal' : 'hidden';
  $suffix = ($stdgroup) ? '_special' : '';
  $table_sambagroups[] = array(
   'TABLE_SAMBA_USERS'		=> $table_users,
   'CLASS_SAMBAGROUP_HASUSERS'	=> $class_hasusers,
   'SAMBAGROUP_GROUPNAME'	=> htmlentities($groupname),
   'SAMBAGROUP_DISPLAY_SHARES'	=> $display_shares,
   'SAMBAGROUP_DISPLAY_USERS'	=> $display_users,
   'SAMBAGROUP_SPECIAL'		=> $specialgroup,
   'SAMBAGROUP_SUFFIX'		=> $suffix
  );
 }
 return $table_sambagroups;
}


/* submit functions */

function submit_access_samba_users_dragdrop()
{
 // elevated privileges
 activate_library('super');

 // redirect URL
 $redir = 'access.php?samba&users';

 // move user to different group
 // 'default' is the default string of HTML hidden elements; they need to be
 // changed by javascript; we do not accept them otherwise
 if ((@$_POST['samba_users_oldgroup'] != 'default') AND
     (@$_POST['samba_users_newgroup'] != 'default') AND
     (@isset($_POST['samba_users_oldgroup'])) AND
     (@isset($_POST['samba_users_newgroup'])) AND
     (strlen(@$_POST['samba_users_user']) > 0))
 {
  $sambauser = $_POST['samba_users_user'];
  $oldgroup = $_POST['samba_users_oldgroup'];
  $newgroup = $_POST['samba_users_newgroup'];
  if ($newgroup == 'share')
   $newgroup = '1000';
  // this code had a regression in 10.0-RC, so we use workaround instead
  //  $command = '/usr/sbin/pw usermod '.$sambauser.' -G '.$newgroup;
  $command_delfromold = '/usr/sbin/pw groupmod '.$oldgroup.' -d '.$sambauser;
  $command_addtonew = '/usr/sbin/pw groupmod '.$newgroup.' -m '.$sambauser;
  // execute
  $result = super_execute($command_delfromold);
  if ($result['rv'] == 0)
   $result = super_execute($command_addtonew);
  if ($result['rv'] == 0)
   redirect_url($redir);
  else
   friendlyerror('could not move user to group <b>'.$newgroup.'</b><pre>'
    .htmlentities($result['output_str']).'</pre>', $redir);
 }
 else
  friendlyerror('invalid form submitted', $redir);
}

function submit_access_samba_users_adduser()
{
 // required libraries
 activate_library('samba');
 activate_library('system');

 // fetch system users
 $sysusers = system_users();

 // redirect URL
 $redir = 'access.php?samba&users';

 // POST variables
 $postgroup = @trim($_POST['groupname']);
 $postuser = @trim(strtolower($_POST['samba_adduser_'.$postgroup]));
 $postpasswd = @trim($_POST['samba_adduserpassword_'.$postgroup]);

 // sanity on username
 sanitize($postuser, 'a-z0-9\_\-\.', $newuser);
 if (strlen($newuser) < 1)
  redirect_url($redir);
 if ($newuser != $postuser)
  page_feedback('modified chosen username to <b>'.$newuser.'</b>', 'c_notice');
 // check whether chosen reserved name
 $reserveduserslist = array('guest', 'share', 'everyone');
 if (in_array($newuser, $reserveduserslist))
  friendlyerror('you have chosen a reserved name, please choose a different'
   .' name!', $redir);
 // check whether chosen username already in use
 foreach ($sysusers as $user)
  if ($user['username'] == $newuser)
   friendlyerror('user <b>'.$newuser.'</b> already exists! '
    .'Choose a different name instead.', $redir);
 // check password
 if (strlen($postpasswd) < 2)
  friendlyerror('Samba password must be at least 2 characters long. '
   .'Please make sure you enabled Javascript.', $redir);
 // set user ID
 for ($uid = 1001; $uid <= 9999; $uid++)
  if (!@isset($sysusers[$uid]))
   break;

 // set user group
 $groupstr = ($postgroup == 'share') ? '' : '-G '.$postgroup;

 // create new user
 $useroptions = '-c "Samba user" -d /nonexistent -s /sbin/nologin '.$groupstr;
 $result = system_adduser($newuser, false, $uid, 1000, $useroptions);
 if (!$result)
  friendlyerror('failed creating new user account', $redir);
 // activate user for use with samba
 $result = samba_setpassword($newuser, $postpasswd);
 if (!$result)
  friendlyerror('failed creating new user account', $redir);
 else
  redirect_url($redir);
}

function submit_access_samba_users_modify()
{
 // required libraries
 activate_library('samba');
 activate_library('system');

 // redirect URL
 $redir = 'access.php?samba&users';

 // POST variables
 $postgroup = @trim($_POST['groupname']);
 $postdelete = @$_POST['samba_delete_user_'.$postgroup];
 $postuser = @trim(strtolower($_POST['samba_modify_username_'.$postgroup]));
 $postpasswd = @trim($_POST['samba_modify_password_'.$postgroup]);

 // sanity check
 if (strlen($postuser) < 1)
  friendlyerror('invalid user submitted; please enable Javascript', $redir);

 // delete user
 if (@isset($_POST['samba_delete_user']))
 {
  system_user_delete($postuser, false);
  redirect_url($redir);
 }
 // modify user Samba password
 elseif (@isset($_POST['samba_modify_user']))
 {
  if (strlen($postpasswd) < 1)
   friendlyerror('please enter a new password for this user', $redir);
  $result = samba_setpassword($postuser, $postpasswd);
  if ($result)
   redirect_url($redir);
  else
   friendlyerror('could not activate user <b>'.htmlentities($postuser).'</b> '
    .'for use with Samba!', $redir);
 }
 else
  friendlyerror('invalid form submitted', $redir);
}

function submit_access_samba_users_addgroup()
{
 // fetch system groups
 activate_library('system');
 $sysgroups = system_groups();

 // redirect URL
 $redir = 'access.php?samba&users';

 // POST variables
 $postgroup = @trim(strtolower($_POST['samba_addgroup']));

 // sanity on groupname
 sanitize($postgroup, 'a-z0-9\_\-\.', $newgroup);
 if (strlen($newgroup) < 1)
  redirect_url($redir);
 if ($newgroup != $postgroup)
  page_feedback('modified chosen groupname to <b>'.$newgroup.'</b>',
   'c_notice');
 // check whether chosen reserved name
 $reservedgrouplist = array('share', 'standard', 'everyone');
 if (in_array($newgroup, $reservedgrouplist))
  friendlyerror('you have chosen a reserved name, please choose a different'
   .' name!', $redir);
 // check whether chosen groupname already in use
 foreach ($sysgroups as $group)
  if ($group['groupname'] == $newgroup)
   friendlyerror('group <b>'.$newgroup.'</b> already exists! '
    .'Choose a different name instead.', $redir);
 // set group ID
 for ($gid = 1001; $gid <= 9999; $gid++)
  if (!@isset($sysgroups[$gid]))
   break;
 // execute command to add new group
 $command = '/usr/sbin/pw groupadd -n '.$newgroup.' -g '.$gid;
 dangerouscommand($command, $redir);
}

function submit_access_samba_users_deletegroup()
{
 // required library
 activate_library('system');

 // redirect URL
 $redir = 'access.php?samba&users';

 // scan each POST variable for deletegroup name with group suffix
 foreach ($_POST as $name => $value)
  if (substr($name, 0, strlen('samba_deletegroup_')) == 'samba_deletegroup_')
  {
   $groupname = substr($name, strlen('samba_deletegroup_'));
   if (substr($name, -2) == '_x')
    $groupname = substr($groupname, 0, -2);
   // remove users in group
   $deleteusers = (@$_POST['samba_deleteusersingroup_'.$groupname] == 'on');
   if ($deleteusers)
   {
    $sysgroups = system_groups();
    foreach ($sysgroups as $group)
     if ($group['groupname'] == $groupname)
     {
      $usersingroup = trim($group['users']);
      break;
     }
    if (strlen(@$usersingroup) > 0)
    {
     $usersingroup = @explode(' ', $usersingroup);
     if (is_array($usersingroup))
      foreach ($usersingroup as $useringroup)
       system_user_delete($useringroup, false);
    }
    else
     error('oh');
   }
   // remove group
   $result = system_group_delete($groupname, false);
   if ($result)
    redirect_url($redir);
   else
    friendlyerror('deleting group <b>'.$groupname.'</b> has failed!', $redir);
  }
}

