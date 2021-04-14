#!/bin/bash

echo "Configuring logpipe..."
mkfifo -m 600 /tmp/logpipe
cat <> /tmp/logpipe 1>&2 &
CPID=$!

echo "Starting PHP runtime..."
serve --enable-dynamic-workers public/index.php &
SPID=$1

echo "Wuhoo! Environment Ready!"
wait -n $CPID $SPID