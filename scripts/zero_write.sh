#!/bin/sh

DISK=$1;

/bin/echo -n "Zero Writing disk ${DISK}..."
/bin/dd if=/dev/zero of=/dev/${DISK} bs=1m
RV=$?

if [ "$RV" = "0" ]
then
 /bin/echo " done"
fi
exit $RV


