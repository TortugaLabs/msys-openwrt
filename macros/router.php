<?php
//
// Router based macros
//

function config_dhcp_interface($ifname) {
  $txt = 'config dhcp '.$ifname.NL;

  $addr = lk_addr(SYSNAME,$ifname);
  if (count($addr) != 1) return '';
  $addr = $addr[0];
  if (!isset($addr['netid']) || !isset($addr['hostid'])) return '';

  global $cf;

  if (!isset($cf['nets'][$addr['netid']])) return '';
  $netdat = $cf['nets'][$addr['netid']];
  if (!isset($netdat['dhcp'])) return '';

  $txt .= '    option interface '.$ifname.NL;
  $txt .= '    option ra server'.NL;
  $txt .= '    option dhcpv6 server'.NL;
  if ($netdat['dhcp']['range']) {
    list($start,$end) = $netdat['dhcp']['range'];
    $txt .= '    option start '.$start.NL;
    $txt .= '    option limit '.($end-$start).NL;
  }
  if ($netdat['dhcp']['lease']) {
    $txt .= '    option leasetime '.$netdat['dhcp']['lease'].NL;
  }
  return $txt;
}

function config_dhcp() {
  global $cf;
  $txt = '';
  foreach (array_keys($cf['hosts'][SYSNAME]['nics']) as $ifnam) {
    $txt .= '# '.$ifnam.NL;
    $x = config_dhcp_interface($ifnam);
    if ($x == '') {
      $txt .= 'config dhcp '.$ifnam.NL;
      $txt .= '    option interface '.$ifnam.NL;
      $txt .= '    option ignore 1'.NL;
      $txt .= NL;
    } else {
      $txt .= $x.NL;
    }
  }
  return $txt;
}

function dhcp_hostsfile() {
  global $cf;
  $txt = '';
  foreach ($cf['hosts'] as $hn => $hdat) {
    $addrlst = lk_addr($hn);
    $count = 0;
    foreach ($addrlst as $addr) {
      if (isset($addr['ip4']) && isset($addr['mac'])) {
	$count++;
	$txt .= $addr['mac'].','.$addr['ip4'].','.$hn.NL;
      }
    }
    if ($count) continue;
    $macs = array();
    if (isset($hdat['mac'])) $macs[] = $hdat['mac'];
    if (isset($hdat['nics'])) {
      foreach ($hdat['nics'] as $ifnam => $ifdat) {
	if (!isset($ifdat['mac'])) continue;
	$macs[] = $ifdat['mac'];
      }
    }
    if (count($macs)) $txt .= implode(',',$macs).','.$hn.NL;
  }
  return $txt;
}

/*
function dump_ethers() {
  global $cf;
  $txt = '';
  foreach ($cf['hosts'] as $hn => $hdat) {
    $first = true;
    if (isset($hdat['mac'])) {
      $txt .= $hdat['mac'].TAB.$hn.NL;
      $first = false;
    }
    if (isset($hdat['nics'])) {
      foreach ($hdat['nics'] as $ifnam => $ifdat) {
	if (isset($ifdat['mac'])) {
	  $txt .= $ifdat['mac'].TAB.$hn;
	  if ($first) {
	    $first = false;
	  } else {
	    $txt .= '-'.$ifnam;
	  }
	  $txt .= NL;
	}
      }
    }
  }
  return $txt;
}
*/
function dump_ethers() {
  global $cf;
  $txt = '';
  foreach ($cf['hosts'] as $hn => $hdat) {
    $first = true;
    if (isset($hdat['mac'])) $txt .= $hdat['mac'].TAB.$hn.NL;
    if (isset($hdat['nics'])) {
      foreach ($hdat['nics'] as $ifnam => $ifdat) {
	if (isset($ifdat['mac'])) $txt .= $ifdat['mac'].TAB.$hn.'-'.$ifnam.NL;
      }
    }
  }
  return $txt;
}

function dump_hosts() {
  global $cf;
  $txt = '';
  foreach ($cf['hosts'] as $hn=>$hdat) {
    if (isset($hdat['alias'])) {
      $aliases = $hdat['alias'];
    } else {
      $aliases = array();
    }
    foreach (lk_addr($hn) as $addr) {
      if (isset($addr['ip4'])) $txt.=dump_host($addr['ip4'],$addr,$hn,$aliases);
      if (isset($addr['ip6'])) $txt.=dump_host($addr['ip6'],$addr,$hn,$aliases);
    }
  }
  return $txt;
}

function dump_host($ip,$addr,$hn,$aliases) {
  global $cf;
  $domain = '.'.$cf['globs']['domain'];
  $txt = $ip.TAB;
  if (isset($addr['logical'])) {
    if ($addr['logical'] != $hn) {
      $txt .= $addr['logical'].$domain.' '.$hn.$domain.' ';
    } else {
      $txt .= $hn.$domain.' ';
    }
  } else {
    $txt .= $hn.$domain.' ';
  }
  $txt .= $hn;

  if (count($aliases)) $txt .= ' '.implode(' ',$aliases);
  $txt .= NL;
  return $txt;
}



?>
