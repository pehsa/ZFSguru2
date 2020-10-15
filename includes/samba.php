<?php

/**
 * @return false
 */
function samba_readconfig()
{
    global $guru;
    // fetch configuration file contents
    $rawtext = @file_get_contents($guru[ 'path' ][ 'Samba' ]);
    if ($rawtext == '') {
        return false;
    }

    // begin by splitting the configuration file in three chunks
    $split = preg_split('/^#(=)+(.*)(=)+\r?$/m', $rawtext);
    // now fetch all the global variables
    preg_match_all(
        '/^[ ]*([a-zA-Z0-9]+(\s[a-zA-Z0-9]+)*)[ ]*=[ ]*(.*)$/Um',
        $split[ 1 ], $global
    );
    if (is_array($global) ) {
        foreach ( $global[ 1 ] as $id => $propertyname ) {
            $config[ 'global' ][ trim($propertyname) ] = trim($global[ 3 ][ $id ]);
        }
    } else {
            return false;
    }

    // now work on the shares
    $sharesplit = preg_split('/^\[([a-zA-Z0-9]+)]/Um', $split[ 2 ]);
    preg_match_all('/^\[([a-zA-Z0-9]+)]/Um', $split[ 2 ], $sharenames_match);
    $sharenames = $sharenames_match[ 1 ];
    // the following did not work, due to PREG_SPLIT_DELIM_CAPTURE messing
    // with the results. unknown issue; to be looked at?
    // circumvented with separate sharename regexp
    //  $sharesplit = preg_split('/^\[([a-zA-Z0-9]+)\]/Um', $split[2],
    //   PREG_SPLIT_DELIM_CAPTURE);
    //  $sharesplit = preg_split('/^\[([a-zA-Z0-9]+)\]\r?$/Um', $split[2]);
    if (is_array($sharesplit) ) {
        foreach ( $sharesplit as $sid => $singleshare ) {
            preg_match_all(
                '/^[ ]*([a-zA-Z0-9]+(\s[a-zA-Z0-9]+)*)[ ]*=[ ]*(.*)$/m',
                $singleshare, $sharecontents
            );
            $sharename = @$sharenames[ $sid - 1 ];
            // add the shares to the config array, to be returned by this function
            if (is_array($sharecontents)AND( @strlen($sharename) > 0 ) ) {
                foreach ( $sharecontents[ 1 ] as $id => $propertyname ) {
                    $config[ 'shares' ][ trim($sharename) ][ trim($propertyname) ] =
                    $sharecontents[ 3 ][ $id ];
                }
            }
        }
    }
    // process alias variables
    $aliasvariables = samba_variables_alias();
    foreach ( $aliasvariables as $mastervariable => $aliasvars ) {
        foreach ( $aliasvars as $aliasvar ) {
            // check global variables
            if (isset($config[ 'global' ][ $aliasvar ]) ) {
                $config[ 'global' ][ $mastervariable ] = $config[ 'global' ][ $aliasvar ];
                unset($config[ 'global' ][ $aliasvar ]);
            }
            // check share variables
            if (@is_array($config[ 'shares' ]) ) {
                foreach ( $config[ 'shares' ] as $sharename => $sharevariables ) {
                    foreach ( $sharevariables as $sharevar => $sharevalue ) {
                        if ($sharevar == $aliasvar ) {
                            unset($config[ 'shares' ][ $sharename ][ $sharevar ]);
                            $config[ 'shares' ][ $sharename ][ $mastervariable ] = $sharevalue;
                        }
                    }
                }
            }
        }
    }

    // process inverted alias variables
    $invertedalias = samba_variables_alias_inverted();
    foreach ( $invertedalias as $mastervariable => $invertedvars ) {
        foreach ( $invertedvars as $invertedvar ) {
            // check global variables
            if (isset($config[ 'global' ][ $invertedvar ]) ) {
                $config[ 'global' ][ $mastervariable ] = ( $invertedvar === 'yes' ) ? 'no' : 'yes';
                unset($config[ 'global' ][ $invertedvar ]);
            }
            // check share variables
            if (@is_array($config[ 'shares' ]) ) {
                foreach ( $config[ 'shares' ] as $sharename => $sharevariables ) {
                    foreach ( $sharevariables as $sharevar => $sharevalue ) {
                        if ($sharevar == $invertedvar ) {
                            unset($config[ 'shares' ][ $sharename ][ $sharevar ]);
                            $config[ 'shares' ][ $sharename ][ $mastervariable ] = ( $sharevalue === 'yes' ) ?
                            'no' : 'yes';
                        }
                    }
                }
            }
        }
    }
    return $config;
}

