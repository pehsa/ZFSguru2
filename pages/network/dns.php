<?php

function content_network_dns()
{
 // required library
 activate_library('dnsmasq');

 // dnsmasq configuration
 $dnsmasq = dnsmasq_readconfig();

 // tables
 $table['dns']['string'] = array();
 $table['dns']['switch'] = array();

 // populate tables
 $servicetype = 'dns';
 if (@is_array($dnsmasq[$servicetype]))
  foreach ($dnsmasq[$servicetype] as $datatype => $dataarray)
   if (is_array($dataarray))
    foreach ($dataarray as $configvar)
     $table[$servicetype][$datatype][$configvar] = array(
      strtoupper($servicetype).'_'.strtoupper($datatype).'_NAME' => 
       htmlentities($configvar),
      strtoupper($servicetype).'_'.strtoupper($datatype).'_VALUE' => 
       @htmlentities($dnsmasq[$servicetype][$datatype][$configvar]),
     );

 // export tags
 return array(
  'PAGE_TITLE'		=> 'DNS',
  'PAGE_ACTIVETAB'	=> 'DNS',
  'TABLE_DNS_STRING'	=> $table['dns']['string'],
  'TABLE_DNS_SWITCH'	=> $table['dns']['switch'],
 );
}

