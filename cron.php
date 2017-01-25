<?php
//
// Crontab functionality
//
$ow_crond = [];

function add_crontab($usr,$txt) {
  global $ow_crond;
  if (isset($ow_crond[$usr])) {
    $ow_crond[$usr][] = $txt;
  } else {
    $ow_crond[$usr] = [$txt];
  }
}

function crontab_config() {
  global $ow_crond;
  if (!count($ow_crond)) return '';
  $count = 0;
  $txt = '# m_write_crontab'.PHP_EOL;
  $restart_line = 'restart cron';
  foreach ($ow_crond as $usr => &$tab) {
    $count++;
    $txt .= 'fixfile --nobackup /etc/crontabs/'.$usr.' <<EOF'.PHP_EOL;
    $txt .= implode(PHP_EOL,$tab).PHP_EOL;
    $txt .= PHP_EOL.'EOF'.PHP_EOL;
    $restart_line .= ' /etc/crontabs/'.$usr;
  }
  if ($count) {
    return $txt.PHP_EOL.$restart_line.PHP_EOL;
  }
  return '';
}

post_code('crontab_config();'.PHP_EOL);