/**
 * @param       $newconfig
 * @param false $removeglobals
 *
 * @return bool
 */
function samba_writeconfig( $newconfig, $removeglobals = false )
{
    global $guru;

    // elevated privileges
    activate_library('super');

    if (!is_array($newconfig) ) {
        error('Invalid call to function samba_writeconfig()');
    }
    // fetch configuration file contents
    $rawtext = @file_get_contents($guru[ 'path' ][ 'Samba' ]);
    if ($rawtext == '') {
        return false;
    }
    // split the configuration file in three parts
    $split = preg_split('/^#(=)+(.*)(=)+\r?$/m', $rawtext);
    // check for expected format
    if (( count($split) != 3 )OR( !is_string(@$split[ 1 ]) ) ) {
        error('Samba configuration file smb4.conf differs from expected format.');
    }

    // start work on the globals section
    foreach ( $newconfig[ 'global' ] as $name => $value ) {
        $split[ 1 ] = preg_replace(
            '/^[ ]*(' . $name . ')[ ]*=[ ]*(.*)$/Um',
            trim($name) . ' = ' . trim($value), $split[ 1 ], 1, $pregcount 
        );
        if ($pregcount < 1 ) {
            $split[ 1 ] .= chr(10) . $name . ' = ' . $value . chr(10);
        }
    }

    // remove globals
    if (is_array($removeglobals) ) {
        foreach ( $removeglobals as $name ) {
            $split[ 1 ] = preg_replace(
                '/^[ ]*(' . $name . ')[ ]*=[ ]*(.*)$/Um',
                '', $split[ 1 ] 
            );
        }
    }

    // remove alias in globals
    $sambaalias = samba_variables_alias();
    $invertedalias = samba_variables_alias_inverted();
    foreach ( $sambaalias as $mastervariable => $aliasvars ) {
        foreach ( $aliasvars as $aliasvar ) {
            $split[ 1 ] = preg_replace(
                '/^[ ]*(' . $aliasvar . ')[ ]*=[ ]*(.*)$/Um', '',
                $split[ 1 ] 
            );
        }
    }
    foreach ( $invertedalias as $mastervariable => $invertedvars ) {
        foreach ( $invertedvars as $invertedvar ) {
            $split[ 1 ] = preg_replace(
                '/^[ ]*(' . $invertedvar . ')[ ]*=[ ]*(.*)$/Um', '',
                $split[ 1 ] 
            );
        }
    }

    // start work on the shares section
    $shareblock = chr(10);
    foreach ( $newconfig[ 'shares' ] as $sharename => $share ) {
        $shareblock .= chr(10) . '[' . $sharename . ']' . chr(10);
        foreach ( $share as $shareproperty => $propertyvalue ) {
            $shareblock .= trim($shareproperty) . ' = ' . trim($propertyvalue) . chr(10);
        }
    }

    // TODO: I really want this ugly code to be removed..
    // now glue split parts together again
    $combined =
    $split[ 0 ]
    . '#======================= Global Settings '
    . '====================================='
    . $split[ 1 ]
    . '#============================ Share Definitions '
    . '=============================='
    . $shareblock;
    // before we overwrite new configuration file, set permissions for php access
    super_execute('/bin/chmod 666 ' . $guru[ 'path' ][ 'Samba' ]);
    // now write the new configuration file to disk
    $result = file_put_contents($guru[ 'path' ][ 'Samba' ], $combined);
    // and reset the permissions again
    super_execute('/bin/chmod 444 ' . $guru[ 'path' ][ 'Samba' ]);
    return true;
}

/**
 * @param $username
 * @param $password
 *
 * @return bool
 */
