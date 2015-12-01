<?php

function fw_zone() {
  $cnt = func_num_args();
  if ($cnt < 2) {
    trigger_error('fw_zone: syntax error',E_USER_ERROR);
    return '';
  }
  $zname = func_get_arg(0);
  $nets = func_get_arg(1);

  $txt = 'config zone'.NL;
  $txt .= '    option name '.$zname.NL;

  $nets = preg_split('/\s*,\s*/',$nets);
  foreach ($nets as $li) {
    $txt .= '    list network \''.$li.'\''.NL;
  }
  for ($i=2;$i < func_num_args();$i++) {
    $opt = func_get_arg($i);
    if (substr($opt,0,1) == '@') {
      $txt .= '    list '.substr($opt,1).NL;
    } else {
      $txt .= '    option '.$opt.NL;
    }
  }
  $txt.=NL;
  return $txt;
}

function fw_fwd($src,$dst,$bidir=0) {
  global $zones;
  $txt = 'config forwarding'.NL;
  $txt .= '	option src '.$src.NL;
  $txt .= '	option dest '.$dst.NL;
  $txt .= NL.NL;
  if ($bidir) $txt .= fw_fwd($dst,$src,0);
  return $txt;
}

function fw_bifwd($src,$dst) {
  return fw_fwd($src,$dst,1);
}


function fw_cfgv($rtype,$rargs) {
  if ($rtype == 'redir') $rtype = 'redirect';

  $tx = 'config '.$rtype.NL;
  $opts = array();
  $family = 'any';
  $src_mac = false;
  global $hosts;

  foreach ($rargs as $op) {
    switch ($op) {
    case 'tcp':
    case 'udp':
    case 'icmp':
      $tx .= '  option proto '.$op.NL;
      break;
    case 'ACCEPT':
    case 'REJECT':
    case 'DROP':
    case 'DNAT':
    case 'SNAT':
      $tx .= '  option target '.$op.NL;
      break;
    case 'ipv4':
    case 'ipv6':
      $family = $op;
      break;
    default:
      $pair = preg_split('/\s+/',$op,2);
      if (count($pair) != 2) {
	trigger_error("fwcfg($op) is invalid",E_USER_WARNING);
	return $tx.NL.'#***** ERROR! INCOMPLETE RULE! *****'.NL;
      }
      list($k,$v) = $pair;
      if (in_array($k,array('src_ip','src_dip','dest_ip'))) {
	$opts[$k] = $v;
      } elseif ($k == 'family') {
	$family = $v;
      } elseif ($k == 'src_mac') {
	$src_mac = $v;
      } elseif (substr($k,0,1) == '@') {
	$tx .= '  list '.substr($k,1).' '.$v.NL;
      } else {
	$tx .= '  option '.$op.NL;
      }
    }
  }

  $tx .= '  option family '.$family.NL;
  if ($src_mac || count($opts)) {
    $tpl = array($tx);
    if ($src_mac) $tpl = _fw_merge(__fw_expand_macs($src_mac),$tpl);
    foreach ($opts as $k=>$v) {
      $tpl = _fw_merge(_fw_expand_ips($k,$v,$family),$tpl);
    }
    $tx = implode(NL.NL,$tpl);
  }
  $tx .= NL;
  return $tx;
}

function _fw_merge($new,$tpl) {
  if (count($new) == 0) return $tpl;
  $res = array();
  foreach ($tpl as $txold) {
    foreach ($new as $post) {
      $res[] = $txold.$post;
    }
  }
  return $res;
}

