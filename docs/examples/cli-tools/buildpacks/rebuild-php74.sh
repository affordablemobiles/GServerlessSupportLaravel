#!/bin/bash

gcloud container images list-tags gcr.io/gae-runtimes/buildpacks/php74/builder | awk -F'_' '{print $2+0}'>tagfilter.txt

TAGDATE=0

while read tag; do
  if [[ $tag -gt $TAGDATE ]]; then
    TAGDATE=$tag
  fi
done<tagfilter.txt

echo "Latest date found for builder: $TAGDATE"

PHPBUILDER_TMP=$(gcloud container images list-tags gcr.io/gae-runtimes/buildpacks/php74/builder --filter php74_$TAGDATE | awk 'NR==2 {print $2}')
PHPBUILDER=${PHPBUILDER_TMP##*,}

echo "Being built with builder image tag $PHPBUILDER"

## Pull the latest image...
docker pull gcr.io/gae-runtimes/buildpacks/php74/builder:$PHPBUILDER

## Builder an image with the latest tag (and install composer)
docker build $(pwd)/builder-7.4 \
    -t gcr.io/gae-runtimes/buildpacks/php74/builder:latest \
    --build-arg IMAGE=gcr.io/gae-runtimes/buildpacks/php74/builder \
    --build-arg TAG=$PHPBUILDER