function samba_setpassword( $username, $password )
{
    // elevated privileges
    activate_library('super');

    // write new password to temp file
    $filepath = '/tmp/zfsguru_smbpasswd.dat';
    super_execute('/usr/bin/touch ' . $filepath);
    super_execute('/usr/sbin/chown root:888 ' . $filepath);
    super_execute('/bin/chmod 620 ' . $filepath);
    file_put_contents($filepath, $password);
    super_execute('/usr/chmod 600 ' . $filepath);
    // call smbpasswd script
    $result = super_script('smbpasswd', $username);
    // remove temp password file (redundant)
    @unlink($filepath);
    // return result
    return ( @$result[ 'rv' ] === 0 );
}

/**
 * @return mixed
 */
function samba_restartservice()
{
    global $guru;

    // elevated privileges
    activate_library('super');

    // restart samba
    $result = super_execute($guru[ 'rc.d' ][ 'Samba' ] . ' onerestart');
    return ( $result[ 'rv' ] );
}

/**
 * @param $path
 *
 * @return false|int|string
 */
function samba_isshared( $path )
{
    $config = samba_readconfig();
    if (@!is_array($config[ 'shares' ]) ) {
        return false;
    }
    foreach ( $config[ 'shares' ] as $sharename => $share ) {
        if ($share[ 'path' ] == trim($path) ) {
            return $sharename;
        }
    }
    return false;
}

/**
 * @param $sharename
 *
 * @return bool
 */
function samba_removeshare( $sharename )
{
    // read samba configuration
    $sambaconf = samba_readconfig();
    // locate share with given mountpoint
    if (!@is_array($sambaconf[ 'shares' ][ $sharename ]) ) {
        error('share "' . $sharename . '" does not exist');
    }
    // remove share from array
    unset($sambaconf[ 'shares' ][ $sharename ]);
    // write new configuration and return true
    samba_writeconfig($sambaconf);
    return true;
}

/**
 * @param $mountpoint
 *
 * @return bool
 */
function samba_removesharepath( $mountpoint )
{
    // read samba configuration
    $sambaconf = samba_readconfig();
    // locate share with given mountpoint
    if (@is_array($sambaconf[ 'shares' ]) ) {
        foreach ( $sambaconf[ 'shares' ] as $sharename => $sharedata ) {
            if ($sharedata[ 'path' ] == $mountpoint ) {
                // remove share from array
                unset($sambaconf[ 'shares' ][ $sharename ]);
                // write new configuration and return true
                samba_writeconfig($sambaconf);
                return true;
            }
        }
    }
    // nothing done; return false
    return false;
}

/**
 * @param $mountpoint
 *
 * @return bool
 */
function samba_removesharepath_recursive( $mountpoint )
{
    // read samba configuration
    $sambaconf = samba_readconfig();
    // locate share with mountpoint that begins with supplied mountpoint
    $changed = false;
    if (@is_array($sambaconf[ 'shares' ]) ) {
        foreach ( $sambaconf[ 'shares' ] as $sharename => $sharedata ) {
            if (strpos($sharedata['path'], $mountpoint) === 0) {
                // remove share from array
                unset($sambaconf[ 'shares' ][ $sharename ]);
                $changed = true;
            }
        }
    }
    if ($changed ) {
        // write new configuration and return true
        samba_writeconfig($sambaconf);
        return true;
    }

    return false;
}

/**
 * @param $username
 */
function samba_remove_user( $username )
{
    // read samba configuration
    $sambaconf_orig = samba_readconfig();
    $sambaconf = $sambaconf_orig;
    // access lists
    $accesslists = ['read list', 'write list', 'admin users', 'invalid users'];
    // traverse each share in search for access lists that include username
    if (@is_array($sambaconf[ 'shares' ]) ) {
        foreach ( $sambaconf[ 'shares' ] as $sharename => $shareproperties ) {
            foreach ( $accesslists as $accesslist ) {
                if (@isset($shareproperties[ $accesslist ]) ) {
                    $expl = explode(' ', $shareproperties[ $accesslist ]);
                    $newlist = [];
                    foreach ( $expl as $userorgroup ) {
                        if ($userorgroup != $username ) {
                            $newlist[] = $userorgroup;
                        }
                    }
                    if (empty($newlist) ) {
                        unset($sambaconf[ 'shares' ][ $sharename ][ $accesslist ]);
                    } else {
                        $sambaconf[ 'shares' ][ $sharename ][ $accesslist ] = implode(' ', $newlist);
                    }
                }
            }
        }
    }
                // save samba configuration only if modified
    if ($sambaconf != $sambaconf_orig ) {
        samba_writeconfig($sambaconf);
    }
}

