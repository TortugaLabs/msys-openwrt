<?php
//
// Macros for configuring TL-WR1042NDv1
//
function config_led() {
  return "config led 'led_usb'
	option name 'USB'
	option sysfs 'tp-link:green:usb'
	option trigger 'usbdev'
	option dev '1-1'
	option interval '50'

config led 'led_wlan'
	option name 'WLAN'
	option sysfs 'tp-link:green:wlan'
	option trigger 'phy0tpt'
\n\n";
}

function config_swcfg() {
  $txt = 'fixfile --mode=755 /usr/bin/swcfg <<\'EOF\''.NL;
  $txt .= '#!/bin/sh'.NL;
  $txt .= 'exec swconfig dev switch0 "$@"'.NL;
  $txt .= NL.'EOF'.NL;
  return $txt;
}

function config_network($host = NULL) {
  global $cf;

  $txt = 'fixfile /etc/config/network <<EOF'.NL;
  $txt .= config_loopback();

  if (!isset($host)) $host = SYSNAME;
  if (isset($cf['hosts'][$host])) {
    $hdat = &$cf['hosts'][$host];
    if (isset($hdat['nics'])) {
      foreach (array_keys($hdat['nics']) as $ifname) {
	$txt .= config_network_interface($host,$ifname);
      }
    }
    if (isset($hdat['switch'])) {
      $txt .= config_switch($hdat['switch']);
    }
  }
  $txt .= NL.'EOF'.NL;
  $txt .= NL.'restart network /etc/config/network'.NL;
  $txt .= NL;

  $txt .= config_swcfg();

  //#config globals 'globals'
  //#	option ula_prefix 'fdce:32ed:3796::/48'

  return $txt;
}


function config_network_interface($host,$ifname) {
  $addr = lk_addr($host,$ifname);
  if (!count($addr)) return '';

  $proto = 'dhcp';
  if (isset($addr[0]['hostid'])) {
	  if ($addr[0]['hostid'])
		  $proto = 'static';
	  elseif ($addr[0]['hostid'] !== "")
		  $proto = 'non-addr';
  }

  global $cf;
  $txt = 'config interface '.$ifname.NL;
  $vlan = res('vlan',$ifname);

  if (!$vlan) {
    trigger_error('Interface '.$ifname.' missing VLAN configuration',
		  E_USER_WARNING);
  } else {
    $txt .= '    option ifname eth0.'.$vlan.NL;
    switch ($proto) {
    case 'static':
      $txt .= '    option force_link 1'.NL;
      $txt .= '    option proto static'.NL;

      $ip = res('ip4',$ifname);
      if ($ip) {
	$txt .= '    option ipaddr '.$ip.NL;
	$txt .= '    option netmask 255.255.255.0'.NL;
	$ip = lk_gw('ip4',$ifname);
	if ($ip) $txt .= '    option gateway '.$ip.NL;
      }
      $ip = res('ip6',$ifname);
      if ($ip) {
	$txt .= '    option ip6addr '.$ip.'/64'.NL;
	$ip = lk_gw('ip6',$ifname);
	if ($ip) $txt .= '    option ip6gw '.$ip.NL;
      }
      break;
	 case 'non-addr':
		 break;
    case 'dhcp':
    default:
      $txt .= '    option proto dhcp'.NL;
      break;
    }
    if (isset($cf['hosts'][SYSNAME]['nics'][$ifname]['wifi'])) {
      if ($cf['hosts'][SYSNAME]['nics'][$ifname]['wifi']) {
	$txt .= '    option type bridge'.NL;
      }
    }
    if (isset($cf['hosts'][SYSNAME]['nics'][$ifname]['opts'])) {
      $xopts = $cf['hosts'][SYSNAME]['nics'][$ifname]['opts'];
      if (!is_array($xopts)) $xopts = array($xopts);
      foreach ($xopts as $ln) {
	$txt .= '    option '.$ln.NL;
      }
    }
  }
  $txt .= NL;
  return $txt;
}


