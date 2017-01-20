<?php
std_check(); // From Macros.php

define('MSYS_OPENWRT',__DIR__.'/');

require_once(MSYS_OPENWRT.'tzdata.php');
if (!defined('BRIEF_OUTPUT')) {
  require_once(MSYS_OPENWRT.'ow-check.sh');
  require_once(MSYS_OPENWRT.'bblib.sh');
  require_once(MSYS_OPENWRT.'services.sh');
  require_once(MSYS_OPENWRT.'swinst.sh');
  require_once(MSYS_OPENWRT.'misc.sh');
}
echo std_copyfile(MSYS_BASE.'ashlib/shlog','/bin/shlog',['mode'=>755,STD_COPYFILE_QUOTED]);
require_once(MSYS_OPENWRT.'core.sh');

foreach (glob(MSYS_OPENWRT.'utils/*') as $in) {
  echo std_copyfile($in,'/bin/'.basename($in),[
    'mode'=>755,
    STD_COPYFILE_QUOTED,
  ]);
}

function config_loopback() {
  return "config interface 'loopback'
	option ifname 'lo'
	option proto 'static'
	option ipaddr '127.0.0.1'
	option netmask '255.0.0.0'
	#option ip6addr '::1'
\n";
}

function config_server_list($var,$cf,$pre="\tlist server ",$post=PHP_EOL) {
  $list_str = vlookup($var,$cf,[VLOOKUP_DEFAULT=>NULL]);
  if ($list_str == NULL) return '';
  $txt = '';
  foreach (preg_split('/\s+/',$list_str) as $i) {
    $txt .= $pre . $i . $post;
  }
  return $txt;
}
