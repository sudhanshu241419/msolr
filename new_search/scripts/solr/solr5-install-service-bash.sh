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
    echo "Specify the service name msearch or hsearch"
    exit 1
elif [ "$1" == "hsearch5" ]
then
  echo echo "service name in use: $1"
  SERVICE_PORT=8983
elif [ "$1" == "msearch5" ]
then
  echo "service name in use: $1"
  SERVICE_PORT=8984
else
  echo "Allowed service names are msearch5 or hsearch5"
  exit 1
fi

SERVICE_NAME=$1
SOLR_VERSION=5.5.0
JTS_JAR_PATH=repo/jts-1.13.jar

service $SERVICE_NAME stop

update-rc.d -f $SERVICE_NAME remove
rm /etc/init.d/$SERVICE_NAME

rm -r /var/$SERVICE_NAME

rm /opt/$SERVICE_NAME

#wget -c http://localhost/web/solr-$SOLR_VERSION.tgz
wget -c https://archive.apache.org/dist/lucene/solr/$SOLR_VERSION/solr-$SOLR_VERSION.tgz
#wget -c http://www.us.apache.org/dist/lucene/solr/$SOLR_VERSION/solr-$SOLR_VERSION.tgz

tar xzf solr-$SOLR_VERSION.tgz solr-$SOLR_VERSION/bin/install_solr_service.sh --strip-components=2
bash ./install_solr_service.sh solr-$SOLR_VERSION.tgz -s $SERVICE_NAME -p $SERVICE_PORT
cp $JTS_JAR_PATH /opt/solr-$SOLR_VERSION/server/lib/ext/

service $SERVICE_NAME restart