//
function config_switch($ports) {
  $txt = '';
  $txt .= 'config switch'.NL;
  $txt .= '    option name switch0'.NL;
  $txt .= '    option reset 1'.NL;
  $txt .= '    option enable_vlan 1'.NL;
  $txt .= '    option enable_vlan4k 1'.NL;
  $txt .= NL;

  global $cf;
  $vlan = array();
  foreach ($cf['nets'] as $netname=>&$netdat) {
    if (!isset($netdat['vlan'])) continue;
    if (isset($vlan[$netdat['vlan']])) {
      trigger_error('Redefining vlan '.$netdat['vlan'].' in '.$netname.
		    ' and in '.$vlan[$netdat['vlan']]['netname'],
		    E_USER_WARNING);
      continue;
    }
    $vlan[$netdat['vlan']] = array('netname' => $netname,
				   'ports' => array('5'=>'5t'));
  }
  foreach ($ports as $pid => $vl) {
    if ($vl == '*trunk*') {
      foreach ($vlan as $vlid => &$vlr) {
	$vlr['ports'][$pid] = $pid.'t';
	if (!defined('ADMIN_VLAN')) continue;
	if (ADMIN_VLAN == $vlid) $vlr['ports'][$pid] = $pid;
      }
    } else {
      if (!isset($cf['nets'][$vl])) {
	trigger_error($pid.' is referencing unknown VLAN '.$vl,E_USER_WARNING);
	continue;
      }
      if (!isset($cf['nets'][$vl]['vlan'])) {
	trigger_error($vl.' used in '.$pid.' missing VLAN id',E_USER_WARNING);
	continue;
      }
      $vlan[$cf['nets'][$vl]['vlan']]['ports'][$pid] = $pid;
    }
  }

  foreach ($vlan as $vlid => $vldat) {
    if (count($vldat['ports']) == 0) continue;
    $txt .= '# '.$vldat['netname'].NL;
    $txt .= 'config switch_vlan'.NL;
    $txt .= '    option device "switch0"'.NL;
    $txt .= '    option vlan '.$vlid.NL;
    $txt .= '    option ports "'.implode(' ',$vldat['ports']).'"'.NL;
    $txt .= NL;
  }

  // Add any default port ids...
  if (defined('ADMIN_VLAN')) {
    foreach ($ports as $pid => $vl) {
      if ($vl != '*trunk*') continue;
      $txt .= 'config switch_port'.NL;
      $txt .= '    option port '.$pid.NL;
      $txt .= '    option pvid '.ADMIN_VLAN.NL;
      $txt .= NL;
    }
  }


  return $txt;
}

function config_wireless($opts = NULL) {
  global $cf;

  if (isset($opts['host'])) {
    $host = $opts['host'];
    unset($opts['host']);
  } else {
    $host = SYSNAME;
  }
  // If not found we skip most of this
  if (!isset($cf['hosts'][$host])) return '';

  $disabled = 0;

  if (isset($cf['hosts'][$host]['wchan'])) {
    $wchan = $cf['hosts'][$host]['wchan'];
  } else {
    $wchan = 1;
    $disabled = 1;
  }

  if (!isset($opts)) $opts = array();

  foreach (array('hwmode'=>'11g',
		 'path'=>"'platform/ath9k'",
		 'htmode' => 'HT20',
		 'type'=>'mac80211',
		 'channel'=>$wchan,
		 'disabled' => $disabled) as $opt=>$def) {
    if (!isset($opts[$opt])) $opts[$opt] = $def;
  }

  $txt = 'fixfile /etc/config/wireless <<EOF'.NL;
  $txt .='config wifi-device radio0'.NL;
  foreach ($opts as $k=>$v) {
    $txt .= '    option '.$k.' '.$v.NL;
  }
  $txt .= NL;

  $hdat = &$cf['hosts'][$host];
  if (isset($hdat['nics'])) {
    foreach ($hdat['nics'] as $ifnam => &$ifdat) {
      if (!isset($ifdat['wifi'])) continue;
      if (!$ifdat['wifi']) continue;
      $netid = res('netid',$host,$ifnam);
      if (!$netid) continue;
      if (!isset($cf['nets'][$netid])) continue;
      $netdat = &$cf['nets'][$netid];
      $txt .= 'config wifi-iface'.NL;
      $txt .= '    option device radio0'.NL;
      $txt .= '    option network '.$ifnam.NL;
      $txt .= '    option mode ap'.NL;
      foreach (array('ssid','key','encryption') as $v) {
	if (isset($netdat[$v]))
	  $txt.= '    option '.$v.' \''.$netdat[$v].'\''.NL;
      }
      $txt .= NL;
    }
  }

  $txt .= NL.'EOF'.NL;
  $txt .= 'restart network /etc/config/wireless'.NL;
  $txt .= NL;

 return $txt;
}
