#!/bin/sh

# script: smbpasswd.sh <username>
# sets Samba password and adds user if applicable

USERNAME=$1
DATFILE="/tmp/zfsguru_smbpasswd.dat"
SMBPASSWD=`cat ${DATFILE}`

# remove temporary password file
/bin/rm -f ${DATFILE}

# sanity check on password file
if [ -z ${SMBPASSWD} ]
then
 echo "Could not read password"
 exit 1
fi

# add ssh user if nonexistent
#/usr/sbin/pw useradd ${USERNAME} -K wheel > /dev/null 2>&1

# create home directory
#mkdir -p /home/${SSHUSERNAME}
#chown ${SSHUSERNAME}:${SSHUSERNAME} /home/${SSHUSERNAME}

# reset password of Samba user
( /bin/echo "${SMBPASSWD}" ; /bin/echo "${SMBPASSWD}" ) | \
/usr/local/bin/smbpasswd -as ${USERNAME}

exit 0
