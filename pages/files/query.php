<?php

function content_files_query()
{
 // required libraries
 activate_library('samba');
 activate_library('zfs');

 // set active tab
 $page['activetab'] = 'Filesystems';

 // include stylesheet from filesystems page
 page_register_stylesheet('pages/files/filesystems.css');
 page_register_javascript('pages/files/query.js');

 // call function
 $zfslist = zfs_filesystem_list();
 $zfsversion = zfs_version();

 // keep track of ZFSguru specific filesystems
 $gurufs = false;
 $hidegurufs = (@isset($_GET['displaygurufs'])) ? false : true;
 $displaygurufs = ($hidegurufs) ? '' : '&displaygurufs';

 // queried filesystem
 $queryfs = @htmlentities(trim($_GET['query']));

 // redirect if user queried unknown filesystem
 if (@!isset($zfslist[$queryfs]))
  friendlyerror('unknown filesystem "'.$queryfs.'"', 'files.php');

 // construct filesystem list table
 $fslist = array();
 foreach ($zfslist as $fsname => $fsdata)
 {
  // behavior for system filesystems
  $systemfs = zfs_filesystem_issystemfs($fsname);
  if ($systemfs)
  {
   $gurufs = true;
   if ($hidegurufs)
    continue;
  }

  // filesystem class
  $fsclass = ($systemfs) ? 'failurerow filesystem_system ' : '';
  if ($fsname == @$queryfs)
   $fsclass = 'activerow filesystem_selected';
  elseif (strpos($fsname, '/') === false)
   $fsclass = 'darkrow filesystem_root ';
  else
   $fsclass .= 'normal';

  // filesystem mountpoint
  if ($fsdata['mountpoint'] == 'legacy')
   $fsmountpoint = '<i>legacy</i>';
  elseif ($fsdata['mountpoint'] == '-')
   $fsmountpoint = '<i>volume</i>';
  else
   $fsmountpoint = '<a href="files.php?browse='
    .str_replace('%2F','/', htmlentities($fsdata['mountpoint']))
    .'">'.htmlentities($fsdata['mountpoint']).'</a>';

  // classes
  $poolfs = (strpos($fsname, '/') === false);
  $volumefs = ($fsdata['mountpoint'] == '-');
  $class_fspool = ($poolfs) ? 'normal' : 'hidden';
  $class_fsnormal = (!$systemfs AND !$poolfs AND !$volumefs)
   ? 'normal' : 'hidden';
  $class_fssystem = ($systemfs AND !$poolfs AND !$volumefs)
   ? 'normal' : 'hidden';
  $class_fsvolume = ($volumefs) ? 'normal' : 'hidden';

  // add row to fslist table
  $fslist[] = array(
   'CLASS_FSPOOL'	=> $class_fspool,
   'CLASS_FSNORMAL'	=> $class_fsnormal,
   'CLASS_FSSYSTEM'	=> $class_fssystem,
   'CLASS_FSVOLUME'	=> $class_fsvolume,
   'FS_ESC'		=> $fsname,
   'FS_USED'		=> $fsdata['used'],
   'FS_AVAIL'		=> $fsdata['avail'],
   'FS_REFER'		=> $fsdata['refer'],
   'FS_CLASS'		=> $fsclass,
   'FS_MOUNTPOINT'	=> $fsmountpoint
  );
 }

 // filesystem selectbox
 $fsselectbox = '';
 foreach ($zfslist as $fsname => $fsdata)
 {
  // determine whether fs is system filesystem
  $fsbase = @substr($fsname, strpos($fsname, '/') + 1);
  if ($basepos = strpos($fsbase, '/'))
   $fsbase = @substr($fsbase, 0, $basepos);
  if (($fsbase == 'zfsguru') OR
      (substr($fsbase, 0, strlen('zfsguru-system')) == 'zfsguru-system') OR
      ($fsbase == 'SWAP001'))
   $querygurufs = true;
  else
   $querygurufs = false;
  // add option to filesystem selectbox
  if ($fsname == $queryfs)
   $fsselectbox .= '<option value="'.htmlentities($fsname)
    .'" selected="selected">'.htmlentities($fsname).'</option>';
  elseif (!$hidegurufs OR !$querygurufs)
   $fsselectbox .= '<option value="'.htmlentities($fsname).'">'
    .htmlentities($fsname).'</option>';
 }

 // filesystem query data
 $zfsinfo = array();
 if (@strlen($queryfs) > 0)
 {
  // figure out which pool this filesystem belongs to
  if (strpos($queryfs, '/') === false)
   $poolname = $queryfs;
  else
   $poolname = substr($queryfs, 0, strpos($queryfs, '/'));

  // gather pool SPA version and system version
  $pool_spa = zfs_pool_version($poolname);

  // gather all filesystem properties of queried filesystem
  $prop = zfs_filesystem_properties($queryfs);
  $zfsinfo = @$prop[$queryfs];
 }

 // classes
 $class_upgrade_v5000 = ($pool_spa < 5000) ? 'normal' : 'hidden';
 $class_gurufs = ($gurufs) ? 'normal' : 'hidden';
 $class_gurufs_display = ($hidegurufs) ? 'normal' : 'hidden';
 $class_gurufs_hide = (!$hidegurufs) ? 'normal' : 'hidden';
 $class_afp_yes = 'hidden';
 $class_afp_no = 'hidden';

 // process data for queried filesystem
 if (@count($zfsinfo) > 1)
 {
  // calculate all data for queried filesystem
  if (strpos($queryfs, '/') === false)
   $queryfs_suffix = $queryfs;
  else
   $queryfs_suffix = substr($queryfs, strrpos($queryfs, '/') + 1);
  // filesystem properties
  $createpreg = preg_match_all('/([^\ ]+)(\ |$)/', 
   $zfsinfo['creation']['value'], $cmatch);
  $created = $cmatch[1][2].' '.$cmatch[1][1].' '.$cmatch[1][4].' @ '
   .$cmatch[1][3];
  $compressedsize = $zfsinfo['referenced']['value'];
  $compressfactor = (double)$zfsinfo['compressratio']['value'];
  $compressratio = number_format($compressfactor, 2, '.', '');
  $uncompressedsize = round((double)$zfsinfo['referenced']['value'] *
   $compressfactor, 2);
  $uncompressedsize .= $zfsinfo['referenced']['value']{
   strlen($zfsinfo['referenced']['value'])-1};
  $spacesaved = number_format((double)$zfsinfo['referenced']['value'] * 
   ($compressfactor-1), 2, '.', '');
  $snapused = $zfsinfo['usedbysnapshots']['value'];
  // TODO: this could use some work
  $spacesaved .= $zfsinfo['used']['value']{strlen(
   $zfsinfo['used']['value'])-1};
  $childrenused = $zfsinfo['usedbychildren']['value'];
  $sizeavailable = $zfsinfo['available']['value'];
  $totalsize = $zfsinfo['used']['value'];
  // checkboxes
  $atime = @trim($zfsinfo['atime']['value']);
  $readonly = @trim($zfsinfo['readonly']['value']);
  $cb_atime = ($atime == 'on')
   ? 'checked="checked"' : '';
  $cb_readonly = ($readonly == 'on')
   ? 'checked="checked"' : '';
  // select boxes
  $compressiontypes = array(
   'off'	=> 'No compression',
   'lz4'	=> 'LZ4 (recommended, v5000)',
   'lzjb'	=> 'LZJB',
   'gzip-1'	=> 'GZIP-1',
   'gzip-2'	=> 'GZIP-2',
   'gzip-3'	=> 'GZIP-3',
   'gzip-4'	=> 'GZIP-4',
   'gzip-5'	=> 'GZIP-5',
   'gzip'	=> 'GZIP-6 (default gzip)',
   'gzip-7'	=> 'GZIP-7',
   'gzip-8'	=> 'GZIP-8',
   'gzip-9'	=> 'GZIP-9 (slowest)');
  $deduptypes = array(
   'off'		=> 'No deduplication',
   'on'			=> 'Fletcher4',
   'verify'		=> 'Fletcher4 +verify',
   'sha256'		=> 'SHA256',
   'sha256,verify'	=> 'SHA256 +verify');
  $copiestypes = array(
   '1' => 'No additional redundancy',
   '2' => 'Two copies of each file',
   '3' => 'Three copies of each file'
  );
  $checksumtypes = array(
   'off'	=> 'Disabled (NOT recommended!)',
   'on'		=> 'Fletcher2 (default)',
   'fletcher4'	=> 'Fletcher4',
   'sha256'	=> 'SHA256 (high CPU)'
  );

  $compression = @trim($zfsinfo['compression']['value']);
  if ($compression == 'on')
   $compression = 'lzjb';
  elseif ($compression == 'gzip-6')
   $compression = 'gzip';
  $dedup = @trim($zfsinfo['dedup']['value']);
  $copies = @trim($zfsinfo['copies']['value']);
  $checksum = @trim($zfsinfo['checksum']['value']);
  $checksum = ($checksum == 'fletcher2') ? 'on' : $checksum;
  $sync = @trim($zfsinfo['sync']['value']);
  $primarycache = @trim($zfsinfo['primarycache']['value']);
  $secondarycache = @trim($zfsinfo['secondarycache']['value']);

  $box_compression = '';
  foreach ($compressiontypes as $value => $description)
   if ($value == $compression)
    $box_compression .= '<option value="'.$value.'" selected="selected">'
     .htmlentities($description).'</option>'.chr(10);
   else
    $box_compression .= '<option value="'.$value.'">'
     .htmlentities($description).'</option>'.chr(10);
  $box_dedup = '';
  foreach ($deduptypes as $value => $description)
   if ($value == $dedup)
    $box_dedup .= '<option value="'.$value.'" selected="selected">'
     .htmlentities($description).'</option>'.chr(10);
   else
    $box_dedup .= '<option value="'.$value.'">'
     .htmlentities($description).'</option>'.chr(10);
  $box_copies = '';
  foreach ($copiestypes as $value => $description)
   if ($value == $copies)
    $box_copies .= '<option value="'.$value.'" selected="selected">'
     .htmlentities($description).'</option>'.chr(10);
   else
    $box_copies .= '<option value="'.$value.'">'
     .htmlentities($description).'</option>'.chr(10);
  $box_checksum = '';
  foreach ($checksumtypes as $value => $description)
   if ($value == $checksum)
    $box_checksum .= '<option value="'.$value.'" selected="selected">'
     .htmlentities($description).'</option>'.chr(10);
   else
    $box_checksum .= '<option value="'.$value.'">'
     .htmlentities($description).'</option>'.chr(10);

  // disable deduplication if not supported by the system or pool
  $class_dedup = 'hidden';
  $class_nodedup_system = 'hidden';
  $class_nodedup_pool = 'hidden';
  if ($zfsversion['spa'] < 21)
   $class_nodedup_system = 'normal';
  elseif ($pool_spa < 21)
   $class_nodedup_pool = 'normal';
  else
   $class_dedup = 'normal';

  // filesystem sync
  $opt_sync_always = ($sync == 'always') ? 'selected="selected"' : '';
  $opt_sync_disabled = ($sync == 'disabled') ? 'selected="selected"' : '';

  // cache strategy
  $opt_pricache_2 = ($primarycache == 'metadata') ? 'selected="selected"' : '';
  $opt_pricache_3 = ($primarycache == 'none') ? 'selected="selected"' : '';
  $opt_seccache_2 = ($secondarycache == 'metadata') ? 'selected="selected"' : '';
  $opt_seccache_3 = ($secondarycache == 'none') ? 'selected="selected"' : '';

  // filesystem quota
  $quotaraw = @trim($zfsinfo['quota']['value']);
  $quota = substr($quotaraw, 0, -1);
  $quotacurrentunit = strtoupper(substr($quotaraw, -1));
  if ($quota == 'non')
  {
   $quota = '';
   $quotacurrentunit = 'disabled';
  }

  // quota units table
  $quotaunits = array('KiB', 'MiB', 'GiB', 'TiB', 'PiB');
  $table_quotaunits = array();
  foreach ($quotaunits as $quotaunit)
   if (strtoupper($quotaunit{0}) == $quotacurrentunit)
   {
    $table_quotaunits[] = array(
     'UNIT_SEL'		=> 'selected="selected"',
     'UNIT_VALUE'	=> strtoupper($quotaunit{0}),
     'UNIT_NAME'	=> $quotaunit
    );
   }
   else
    $table_quotaunits[] = array(
     'UNIT_SEL'		=> '',
     'UNIT_VALUE'	=> strtoupper($quotaunit{0}),
     'UNIT_NAME'	=> $quotaunit
    );

  // primary filesystem
  $class_primaryfs = (strpos($queryfs, '/') === false) ? 'normal' : 'hidden';

  // Samba/NFS share status
  if (@$zfsinfo['mountpoint']['value'] == 'legacy')
  {
   // legacy mountpoint; skip sharing
   $mountpoint			= 'legacy';
   $mountpoint_string		= '<i>legacy</i>';
   $class_nfsshared		= 'hidden';
   $class_nfsnotshared		= 'normal';
   $nfssharestatus		= 'Not shared';
   $nfssharename		= '--legacy--';
   $nfsshareaction		= 'legacy';
   $nfsshareactionname		= 'legacy';
   $nfssharesubmit		= 'disabled="disabled"';
   $class_sambashared		= 'hidden';
   $class_sambanotshared	= 'normal';
   $sambasharestatus		= 'Not shared';
   $sambasharename		= '--legacy--';
   $sambashareaction		= 'legacy';
   $sambashareactionname	= 'legacy';
   $sambasharesubmit		= 'disabled="disabled"';
  }
  elseif (@$zfsinfo['type']['value'] == 'volume')
  {
   // ZVOL; skip sharing
   $mountpoint			= 'volume';
   $mountpoint_string		= '<i>volume</i>';
   $class_nfsshared		= 'hidden';
   $class_nfsnotshared		= 'normal';
   $nfssharestatus		= 'Not shared';
   $nfssharename		= '--volume--';
   $nfsshareaction		= 'volume';
   $nfsshareactionname		= 'volume';
   $nfssharesubmit		= 'disabled="disabled"';
   $class_sambashared		= 'hidden';
   $class_sambanotshared	= 'normal';
   $sambasharestatus		= 'Not shared';
   $sambasharename		= '--volume--';
   $sambashareaction		= 'volume';
   $sambashareactionname	= 'volume';
   $sambasharesubmit		= 'disabled="disabled"';
  }
  else
  {
   // normal filesystem
   activate_library('nfs');

   // mountpoint
   $mountpoint = @trim($zfsinfo['mountpoint']['value']);
   $mountpoint_string = '<input class="yellow" type="text" name="mountpoint" '
    .'value="'.htmlentities($mountpoint).'" /> '
    .'<span class="minortext"><a href="/files.php?browse='
    .$mountpoint.'">[browse]</a></span>';

   // check if mountpoint is shared with Samba
   $sambasharename = samba_isshared($mountpoint);
   if ($sambasharename)
   {
    $sambaconf = samba_readconfig();
    $sambapermissions = samba_share_permissions($sambaconf, $sambasharename);
    $samba_accesstype = samba_share_accesstype($sambapermissions);

    $class_sambashared		= 'normal';
    $class_sambanotshared	= 'hidden';
    $sambasharestatus		= 'Shared';
    $sambasharename		= htmlentities($sambasharename);
    $sambashareprofile		= htmlentities(ucfirst($samba_accesstype));
    $sambashareaction		= 'unshare';
    $sambashareactionname	= 'Unshare';
   }
   else
   {
    $class_sambashared		= 'hidden';
    $class_sambanotshared	= 'normal';
    $sambasharestatus		= 'Not shared';
    $sambasharename		= '';
    $sambashareaccesstype	= '';
    $sambashareaction		= 'share';
    $sambashareactionname	= 'Share';
   }
   // nfs share status
   $nfslist = nfs_sharenfs_list($queryfs);
   if (@is_array($nfslist[$queryfs]))
    $nfsprofile = @ucfirst(nfs_getprofile($nfslist[$queryfs]));
   else
    $nfsprofile = 'Notshared';
   if ($nfsprofile == 'Notshared')
   {
    $class_nfsshared	= 'hidden';
    $class_nfsnotshared	= 'normal';
    $nfssharestatus	= 'Not shared';
    $nfssharename	= '';
    $nfsshareaction	= 'share';
    $nfsshareactionname	= 'Share';
   }
   else
   {
    $class_nfslocal = 'normal';
    $class_nfsinherited = 'hidden';
    if (@$zfsinfo['sharenfs']['source'] != 'local')
    {
     $class_nfsinherited = 'normal';
     $class_nfslocal = 'hidden';
    }
    $class_nfsshared	= 'normal';
    $class_nfsnotshared	= 'hidden';
    $nfssharestatus	= 'Shared';
    $nfsshareaction	= 'unshare';
    $nfsshareactionname	= 'Unshare';
   }
   // AppleShare share status
   sanitize(trim(basename($queryfs)), 'a-zA-Z0-9 -_', $afp_name, 20);
   if (@file_exists('/services/appleshare/panel/appleshare.php'))
   {
    include_once('/services/appleshare/panel/appleshare.php');
    if (appleshare_isshared($mountpoint))
     $class_afp_yes = 'normal';
    else
     $class_afp_no = 'normal';
   }
  }
 }

 // class of filesystem buttonbox (green = shared; red = not shared)
 $class_fsbuttonbox = (($class_nfsshared == 'normal') OR 
                       ($class_sambashared == 'normal')) ? 
  'fsbuttonbox-shared' : 'normal';

 // pool supported filesystem versions
 $poolsupported = array(
  2	=> 1,
  3	=> 9,
  4	=> 15,
  5	=> 24
 );

 // upgrade filesystem (current ZPL version)
 $upgrade_version = @$prop[$queryfs]['version']['value'];

 // upgrade table
 $table_upgrade = array();
 $maxversion = 0;
 foreach ($poolsupported as $zpl => $spa)
  if ($spa <= $pool_spa AND $zpl <= $zfsversion['zpl'])
   $maxversion = $zpl;
 for ($i = 2; $i <= $maxversion; $i++)
  if ($i > $upgrade_version)
   $table_upgrade[] = array(
    'VER'	=> $i,
    'SELECT'	=> ($i <= $zfsversion['zpl']) ? 'selected="selected"' : ''
   );

 // display upgrade if upgrade possible
 $upgrade = 'hidden';
 if (count($table_upgrade) > 0 AND ($upgrade_version > 0))
  $upgrade = 'normal';

 // other
 $defaultsnapshotname = date('Y-m-d');

 // export new tags
 $newtags = @array(
  'PAGE_ACTIVETAB'			=> 'Filesystems',
  'PAGE_TITLE'				=> 'Filesystem '.$queryfs,
  'FILES_FSSELECTBOX'			=> $fsselectbox,
  'TABLE_FILES_FSLIST'			=> $fslist,
  'TABLE_UPGRADEVERSION'		=> $table_upgrade,
  'TABLE_QUOTAUNITS'			=> $table_quotaunits,
  'CLASS_GURUFS'			=> $class_gurufs,
  'CLASS_GURUFS_DISPLAY'		=> $class_gurufs_display,
  'CLASS_GURUFS_HIDE'			=> $class_gurufs_hide,
  'CLASS_PRIMARYFS'			=> $class_primaryfs,
  'CLASS_UPGRADE_V5000'			=> $class_upgrade_v5000,
  'DISPLAYGURUFS'			=> $displaygurufs,
  'QUERYFS'				=> $queryfs,
  'QUERYFS_SUFFIX'			=> $queryfs_suffix,
  'QUERYFS_CREATED'			=> $created,
  'QUERYFS_COMPRESSRATIO'		=> $compressratio,
  'QUERYFS_SIZE_UNCOMPRESSED'		=> $uncompressedsize,
  'QUERYFS_SIZE_COMPRESSED'		=> $compressedsize,
  'QUERYFS_SIZE_RECLAIMED'		=> $spacesaved,
  'QUERYFS_SIZE_SNAPSHOTS'		=> $snapused,
  'QUERYFS_SIZE_CHILDREN'		=> $childrenused,
  'QUERYFS_SIZE_TOTAL'			=> $totalsize,
  'QUERYFS_SIZE_AVAILABLE'		=> $sizeavailable,
  'QUERYFS_MOUNTPOINT'			=> $mountpoint,
  'QUERYFS_MOUNTPOINT_STRING'		=> $mountpoint_string,
  'QUERYFS_COMPRESSION'			=> $compression,
  'QUERYFS_DEDUP'			=> $dedup,
  'QUERYFS_COPIES'			=> $copies,
  'QUERYFS_CHECKSUM'			=> $checksum,
  'QUERYFS_SYNC'			=> $sync,
  'QUERYFS_PRIMARYCACHE'		=> $primarycache,
  'QUERYFS_SECONDARYCACHE'		=> $secondarycache,
  'QUERYFS_ATIME'			=> $atime,
  'QUERYFS_READONLY'			=> $readonly,
  'OPT_SYNC_ALWAYS'			=> $opt_sync_always,
  'OPT_SYNC_DISABLED'			=> $opt_sync_disabled,
  'OPT_PRICACHE_2'			=> $opt_pricache_2,
  'OPT_PRICACHE_3'			=> $opt_pricache_3,
  'OPT_SECCACHE_2'			=> $opt_seccache_2,
  'OPT_SECCACHE_3'			=> $opt_seccache_3,
  'QUERYFS_CHECKED_ATIME'		=> $cb_atime,
  'QUERYFS_CHECKED_READONLY'		=> $cb_readonly,
  'QUERYFS_COMPRESSIONOPTIONS'		=> $box_compression,
  'CLASS_DEDUP'				=> $class_dedup,
  'QUERYFS_DEDUP_OPTIONS'		=> $box_dedup,
  'CLASS_NODEDUP_SYSTEM'		=> $class_nodedup_system,
  'CLASS_NODEDUP_POOL'			=> $class_nodedup_pool,
  'QUERYFS_REDUNDANCYOPTIONS'		=> $box_copies,
  'QUERYFS_CHECKSUMOPTIONS'		=> $box_checksum,
  'QUERYFS_DEFAULTSNAPSHOTNAME'		=> $defaultsnapshotname,
  'CLASS_FSBUTTONBOX'			=> $class_fsbuttonbox,
  'CLASS_NFSSHARED'			=> $class_nfsshared,
  'CLASS_NFSNOTSHARED'			=> $class_nfsnotshared,
  'CLASS_NFSLOCAL'			=> $class_nfslocal,
  'CLASS_NFSINHERITED'			=> $class_nfsinherited,
  'QUERYFS_NFSSHARESTATUS'		=> $nfssharestatus,
  'QUERYFS_NFSSHARENAME'		=> $nfsprofile,
  'QUERYFS_NFSSHAREACTION'		=> $nfsshareaction,
  'QUERYFS_NFSSHAREACTIONNAME'		=> $nfsshareactionname,
  'QUERYFS_NFSSHARESUBMIT'		=> @$nfssharesubmit,
  'CLASS_SAMBASHARED'			=> $class_sambashared,
  'CLASS_SAMBANOTSHARED'		=> $class_sambanotshared,
  'QUERYFS_SAMBASHARESTATUS'		=> $sambasharestatus,
  'QUERYFS_SAMBASHARENAME'		=> $sambasharename,
  'QUERYFS_SAMBASHAREPROFILE'		=> $sambashareprofile,
  'QUERYFS_SAMBASHAREACTION'		=> $sambashareaction,
  'QUERYFS_SAMBASHAREACTIONNAME'	=> $sambashareactionname,
  'QUERYFS_SAMBASHARESUBMIT'		=> @$sambasharesubmit,
  'QUERYFS_AFP_YES'			=> $class_afp_yes,
  'QUERYFS_AFP_NO'			=> $class_afp_no,
  'QUERYFS_AFP_NAME'			=> $afp_name,
  'QUERYFS_UPGRADE'			=> $upgrade,
  'QUERYFS_VERSION'			=> $upgrade_version,
  'QUERYFS_QUOTA'			=> $quota,
  'QUERYFS_QUOTARAW'			=> $quotaraw,
 );

 // return as tags
 return $newtags;
}

