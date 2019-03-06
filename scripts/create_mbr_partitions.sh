#!/bin/sh

# parameters
DISK=$1;
MBR_LABEL=$2;
DATA_SIZE=$3;
PMBR=$4;
ZFSBOOT=$5;

echo -n "Creating MBR partition scheme on ${DISK}"
/sbin/gpart create -s mbr ${DISK}
RV=$?

if [ "$RV" = "0" ]
then

 # boot partition; 128 (sectors) size minimum; 512 (sectors) chosen
 /sbin/gpart add -b 34 -s 512 -t freebsd ${DISK}

 # insert bootcode onto boot partition
 /sbin/gpart bootcode -b ${PMBR} -p ${ZFSBOOT} -i 1 ${DISK}

 # data partition; start at 2048 sector offset (1MiB) for proper alignment
 /sbin/gpart add -b 2048 -s ${DATA_SIZE} -t freebsd ${DISK}
 RV=$?

fi
exit $RV

