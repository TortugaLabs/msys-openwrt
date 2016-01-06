#
# Basic Openwrt stuff
#
umask 022
[ ! -f /etc/openwrt_version ] \
    && fatal "Invalid device type (missing openwrt_version)"
openwrt_version=$(cat /etc/openwrt_version)
[ ! -f /etc/openwrt_release ] \
    && fatal "Invalid device type (missing openwrt_release)"
. /etc/openwrt_release

MSYS_MAC="<?= res('mac') ?>"

if [ -n "$MSYS_MAC" ] ; then
  rt_mac=$(ifconfig eth0 | awk '/HWaddr/ { print $5 }' | tr A-Z a-z)
  if [ "$rt_mac" != "$MSYS_MAC" ] ; then
    warn "MAC address mismatch"
    warn "    expecting: $MSYS_MAC"
    warn "    actual:    $rt_mac"
    exit 1
  else
    echo "MAC address verified"
  fi
else
  warn "No MAC address verification"
fi

if [ -f /etc/secrets.cfg ] ; then
  . /etc/secrets.cfg
else
  echo "*********************************************************"
  echo "* /etc/secrets not found!"
  echo "*********************************************************"
fi

<?php if (!defined('BRIEF_OUTPUT')) { ?>
utldir="<?= INSTPKGDIR.'utils'?>"
<?= instree(PKG_OPENWRT.'/utils','$utldir') ?>
lnpkg_bin $utldir/bin /usr/bin

<?php require_once('openwrt/bblib.sh'); ?>
<?php require_once('openwrt/swinst.sh'); ?>
<?php require_once('openwrt/services.sh'); ?>

xpkg=opkg
warn Creating sw inventory
OPKG_INSTALLED=$(swinst_init)

<?php } else { ?>

# SUPPRESED -- use TEST_SHOW_ALL=1 to show suppressed output
#    bblib.sh
#    swinst.sh
#    services.sh
# 

<?php } ?>


#
# Default pkg installation
#
[ $openwrt_version = 14.07 ] \
  && swinst flock pwgen diffutils ifstat tcpdump-mini muninlite

# Default services...
[ $openwrt_version = 10.03.1 ] \
  && enable boot done dropbear led network sysctl sysntpd ubus usb watchdog
[ $openwrt_version = 12.09 ] \
  && enable luci_fixtime boot ubus network usb dropbear done led watchdog \
  sysntpd sysctl
[ $openwrt_version = 14.07 ] \
  && enable boot done dropbear led log network sysctl sysfixtime \
	    sysntpd system
	    #cron dnsmasq firewall odhcpd telnet uhttpd #umount

# configure munin
restart xinetd /etc/xinet.d/munin

# clean-up bin directory
<?php post_text('clean_links /usr/bin '.INSTPKGDIR.NL); ?>
