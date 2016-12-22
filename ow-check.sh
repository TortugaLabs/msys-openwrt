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

if [ -n "$SYS_MAC" ] ; then
  rt_mac=$(ifconfig eth0 | awk '/HWaddr/ { print $5 }' | tr A-Z a-z)
  if [ "$rt_mac" != "$SYS_MAC" ] ; then
    warn "MAC address mismatch"
    warn "    expecting: $SYS_MAC"
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