/**
 * @param $groupname
 */
function samba_remove_group( $groupname )
{
    if ($groupname != '') {
        samba_remove_user('+' . $groupname);
        samba_remove_user('@' . $groupname);
    }
}

/**
 * @return array[]
 */
function samba_usergroups()
{
    // required library
    activate_library('system');

    // system users & groups
    $sysusers = system_users();
    $sysgroups = system_groups();

    // sanity check on group #1000 called 'share'
    if (!@isset($sysgroups[ 1000 ]) ) {
        // TODO: how to handle this?
        error('missing group #1000; you should have a <b>share</b> group!');
    }

    // standardusers part of GID 1000
    $standardusers = [];
    foreach ( $sysusers as $user ) {
        if (($user['groupid'] == 1000) && ($user['userid'] > 1000) and ($user['userid'] < 65533)) {
            $standardusers[ ( int )$user[ 'userid' ] ] = $user[ 'username' ];
        }
    }

    // groupusers contain the users in other groups than the standard group #1000
    $groupusers = [];
    foreach ( $sysgroups as $group ) {
        if (( $group[ 'groupid' ] > 1000 )AND( $group[ 'groupid' ] < 65533 ) ) {
            if (trim($group['users']) !== '') {
                $groupusers[ $group[ 'groupname' ] ] = explode(',', $group[ 'users' ]);
            } else {
                $groupusers[ $group[ 'groupname' ] ] = [];
            }
        }
    }

    // remove users in special groups from the standardusers array
    $rejectedusers = [];
    foreach ( $groupusers as $groupname => $userlist ) {
        foreach ( $userlist as $user ) {
            $uid = array_search($user, $standardusers, true);
            if ($uid > 0 ) {
                unset($standardusers[ $uid ]);
            } elseif ($sysusers[ $uid ][ 'groupid' ] != 1000 ) {
                $rejectedusers[ $user ] = $user;
            }
        }
    }
    // remove rejected users from any group
    foreach ( $rejectedusers as $ruser ) {
        foreach ( $groupusers as $groupname => $userlist ) {
            foreach ( $userlist as $id => $user ) {
                if ($user == $ruser ) {
                    unset($groupusers[ $groupname ][ $id ]);
                }
            }
        }
    }

                // start array with standard users
    $usergroups = ['share' => $standardusers];

    // add the other groups
    foreach ( $groupusers as $groupname => $userlist ) {
        $usergroups[ $groupname ] = [];
        foreach ( $userlist as $user ) {
            if (@!isset($rejectedusers[ $user ]) ) {
                $usergroups[ $groupname ] = $userlist;
            }
        }
    }

    // report on any rejected users
    if (!empty($rejectedusers) ) {
        page_feedback(
            'the following users were rejected because they have a group '
            . 'ID other than #1000: <b>' . implode(', ', $rejectedusers) . '</b>',
            'a_warning' 
        );
    }
    return $usergroups;
}

/**
 * @param $sambaconf
 * @param $sharename
 *
 * @return array[]
 */
