#
# Default pkg installation
#
# TODO
<?php if (FALSE) { ?>
[ $openwrt_version = "14.07" ] \
  && swinst flock pwgen diffutils ifstat tcpdump-mini muninlite

# Default services...	
[ $openwrt_version = "10.03.1" ] \
  && enable boot done dropbear led network sysctl sysntpd ubus usb watchdog
[ $openwrt_version = "12.09" ] \
  && enable luci_fixtime boot ubus network usb dropbear done led watchdog \
  sysntpd sysctl
[ $openwrt_version = "14.07" ] \
  && enable boot done dropbear led log network sysctl sysfixtime \
	    sysntpd system
	    #cron dnsmasq firewall odhcpd telnet uhttpd #umount

# TODO
[ $openwrt_version = "15.0??" ] \
  && enable boot done dropbear led log network sysctl sysfixtime \
	    sysntpd system
	    #cron dnsmasq firewall odhcpd telnet uhttpd #umount

# configure munin
restart xinetd /etc/xinet.d/munin
<?php } ?>

#!/bin/sh
#
# Misc utilities
#
cfg_sysctl() {
  local opt= l= r=
  local conf=sysctl.conf
  local orig="$([ -f $conf ] && sed 's/^/:/' $conf)"
  local text="$orig"
  
  for opt in "$@"
  do
    l="$(echo "$opt" | cut -d= -f1)"
    r="$(echo "$opt" | cut -d= -f2-)"
    if [ $(echo "$text" | grep '^:[ \t]*[#;]*[ \t]*'"$l"'[ \t]*=' | wc -l) -eq 0 ] ; then
      text="$(echo "$text" ; echo ":$opt")"
      continue
    fi
    text="$(echo "$text" | sed -e 's/:[ \t]*[#;]*[ \t]*'"$l"'[ \t]*=.*$/:'"$opt"'/')"
  done
  
  [ x"$orig" = x"$text" ] && return 1
  echo "$text" | sed 's/^://' > "$conf" && echo "$conf: updated"
  return 0
}



