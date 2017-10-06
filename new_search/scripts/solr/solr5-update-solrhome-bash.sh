#!/usr/bin/env bash
# Install solr as service with name msearch/hsearch

if [[ $EUID -ne 0 ]]; then
  echo -e "\nERROR: Run this script as root\n" 1>&2
  exit 1
fi

print_usage() {
  ERROR_MSG="$1"
  if [ "$ERROR_MSG" != "" ]; then
    echo -e "\nERROR: $ERROR_MSG\n" 1>&2
  fi
} # end print_usage

if [ "$1" == "" ]; then
  print_usage "Specify the solr service name hsearch5 or msearch5"
  exit 1
elif [[ "$1" == "msearch5" || "$1" == "hsearch5" ]]; then
  echo "Solr service in use: $1"
else
  echo "Allowed contexts are msearch5 or hsearch5"
  exit 1
fi

## cd to msolr/new_search/solr
cd "$(dirname "$0")/../../solr"

echo -e "PWD = $PWD"


SOLR_SERVICE=$1
SOLR_USER=solr

SOLR_HOME_DIRNAME=solr5
SOLR_DIR=/var/$SOLR_SERVICE
SOLR_DATA_DIR=$SOLR_DIR/data

service $SOLR_SERVICE stop
echo "Waiting for 5 seconds."
sleep 5

rm -r $SOLR_DATA_DIR
cp -r $PWD/$SOLR_HOME_DIRNAME $SOLR_DIR


mv $SOLR_DIR/$SOLR_HOME_DIRNAME $SOLR_DATA_DIR
chown -hR $SOLR_USER:$SOLR_USER $SOLR_DATA_DIR

echo -e "Updated. Restarting service..."
service $SOLR_SERVICE start
echo -e "============ have fun!!!============="