#!/bin/sh
#
# In OpenWRT there is no readlink... this should work ok-ish...
#
readlink() {
  ls -l $1 | awk '{print $11}'
}