function samba_share_permissions( $sambaconf, $sharename )
{
    $shareperms = [
    'fullaccess' => [],
    'readonly' => [],
    'noaccess' => []
    ];
    if (!@isset($sambaconf[ 'shares' ][ $sharename ]) ) {
        return $shareperms;
    }
    // share access defaults
    $guestok = ( @$sambaconf[ 'shares' ][ $sharename ][ 'guest ok' ] === 'yes' );
    $readonly = ( @$sambaconf[ 'shares' ][ $sharename ][ 'read only' ] !== 'no' );
    // check guest access
    if (!$guestok ) {
        $shareperms[ 'noaccess' ][] = 'guest';
    } elseif ($readonly ) {
        $shareperms[ 'readonly' ][] = 'guest';
    } else {
        $shareperms[ 'fullaccess' ][] = 'guest';
    }
    // share access lists
    $accesslists_names = [
    'fullaccess' => 'write list',
    'readonly' => 'read list',
    'noaccess' => 'invalid users'
    ];
    foreach ( $accesslists_names as $spname => $alname ) {
        $accesslist_array = @explode(' ', $sambaconf[ 'shares' ][ $sharename ][ $alname ]);
        if (!empty($accesslist_array)AND is_array($accesslist_array) ) {
            foreach ( $accesslist_array as $listitem ) {
                if (trim($listitem) !== '') {
                    $shareperms[ $spname ][] = trim($listitem);
                }
            }
        }
    }
    return $shareperms;
}

/**
 * @param $perm
 *
 * @return string
 */
function samba_share_accesstype( $perm )
{
    if (@$perm[ 'fullaccess' ][ 0 ] === 'guest' ) {
        return 'public';
    }
    if (@empty($perm[ 'fullaccess' ])AND @empty($perm[ 'readonly' ]) ) {
        return 'noaccess';
    }
    if (( @$perm[ 'fullaccess' ][ 0 ] === '@share' )AND @empty($perm[ 'readonly' ]) ) {
        return 'protected';
    }
    if (( @count($perm[ 'fullaccess' ]) == 1 )AND @empty($perm[ 'readonly' ]) ) {
        return 'private';
    }
    return 'custom';

    /*if (@$perm[ 'readonly' ][ 0 ] === 'guest' ) {
        $atype = 'public';
    } elseif (in_array('@share', @$perm['noaccess'], true)) {
        $atype = 'access disabled';
    } else {
        $atype = '?';
    }
    return $atype;*/
}

/**
 * @return string[]
 */
