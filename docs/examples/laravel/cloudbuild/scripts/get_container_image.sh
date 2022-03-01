#!/bin/bash

gcloud container images list-tags gcr.io/gae-runtimes/buildpacks/php74/builder | awk -F'_' '{print $2}'>tagfilter.txt

TAGDATE=0

while read tag; do
  if [[ $tag -gt $TAGDATE ]]; then
    TAGDATE=$tag
  fi
done<tagfilter.txt

echo "Latest date found for builder: $TAGDATE"

PHPBUILDER=$(gcloud container images list-tags gcr.io/gae-runtimes/buildpacks/php74/builder --filter php74_$TAGDATE | awk 'NR==2 {print $2}')

echo "Being built with builder image tag $PHPBUILDER"

rm tagfilter.txt

echo -n $PHPBUILDER>/workspace/phpbuilder