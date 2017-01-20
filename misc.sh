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



