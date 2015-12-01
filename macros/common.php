<?php
if (isset($cf['globs']['admin_vlan'])) {
   define('ADMIN_VLAN',$cf['globs']['admin_vlan']);
}
define('INSTPKGDIR','/usr/localpkgs/');

require_once('openwrt/macros/tzdata.php');

function config_loopback() {
  return "config interface 'loopback'
	option ifname 'lo'
	option proto 'static'
	option ipaddr '127.0.0.1'
	option netmask '255.0.0.0'
\n";
}

function config_ntp_servers($ntpsrv) {
  $txt = '';
  foreach (lk_addr_item('hostname',$ntpsrv) as $srv) {
	 $txt .= '    list server '.$srv.NL;
  }
  return $txt;
}


function sysctl_config($opts) {
  $txt = 'fixfile --filter /etc/sysctl.conf <<\'EOF\''.NL;
  $txt .= 'sed';
  foreach ($opts as $var => $val) {
    $txt .= '  \\'.NL.' -e \'s/^'.$var.'=.*/'.$var.'='.$val.'/\'';
  }
  $txt .= NL.NL.'EOF'.NL;
  $txt .= 'restart sysctl /etc/sysctl.conf'.NL;
  return $txt;
}

function fixfile_inc($src,$dst,$opts=null) {
   // Make globals variable in this context...
   foreach ($GLOBALS as $i=>$j) {
      eval('global $'.$i.';');
   }
   $txt = NL.'fixfile';
   if (is_array($opts)) {
      foreach ($opts as $i=>$j) {
	 if (is_numeric($i)) {
	    $txt .= ' --'.$j;
	 } else {
	    $txt .= ' --'.$i.'='.$j;
	 }
      }
   }
   $txt .= ' '.$dst.' <<'.QEOFMARK.NL;

   ob_start();
   require($src);
   $txt .= ob_get_clean();

   $txt .= EOFLINE;
   return $txt;
}
?>
