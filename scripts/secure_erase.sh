#!/bin/sh

DISK=$1;

/bin/echo -n "Secure-Erasing disk ${DISK}..."
/sbin/newfs -E -b 65536 /dev/${DISK}
RV=$?

if [ "$RV" = "0" ]
then
 /bin/echo " done"
fi
exit $RV


