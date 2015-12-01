#!/bin/sh
#
# Used to manage services
#
REF_FILE=/tmp/srv_ref.$$
touch $REF_FILE
COMMIT_SRV=no

enable() {
  local SRV
  for SRV in "$@"
  do
    if [ ! -x /etc/init.d/$SRV ] ; then
      echo "Unable to activate missing service: $SRV"
      return
    fi
    COMMIT_SRV=yes
    local srvid=$(mkid SRV_${SRV})
    local cSTATUS=$(eval echo \$${srvid})
    if [ -z "$cSTATUS" ] ; then
      eval ${srvid}=on
    fi
    # echo "$srvid => on"
  done
}

restart() {
  local srv="$1"

  if [ ! -x /etc/init.d/$srv ] ; then
    echo "Unable to restart missing service: $srv"
    return
  fi
  COMMIT_SRV=yes
  local srvid=$(mkid SRV_${srv})
  local cSTATUS=$(eval echo \$${srvid})
  [ x"$cSTATUS" = x"restart" ] && return

  local cfile
  local restart=on
  for cfile in "$@"
  do
    [ -f $cfile ] || continue # Ignore missing config files
    [ $REF_FILE -nt $cfile ] && continue
    restart=restart

    local r
    # NOTE: These services can not be restart so a reboot is needed!
    for r in boot network fstab
    do
      if [ $srv = $r ] ; then
	MUST_REBOOT="$MUST_REBOOT.$srv"
	break
      fi
    done

    break
  done
  # echo "$srvid => $restart"
  eval ${srvid}=\$restart
}

disable() {
  local srv
  for srv in "$@"
  do
    [ ! -x /etc/init.d/$srv ] && return
    local srvid=$(mkid SRV_${srv})
    local cSTATUS=$(eval echo \$${srvid})
    if [ ! -z "$cSTATUS" ] ; then
      eval ${srvid}=\"\"
    fi
  done
}

commit_services() {
  local SRV

  rm -f $REF_FILE

  # We are not using the services.sh framework
  [ $COMMIT_SRV = no ] && return

  if [ -z "$MUST_REBOOT" ] ; then
    DO=""
  else
    echo "Reboot is required by $MUST_REBOOT" 1>&2
    DO=":"
  fi

  for SRV in $(cd /etc/init.d && echo *)
  do
    [ $SRV = rcS ] && continue
    [ -x /etc/init.d/$SRV ] || continue

    local SRVID=$(mkid SRV_${SRV})
    local cSTATUS=$(eval echo \$${SRVID})

    if [ x"$cSTATUS" = x"on" ] ; then
      /etc/init.d/$SRV enabled && continue
      echo "Enabling $SRV"
      /etc/init.d/$SRV enable
      $DO /etc/init.d/$SRV start
    elif [ x"$cSTATUS" = x"restart" ] ; then
      if /etc/init.d/$SRV enabled ; then
	$DO echo "Re-starting service $SRV"
	$DO /etc/init.d/$SRV restart
      else
	echo "Starting $SRV"
	/etc/init.d/$SRV enable
	$DO /etc/init.d/$SRV start
      fi
    else
      /etc/init.d/$SRV enabled || continue
      [ $SRV = "network" ] && continue;
      echo "Disabling $SRV"
      /etc/init.d/$SRV disable
      # $DO /etc/init.d/$SRV stop
    fi
  done
}

<?php post_text('commit_services'.NL); ?>
