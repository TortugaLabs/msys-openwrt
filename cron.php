<?php
//
// Crontab functionality
//
$ow_crond = array();

function add_crontab($usr,$txt) {
  global $ow_crond;
  if (isset($ow_crond[$usr])) {
    $ow_crond[$usr][] = $txt;
  } else {
    $ow_crond[$usr] = array($txt);
  }
}

function crontab_config() {
  global $ow_crond;
  if (!count($ow_crond)) return '';
  $count = 0;
  $txt = '# m_write_crontab'.NL;
  $restart_line = 'restart cron';
  foreach ($ow_crond as $usr => &$tab) {
    $count++;
    $txt .= 'fixfile --nobackup /etc/crontabs/'.$usr.' <<EOF'.NL;
    $txt .= implode(NL,$tab).NL;
    $txt .= NL.'EOF'.NL;
    $restart_line .= ' /etc/crontabs/'.$usr;
  }
  if ($count) {
    return $txt.NL.$restart_line.NL;
  }
  return '';
}
