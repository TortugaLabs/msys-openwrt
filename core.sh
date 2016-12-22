#
# Default pkg installation
#
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

# configure munin
restart xinetd /etc/xinet.d/munin
<?php } ?>

