<?php
//
// Macros for configuring TL-WR1042NDv1
//
// Expects that the config array contains NIC configs as:
//
// 'hosts.$sysname.nics.$nicname'
//
// Networks are under 'nets'
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
  $txt = 'fixfile --mode=755 /usr/bin/swcfg <<\'EOF\''.PHP_EOL;
  $txt .= '#!/bin/sh'.PHP_EOL;
  $txt .= 'exec swconfig dev switch0 "$@"'.PHP_EOL;
  $txt .= PHP_EOL.'EOF'.PHP_EOL;
  return $txt;
}

function config_network_interfaces($ifdat) {
  $txt = '';
  foreach ($ifdat as $if => $dat) {
    $txt .= config_network_if($if,$dat);
  }
  return $txt;
}

function config_network_if($ifname,$ifdat) {
  $txt = '';
  $txt .= 'config interface '.$ifname.PHP_EOL;

  $proto = 'dhcp';

  if (isset($ifdat['ipv4']) || isset($ifdat['ipv6'])) {
    $proto = 'static';
  } else {
    if (isset($ifdat['node']) && ($ifdat['node'] == 0 || $ifdat['node'] == ''))
      $proto = 'no-addr';
  }

  if (isset($ifdat['vlan']) && filter_var($ifdat['vlan'],FILTER_VALIDATE_INT)) {
    $txt .= '	option ifname eth0.'.$ifdat['vlan'].PHP_EOL;
    switch ($proto) {
    case 'static':
      $txt .= '	option force_link 1'.PHP_EOL;
      $txt .= '	option proto static'.PHP_EOL;

      if (isset($ifdat['ipv4'])) {
	$txt .= '	option ipaddr '.$ifdat['ipv4'].PHP_EOL;
	$txt .= '	option netmask 255.255.255.0'.PHP_EOL;
	if (isset($ifdat['gw'])) {
	  $i = $ifdat['gw'];
	} elseif (isset($ifdat['node']) && $ifdat['node'] > 1) {
	  $i = explode('.',$ifdat['ipv4']);
	  $i[3] = 1;
	  $i = implode('.',$i);
	}
	if ($i != $ifdat['ipv4']) $txt .= '	option gateway '.$i.PHP_EOL;

      }
      if (isset($ifdat['ipv6'])) {
	$txt .= '	option ip6addr '.$ifdat['ipv6'].'/64'.PHP_EOL;
	if (isset($ifdat['ipv6gw'])) {
	  $i = $ifdat['ipv6gw'];
	} elseif (isset($ifdat['node']) && $ifdat['node'] > 1) {
	  $i = explode('::',$ifdat['ipv6']);
	  $i = $i[0].'::1';
	}
	if ($i != $ifdat['ipv6']) $txt .= '	option ip6gw '.$i.PHP_EOL;
      }
      break;
    case 'no-addr':
      break;
    case 'dhcp':
    default:
      $txt .= '    option proto dhcp'.PHP_EOL;
      break;
    }
  }
  if (isset($ifdat['wifi'])) {
    $txt .= '	option type bridge'.PHP_EOL;
  }
  if (isset($ifdat['opts']) && is_arra($ifdat['opts'])) {
    foreach ($ifdat['opts'] as $i=>$j) {
	$txt .= '	option '.$i.' '.$j.PHP_EOL;
    }
  }
  $txt .= PHP_EOL.PHP_EOL;
  return $txt;
}

function config_switch($ports,$nets) {
  $txt = '';
  $txt .= 'config switch'.PHP_EOL;
  $txt .= '    option name switch0'.PHP_EOL;
  $txt .= '    option reset 1'.PHP_EOL;
  $txt .= '    option enable_vlan 1'.PHP_EOL;
  $txt .= '    option enable_vlan4k 1'.PHP_EOL;
  $txt .= PHP_EOL;

  $vlan = [];
  foreach ($nets as $netname=>$netdat) {
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
      if (!isset($nets[$vl])) {
	trigger_error($pid.' is referencing unknown VLAN '.$vl,E_USER_WARNING);
	continue;
      }
      if (!isset($nets[$vl]['vlan'])) {
	trigger_error($vl.' used in '.$pid.' missing VLAN id',E_USER_WARNING);
	continue;
      }
      $vlan[$nets[$vl]['vlan']]['ports'][$pid] = $pid;
    }
  }

  foreach ($vlan as $vlid => $vldat) {
    if (count($vldat['ports']) == 0) continue;
    $txt .= '# '.$vldat['netname'].PHP_EOL;
    $txt .= 'config switch_vlan'.PHP_EOL;
    $txt .= '    option device "switch0"'.PHP_EOL;
    $txt .= '    option vlan '.$vlid.PHP_EOL;
    $txt .= '    option ports "'.implode(' ',$vldat['ports']).'"'.PHP_EOL;
    $txt .= PHP_EOL;
  }

  // Add any default port ids...
  if (defined('ADMIN_VLAN')) {
    foreach ($ports as $pid => $vl) {
      if ($vl != '*trunk*') continue;
      $txt .= 'config switch_port'.PHP_EOL;
      $txt .= '    option port '.$pid.PHP_EOL;
      $txt .= '    option pvid '.ADMIN_VLAN.PHP_EOL;
      $txt .= PHP_EOL;
    }
  }
  return $txt;
}



//
function config_wifi_radio($wchan, $opts=NULL) {
  if ($wchan) {
    $disabled = 0;
  } else {
    $wchan = 1;
    $disabled = 1;
  }
  if (is_array($opts)) $opts = [];
  foreach (['hwmode'=>'11g',
		 'path'=>"'platform/ath9k'",
		 'htmode' => 'HT20',
		 'type'=>'mac80211',
		 'channel'=>$wchan,
		 'disabled' => $disabled] as $opt=>$def) {
    if (!isset($opts[$opt])) $opts[$opt] = $def;
  }
  $txt = '';
  $txt .='config wifi-device radio0'.PHP_EOL;
  foreach ($opts as $k=>$v) {
    $txt .= '    option '.$k.' '.$v.PHP_EOL;
  }
  $txt .= PHP_EOL;
  return $txt;
}

function config_wifi_nets($nics,$nets) {
  $txt = '';
  foreach ($nics as $if => $ifdat) {
    
    if (!isset($ifdat['wifi'])) continue;
    if (!$ifdat['wifi']) continue;
    if (!isset($ifdat['net'])) continue;
    $netid = $ifdat['net'];
    if (!isset($nets[$netid])) continue;
    $netdat = &$nets[$netid];
    $txt .= 'config wifi-iface'.PHP_EOL;
    $txt .= '	option device radio0'.PHP_EOL;
    $txt .= '	option network '.$if.PHP_EOL;
    $txt .= '	option mode ap'.PHP_EOL;
    foreach (['ssid','key','encryption'] as $v) {
      if (isset($netdat[$v])) $txt .= '	option '.$v.' \''.$netdat[$v].'\''.PHP_EOL;
    }
    $txt .= PHP_EOL;
  }
  return $txt;
}