function _fw_add_ips($opt,$addr,$fam,&$res) {
  list($netid,$hostid) = parse_ipnet($addr);
  if ($netid) {
    global $cf;
    if (!isset($cf['nets'][$netid])) {
      trigger_error("$opt($addr) unknown network",E_USER_WARNING);
      return;
    }
    if (!$hostid) {
      // Assume we refer to the full subnet
      if ($fam == 'ipv6' || $fam == 'any') {
	if (isset($cf['nets'][$netid]['ip6'])) {
	  $res[] = '  option '.$opt.' '.$cf['nets'][$netid]['ip6'].'::/64'.NL;
	} elseif ($fam == 'ipv6') {
	  trigger_error("$opt($addr) Not an IPv6 network",E_USER_WARNING);
	}
      }
      if ($fam == 'ipv4' || $fam == 'any') {
	if (isset($cf['nets'][$netid]['ip4'])) {
	  $res[] = '  option '.$opt.' '.$cf['nets'][$netid]['ip4'].'.0/24'.NL;
	} elseif ($fam == 'ipv4') {
	  trigger_error("$opt($addr) Not an IPv4 network",E_USER_WARNING);
	}
      }
      return;
    }
    if ($fam == 'ipv6' || $fam == 'any') {
      if (isset($cf['nets'][$netid]['ip6'])) {
	$res[] = '  option '.$opt.' '.$cf['nets'][$netid]['ip6'].'::'.
	  dechex($hostid).NL;
      } elseif ($fam == 'ipv6') {
	trigger_error("$opt($addr) Not an IPv6 network",E_USER_WARNING);
      }
    }
    if ($fam == 'ipv4' || $fam == 'any') {
      if (isset($cf['nets'][$netid]['ip4'])) {
	$res[] = '  option '.$opt.' '.$cf['nets'][$netid]['ip4'].'.'.
	  $hostid.NL;
      } elseif ($fam == 'ipv4') {
	trigger_error("$opt($addr) Not an IPv4 network",E_USER_WARNING);
      }
    }
    return;
  }
  trigger_error("Invalid netaddr definition $addr",E_USER_WARNING);
}



function _fw_expand_ips($opt,$ipspec,$fam) {
  $res = array();

  if (substr($ipspec,0,4) == 'net:') {
    _fw_add_ips($opt,$ipspec,$fam,$res);
    return $res;
  }
  if (($fam == 'ipv6' || $fam == 'any') && is_ipv6($ipspec)) {
    $res[] = '  option '.$opt.' '.$addr.NL;
    return $res;
  }
  if (($fam == 'ipv4' || $fam == 'any') && is_ipv4($ipspec)) {
    $res[] = '  option '.$opt.' '.$addr.NL;
    return $res;
  }

  $nic = NULL;
  $hn = $ipspec;

  if ($p = strrpos($ipspec,'-')) {
    if ($p) {
      $nic = substr($ipspec,$p+1);
      $hn = substr($ipspec,0,$p);
    }
  }
  foreach (lk_addr($hn,$nic) as $addr) {
    if (($fam == 'ipv4' || $fam == 'any') && isset($addr['ip4'])) {
      $res[] = '  option '.$opt.' '.$addr['ip4'].NL;
    }
    if (($fam == 'ipv6' || $fam == 'any') && isset($addr['ip6'])) {
      $res[] = '  option '.$opt.' '.$addr['ip6'].NL;
    }
  }
  if (!count($res)) {
    // trigger_error("Unable to lookup ipspec: $ipspec",E_USER_WARNING);
    $res[] = '  option '.$opt.' '.$ipspec.NL;
  }
  return $res;
}

function fw_rule() {
  $cnt = func_num_args();
  if (!$cnt) {
    trigger_error("m_fwcfg: syntax error",E_USER_ERROR);
    return '';
  }
  $args = array();
  for ($i=0;$i<func_num_args();$i++) {
    $args[] = func_get_arg($i);
  }
  return fw_cfgv('rule',$args);
}
function fw_redir() {
  $cnt = func_num_args();
  if (!$cnt) {
    trigger_error("m_fwcfg: syntax error",E_USER_ERROR);
    return '';
  }
  $args = array();
  for ($i=0;$i<func_num_args();$i++) {
    $args[] = func_get_arg($i);
  }
  return fw_cfgv('redirect',$args);
}

function _fw_expand_macs($h) {
  $macs = array();
  global $hosts;

  $nic = false;
  $hn = $h;

  if ($p = strrpos($h,'-')) {
    if ($p) {
      $nic = substr($h,$p+1);
      $hn = substr($h,0,$p);
    }
  }

  foreach (lk_addr_item('mac',$hn,$nic) as $mc) {
    $macs[] = '  option src_mac '.$mc.NL;
  }


  if (!isset($hosts[$hn])) {
    $macs[] = '    option src_mac '.$h.NL;
    return $macs;
  }

  if (count($macs) == 0) {
    trigger_error("$h: Has no MAC's defined",E_USER_WARNING);
  }
  return $macs;
}
