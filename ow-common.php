<?php
  std_check(); // From Macros.php

  define('MSYS_OPENWRT',__DIR__.'/');
  
  if (!defined('BRIEF_OUTPUT')) {
    require_once(MSYS_OPENWRT.'ow-check.sh');
    require_once(MSYS_OPENWRT.'bblib.sh');
    require_once(MSYS_OPENWRT.'services.sh');
    require_once(MSYS_OPENWRT.'swinst.sh');    
  }
  require_once(MSYS_OPENWRT.'core.sh');

  foreach (glob(MSYS_OPENWRT.'utils/*') as $in) {
    echo std_copyfile($in,'/bin/'.basename($in),[
      'mode'=>755,
      STD_COPYFILE_QUOTED,
    ]);
  }
