#!/usr/bin/env bash
# SOLR-5.4.0 UPDATE PROCESS

echo "$1"

if [[ $EUID -ne 0 ]]
then
  echo -e "\nERROR: Run this script as root\n" 1>&2
  exit 1
fi

if [ "$1" == "" ]
then
    echo "Specify the service name msearch or hserach"
    exit 1
elif [ "$1" == "hsearch5" ]
then
  echo echo "service name in use: $1"
elif [ "$1" == "msearch5" ]
then
  echo "service name in use: $1"
else
  echo "Allowed service names are msearch5 or hsearch5."
  exit 1
fi

SERVICE_NAME=$1

service $SERVICE_NAME stop

update-rc.d -f $SERVICE_NAME remove
rm /etc/init.d/$SERVICE_NAME

rm -r /var/$SERVICE_NAME
rm /opt/$SERVICE_NAME