function samba_variables_global()
{
    return [
    'abort shutdown script',
    'acl allow execute always',
    'acl compatibility',
    'add group script',
    'add machine script',
    'add port command',
    'addprinter command',
    'add share command',
    'add user command',
    'add user to group script',
    'afs username map',
    'algorithmic rid base',
    'allow trusted domains',
    'announce as',
    'announce version',
    'auth methods',
    'bind interfaces only',
    'browse list',
    'cache directory',
    'change share command',
    'check password script',
    'client lanman auth',
    'client ldap sasl wrapping',
    'client ntlmv2 auth',
    'client plaintext auth',
    'client schannel',
    'client signing',
    'client use spnego',
    'cluster addresses',
    'clustering',
    'config backend',
    'config file',
    'create krb5 conf',
    'ctdbd socket',
    'ctdb timeout',
    'cups connection timeout',
    'cups encrypt',
    'cups server',
    'deadtime',
    'debug class',
    'debug hires timestamp',
    'debug pid',
    'debug prefix timestamp',
    'debug timestamp',
    'debug uid',
    'dedicated keytab file',
    'default service',
    'defer sharing violations',
    'delete group script',
    'deleteprinter command',
    'delete share command',
    'delete user from group script',
    'delete user script',
    'disable netbios',
    'disable spoolss',
    'display charset',
    'dns proxy',
    'domain logons',
    'domain master',
    'dos charset',
    'enable asu support',
    'enable core files',
    'enable privileges',
    'enable spoolss',
    'encrypt passwords',
    'enhanced browsing',
    'enumports command',
    'eventlog list',
    'get quota command',
    'getwd cache',
    'guest account',
    'homedir map',
    'host msdfs',
    'hostname lookups',
    'idmap alloc backend',
    'idmap alloc config',
    'idmap backend',
    'idmap cache time',
    'idmap config',
    'idmap gid',
    'idmap negative cache time',
    'idmap uid',
    'include',
    'init logon delayed hosts',
    'init logon delay',
    'interfaces',
    'iprint server',
    'keepalive',
    'kerberos method',
    'kernel oplocks',
    'lanman auth',
    'large readwrite',
    'ldap admin dn',
    'ldap connection timeout',
    'ldap debug level',
    'ldap debug threshold',
    'ldap delete dn',
    'ldap deref',
    'ldap follow referral',
    'ldap group suffix',
    'ldap idmap suffix',
    'ldap machine suffix',
    'ldap page size',
    'ldap passwd sync',
    'ldap replication sleep',
    'ldapsam:editposix',
    'ldapsam:trusted',
    'ldap ssl ads',
    'ldap ssl',
    'ldap suffix',
    'ldap timeout',
    'ldap user suffix',
    'lm announce',
    'lm interval',
    'load printers',
    'local master',
    'lock directory',
    'lock spin count',
    'lock spin time',
    'log file',
    'log level',
    'logon drive',
    'logon home',
    'logon path',
    'logon script',
    'lpq cache time',
    'machine password timeout',
    'mangle prefix',
    'mangling method',
    'map to guest',
    'map untrusted to domain',
    'max disk size',
    'max log size',
    'max mux',
    'max open files',
    'max protocol',
    'max smbd processes',
    'max stat cache size',
    'max ttl',
    'max wins ttl',
    'max xmit',
    'message command',
    'min protocol',
    'min receivefile size',
    'min wins ttl',
    'name cache timeout',
    'name resolve order',
    'netbios aliases',
    'netbios name',
    'netbios scope',
    'nis homedir',
    'nmbd bind explicit broadcast',
    'ntlm auth',
    'nt pipe support',
    'nt status support',
    'null passwords',
    'obey pam restrictions',
    'oplock break wait time',
    'os2 driver map',
    'os level',
    'pam password change',
    'panic action',
    'paranoid server security',
    'passdb backend',
    'passdb expand explicit',
    'passwd chat debug',
    'passwd chat timeout',
    'passwd chat',
    'passwd program',
    'password level',
    'password server',
    'perfcount module',
    'pid directory',
    'preferred master',
    'preload modules',
    'preload',
    'printcap cache time',
    'printcap name',
    'private dir',
    'read raw',
    'realm',
    'registry shares',
    'remote announce',
    'remote browse sync',
    'rename user script',
    'reset on zero vc',
    'restrict anonymous',
    'root directory',
    'security',
    'server schannel',
    'server signing',
    'server string',
    'set directory',
    'set primary group script',
    'set quota command',
    'share:fake_fscaps',
    'show add printer wizard',
    'shutdown script',
    'smb passwd file',
    'smb ports',
    'socket address',
    'socket options',
    'stat cache',
    'state directory',
    'svcctl list',
    'syslog only',
    'syslog',
    'template homedir',
    'template shell',
    'time offset',
    'time server',
    'unix charset',
    'unix extensions',
    'unix password sync',
    'update encrypted',
    'use mmap',
    'username level',
    'username map script',
    'username map',
    'usershare allow guests',
    'usershare max shares',
    'usershare owner only',
    'usershare path',
    'usershare prefix allow list',
    'usershare prefix deny list',
    'usershare template share',
    'use spnego',
    'utmp directory',
    'utmp',
    'winbind cache time',
    'winbind enum groups',
    'winbind enum users',
    'winbind expand groups',
    'winbind nested groups',
    'winbind normalize names',
    'winbind nss info',
    'winbind offline logon',
    'winbind reconnect delay',
    'winbind refresh tickets',
    'winbind rpc only',
    'winbind separator',
    'winbind trusted domains only',
    'winbind use default domain',
    'wins hook',
    'wins proxy',
    'wins server',
    'wins support',
    'workgroup',
    'write raw',
    'wtmp directory'
    ];
}

/**
 * @return string[]
 */
