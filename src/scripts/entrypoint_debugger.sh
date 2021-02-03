#!/bin/bash

echo "Set our environment variables so the app knows Debugger is available..."
export STACKDRIVER_DEBUGGER='true'

echo "Starting PHP runtime..."
serve --enable-dynamic-workers public/index.php &
WPID=$!;

echo "Starting Cloud Debugger..."
php -d auto_prepend_file='' -d disable_functions='' /srv/vendor/bin/google-cloud-debugger -s /tmp/srv &
DPID=$1;

echo "Wuhoo! Environment Ready!"
wait -n $WPID $DPID;