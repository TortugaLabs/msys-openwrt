<?php
//
// Router based macros
//
function dump_hosts($hosts,$domain) {
  $txt = '';
  
  $domain = '.'.$domain;

  foreach ($hosts as $hn=>$hdat) {
    $aliases = isset($hdat['aliases']) ? $hdat['aliases'] : [];
    
    if (isset($hdat['nics']) && is_array($hdat['nics'])) {
      if (count($hdat['nics']) >1) {
	array_unshift($aliases,$hn); // More than one NIC, create an alias for the main name
      }
      foreach ($hdat['nics'] as $ifname=>&$ifdat) {
	foreach (['ipv4','ipv6'] as $atype) {
	  if (!isset($ifdat[$atype])) continue;
	  $txt .= $ifdat[$atype]."\t";
	  if (count($hdat['nics']) == 1) {
	    $txt .= $hn.$domain;
	  } else {
	    $txt .= $hn.'-'.$ifname.$domain;
	  }
	  if (count($aliases) > 0) {
	    $txt .= ' '.implode(' ',$aliases);
	  }
	  $txt .= PHP_EOL;
	}
      }
    } else {
      foreach (['ipv4','ipv6'] as $atype) {
	if (!isset($hdat[$atype])) continue;
	foreach ($hdat[$atype] as $addr) {
	  $txt .= $addr."\t".$hn.$domain;
	  if (count($aliases) > 0) $txt .= ' '.implode(' ',$aliases);
	}
	$txt .= PHP_EOL;
      }
    }
  }
  return $txt;
}


function dump_ethers($hosts) {
  $txt = '';
  foreach ($hosts as $hn => $hdat) {
    if (isset($hdat['nics'])) {
      $count = 0;
      foreach ($hdat['nics'] as $ifname=>&$ifdat) {
	if (!isset($ifdat['mac'])) continue;
	++$count;
	$txt .= $ifdat['mac']."\t".$hn.'-'.$ifnam.PHP_EOL;
      }
    }
    if ($count > 0) continue;
    if (!isset($hdat['mac'])) continue;
    $macs = is_array($hdat['mac']) ? $hdat['mac'] : [$hdat['mac']];
    if (count($macs) == 1) {
      $txt .= $macs[0]."\t".$hn.PHP_EOL;
    } else {
      $i = 0;
      foreach ($macs as $m) {
	$txt .= $m."\t".$hn.'-p'.($i++).PHP_EOL;
      }
    }
  }
  return $txt;
}

function dhcp_hostsfile($hosts) {
  $txt = '';
  foreach ($hosts as $hn => $hdat) {
    $macs = [];
    if (isset($hdat['nics'])) {
      $count = 0;
      
      foreach ($hdat['nics'] as $ifname=>&$ifdat) {
	if (!isset($ifdat['mac'])) continue;
	$macs[$ifdat['mac']] = (isset($ifdat['ipv4']) ? $ifdat['ipv4'].',' : '')
		  . $hn;
      }
    }
    if (isset($hdat['mac'])) {
      if (is_array($hdat['mac'])) {
	foreach ($hdat['mac'] as $m) {
	  if (!isset($macs[$m])) $macs[$m] = $hn;
	}
      } else {
	if (!isset($macs[$hdat['mac']])) $macs[$hdat['mac']] = $hn;
      }
    }
    if (!count($macs)) continue;
    
    $rules = [];
    foreach ($macs as $m=>$k) {
      if (!isset($rules[$k])) $rules[$k] = [];
      $rules[$k][] = $m;
    }
    
    foreach ($rules as $k=>$m) {
      $txt .= implode(',',$m).','.$k.PHP_EOL;
    }
  }
  return $txt;
}


function _config_dhcp_interface($ifnam,$ifdat,$nets) {
  if (!isset($ifdat['net'])) return '';
  if (!isset($nets[$ifdat['net']])) return '';
  $netdat = $nets[$ifdat['net']];
  $txt = '';
  if (isset($netdat['ipv6'])) {
    $txt .= '	option ra server'.PHP_EOL;
    $txt .= '	option dhcpv6 server'.PHP_EOL;
  }
  if (isset($netdat['dhcp'])) {
    if (isset($netdat['dhcp']['range'])) {
      list($start,$end) = $netdat['dhcp']['range'];
      $txt .= '	option start '.$start.PHP_EOL;
      $txt .= '	option limit '.($end-$start).PHP_EOL;
    }
    if ($netdat['dhcp']['lease']) {
      $txt .= '	option leasetime '.$netdat['dhcp']['lease'].PHP_EOL;
    }
  }
  return $txt;
}



function config_dhcp($nics,$nets) {
  $txt = '';
  foreach ($nics as $ifnam => $ifdat) {
    $txt .= 'config dhcp '.$ifnam.PHP_EOL;
    $txt .= '    option interface '.$ifnam.PHP_EOL;
    $x = _config_dhcp_interface($ifnam,$ifdat,$nets);
    if ($x == '') {
      $txt .= '    option ignore 1'.PHP_EOL;
      $txt .= PHP_EOL;
    } else {
      $txt .= $x.PHP_EOL;
    }
  }
  return $txt;
}