function submit_filesystem_modify()
{
 // required library
 activate_library('samba');
 activate_library('zfs');

 // variables
 $fs = @$_POST['fs_name'];
 $url = 'files.php?query='.$fs;
 $url2 = 'files.php';

 // submit actions
 if (@isset($_POST['submit_upgradefilesystem']))
 {
  $version = (int)$_POST['upgrade_version'];
  dangerouscommand('/sbin/zfs upgrade -V '.$version.' '.$fs, $url);
 }
 elseif (@isset($_POST['submit_destroyfilesystem']))
 {
  // check for children filesystems
  $fslist = zfs_filesystem_list($fs, '-r -t all');
  // redirect to different page in case of children datasets
  if (count($fslist) > 1)
   redirect_url('files.php?destroy='.urlencode($fs));
  // no children; continue deletion of single filesystem
  $fs_mp = @current($fslist);
  $fs_mp = @$fs_mp['mountpoint'];
  // remove any samba shares on the mountpoint
  samba_removesharepath($fs_mp);
  // start command array
  $command = array();
  // check for SWAP filesystem
  exec('/sbin/swapctl -l', $swapctl_raw);
  $swapctl = @implode(chr(10), $swapctl_raw);
  $fsdetails = zfs_filesystem_properties($fs, 'org.freebsd:swap');
  // disable swap
  if (@$fsdetails[$fs]['org.freebsd:swap']['value'] == 'on')
   if (@strpos($swapctl, '/dev/zvol/'.$fs) !== false)
    $command[] = '/sbin/swapoff /dev/zvol/'.$fs;
  // display message if swap volumes detected
  if (count($command) > 0)
   page_feedback('this volume is in use as SWAP device! '
    .'If you continue, the SWAP device will be deactivated first.', 'c_notice');
  // add destroy command
  $command[] = '/sbin/zfs destroy '.$fs;
  // defer to dangerous command function
  dangerouscommand($command, $url2);
 }
 elseif (@isset($_POST['submit_createsnapshot']))
 {
  sanitize(@$_POST['snapshot_name'], null, $snapname, 32);
  if (strlen($snapname) > 0)
   dangerouscommand('/sbin/zfs snapshot '.$fs.'@'.$snapname, $url);
  else
   friendlyerror('invalid snapshot name', $url);
 }
 elseif (@isset($_POST['submit_nfs_share']))
 {
   redirect_url('access.php?nfs&newfs='.$fs);
 }
 elseif (@isset($_POST['submit_nfs_unshare']))
  // TODO: set explicit to 'off' if already inherited from parent dataset
  dangerouscommand('/sbin/zfs inherit sharenfs '.$fs, $url);
 elseif (@isset($_POST['submit_samba_share']))
 {
  redirect_url('access.php?shares&newshare='.$fs);
 }
 elseif (@isset($_POST['submit_samba_unshare']))
 { 
  // fetch samba configuration
  $sambaconf = samba_readconfig();
  // search for mountpoint ('path') in samba share configuration
  $path = @$_POST['fs_mountpoint'];
  if ((strlen($path) > 0) AND is_array($sambaconf['shares']))
   foreach ($sambaconf['shares'] as $sharename => $sharedata)
    if ($sharedata['path'] == $path)
     unset($sambaconf['shares'][$sharename]);
  $result = samba_writeconfig($sambaconf);
  if ($result)
   page_feedback('filesystem <b>'.htmlentities($fs)
    .'</b> removed from Samba shares', 'b_success');
  else
   page_feedback('could not save Samba configuration!', 'a_failure');
 }
 elseif (@isset($_POST['submit_afp_newshare']))
 {
  sanitize($_POST['afp_name'], 'a-zA-Z0-9 -_', $afpname, 20);
  if ((strlen(@$_POST['fs_mountpoint']) < 1) OR 
      (@$_POST['fs_mountpoint']{0} != '/'))
   friendly_error('invalid filesystem mountpoint!', $url);
  // create new AFP share
  if (@file_exists('/services/appleshare/panel/appleshare.php'))
  {
   include_once('/services/appleshare/panel/appleshare.php');
   if (@$_POST['afp_timemachine'] == 'on')
    $result = appleshare_addshare($_POST['fs_mountpoint'], $afpname, 'tm');
   else
    $result = appleshare_addshare($_POST['fs_mountpoint'], $afpname);
  }
  else
   error('appleshare service not installed?');
 }
 elseif (@isset($_POST['submit_afp_removeshare']))
 {
  if ((strlen(@$_POST['fs_mountpoint']) < 1) OR
      (@$_POST['fs_mountpoint']{0} != '/'))
   friendly_error('invalid filesystem mountpoint!', $url);
  // remove existing AFP share
  if (file_exists('/services/appleshare/panel/appleshare.php'))
  {
   include_once('/services/appleshare/panel/appleshare.php');
   $result = appleshare_removeshare($_POST['fs_mountpoint']);
  }
  else
   error('appleshare service not installed?');
 }
 elseif (@isset($_POST['submit_updateproperties']))
 {
  // string variables (selectbox or textbox)
  $stringvars = array('mountpoint', 'compression', 'dedup', 'copies',
    'checksum', 'sync', 'primarycache', 'secondarycache');
  // skip mountpoint for zvol or legacy filesystem
  if (($_POST['fs_mountpoint'] == 'legacy') OR 
      ($_POST['fs_mountpoint'] == 'volume'))
   unset($stringvars[0]);
  // boolean variables (checkbox)
  $boolvars = array('atime', 'readonly');
  // check all above variables for submitted information and act accordingly
  foreach ($stringvars as $var)
   if ((@strlen($_POST['fs_'.$var]) > 0) AND
    (@$_POST[$var] != $_POST['fs_'.$var]))
   {
    $fspool = @substr($fs, 0, strpos($fs, '/'));
    if (($var == 'compression') AND ($_POST[$var] == 'lz4'))
     dangerouscommand(array(
      '/sbin/zpool upgrade '.$fspool,
      '/sbin/zfs set '.$var.'='.$_POST[$var].' '.$fs,
     ), $url);
    else
     dangerouscommand('/sbin/zfs set '.$var.'='.$_POST[$var].' '.$fs, $url);
   }
  foreach ($boolvars as $var)
   if ((@$_POST[$var] == 'on') AND ($_POST['fs_'.$var] == 'off'))
    dangerouscommand('/sbin/zfs set '.$var.'=on '.$fs, $url);
   elseif ((@$_POST[$var] != 'on') AND ($_POST['fs_'.$var] == 'on'))
    dangerouscommand('/sbin/zfs set '.$var.'=off '.$fs, $url);
  // filesystem quota
  $newquota = @$_POST['quota'].@$_POST['quota_unit'];
  if (@$_POST['quota_unit'] == 'disabled')
   $newquota = '';
  $oldquota = @$_POST['fs_quota'];
  if ($newquota != $oldquota)
   dangerouscommand('/sbin/zfs set quota='.$newquota.' '.$fs, $url);
  // not deferred by dangerous command function? (command confirmation off)
  redirect_url($url);
 }
 redirect_url($url);
}