function samba_variables_share()
{
    return [
    'access based share enum',
    'acl check permissions',
    'acl group control',
    'acl map full control',
    'administrative share',
    'admin users',
    'afs share',
    'aio read size',
    'aio write behind',
    'aio write size',
    'allocation roundup size',
    'available',
    'blocking locks',
    'block size',
    'browsable',
    'case sensitive',
    'change notify',
    'comment',
    'copy',
    'create mask',
    'csc policy',
    'cups options',
    'default case',
    'default devmode',
    'delete readonly',
    'delete veto files',
    'dfree cache time',
    'dfree command',
    'directory mask',
    'directory name cache size',
    'directory security mask',
    'dmapi support',
    'dont descend',
    'dos filemode',
    'dos filetime resolution',
    'dos filetimes',
    'ea support',
    'fake directory create times',
    'fake oplocks',
    'follow symlinks',
    'force create mode',
    'force directory mode',
    'force directory security mode',
    'force group',
    'force printername',
    'force security mode',
    'force unknown acl user',
    'force user',
    'fstype',
    // synonym: public
    'guest ok',
    'guest only',
    'hide dot files',
    'hide files',
    'hide special files',
    'hide unreadable',
    'hide unwriteable files',
    'hosts allow',
    'hosts deny',
    'inherit acls',
    'inherit owner',
    'inherit permissions',
    'invalid users',
    'kernel change notify',
    'level2 oplocks',
    'locking',
    'lppause command',
    'lpq command',
    'lpresume command',
    'lprm command',
    'magic output',
    'magic script',
    'mangled names',
    'mangling char',
    'mangling method',
    'map acl inherit',
    'map archive',
    'map hidden',
    'map readonly',
    'map system',
    'max connections',
    'max print jobs',
    'max reported print jobs',
    'min print space',
    'msdfs proxy',
    'msdfs root',
    'nt acl support',
    'only user',
    'oplock contention limit',
    'oplocks',
    'path',
    'posix locking',
    'postexec',
    'preexec close',
    'preexec',
    'preserve case',
    'printable',
    'print command',
    'printer admin',
    'printer name',
    'printing',
    'printjob username',
    'profile acls',
    'queuepause command',
    'queueresume command',
    'read list',
    // inverted synonym: writeable
    'read only',
    'root postexec',
    'root preexec close',
    'root preexec',
    'security mask',
    'set directory',
    'share modes',
    'short preserve case',
    'smb encrypt',
    'store dos attributes',
    'strict allocate',
    'strict locking',
    'strict sync',
    'sync always',
    'use client driver',
    'username',
    'use sendfile',
    'valid users',
    //  '-valid',  // note: skipping this one, looks like a nasty debug setting to me
    'veto files',
    'veto oplock files',
    'vfs objects',
    'volume',
    'wide links',
    'write cache size',
    'write list'
    ];
}

/**
 * @return \string[][]
 */
function samba_variables_alias()
{
    return [
    'browseable' => ['browsable'],
    'case sensitive' => ['casesignames'],
    'create mask' => ['create mode'],
    'debug timestamp' => ['timestamp logs'],
    'default service' => ['default'],
    'directory mask' => ['directory mode'],
    'force group' => ['group'],
    'guest ok' => ['public'],
    'guest only' => ['only guest'],
    'hosts allow' => ['allow hosts'],
    'hosts deny' => ['deny hosts'],
    'idmap gid' => ['winbind gid'],
    'idmap uid' => ['winbind uid'],
    'ldap passwd sync' => ['ldap password sync'],
    'lock directory' => ['lock dir'],
    'log level' => ['debuglevel'],
    'nbt client socket address' => ['socket address'],
    'path' => ['directory'],
    'preexec' => ['exec'],
    'preferred master' => ['prefered master'],
    'preload' => ['auto services'],
    'printable' => ['print ok'],
    'printcap name' => ['printcap'],
    'printer name' => ['printer'],
    'private dir' => ['private directory'],
    // write ok is synonymous with read only? this has to be a bug....
    //  'read only'        => array('write ok'),
    'root directory' => ['root', 'root dir'],
    'server max protocol' => ['max protocol', 'protocol'],
    'server min protocol' => ['min protocol'],
    'username' => ['user', 'users'],
    'vfs objects' => ['vfs object'],
    'writeable' => ['writable'],
    ];
}

/**
 * @return \string[][]
 */
function samba_variables_alias_inverted()
{
    return [
    'disable spoolss' => ['enable spoolss'],
    'read only' => ['writeable'],
    ];
}
