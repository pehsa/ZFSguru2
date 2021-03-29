#!/bin/sh

DISK=$1;

getmsize() { echo $(expr $(diskinfo -v $1 | grep bytes | cut -d \# -f 1) / 1024 / 1024  ) ; }

DISKSIZE=$( getmsize ${DISK} );

echo  "Erasing partitions on disk ${DISK}...${DISKSIZE}M"
/bin/dd if=/dev/zero of=/dev/${DISK} bs=1m count=1
RV=$?

#echo  "Erasing backup GPT on disk ${DISK}...${DISKSIZE}M"
/bin/dd if=/dev/zero of=/dev/${DISK} bs=1m seek=$((${DISKSIZE}-2))

if [ "$RV" = "0" ]
then
 echo " done"
fi
exit $RV

