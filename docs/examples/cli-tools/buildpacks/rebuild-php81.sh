#!/bin/bash

gcloud container images list-tags gcr.io/gae-runtimes/buildpacks/php81/builder | awk -F'_' '{print $2+0}'>tagfilter.txt

TAGDATE=0

while read tag; do
  if [[ $tag -gt $TAGDATE ]]; then
    TAGDATE=$tag
  fi
done<tagfilter.txt

echo "Latest date found for builder: $TAGDATE"

PHPBUILDER_TMP=$(gcloud container images list-tags gcr.io/gae-runtimes/buildpacks/php81/builder --filter php81_$TAGDATE | awk 'NR==2 {print $2}')
PHPBUILDER=${PHPBUILDER_TMP##*,}

echo "Being built with builder image tag $PHPBUILDER"

## Pull the latest image...
docker pull gcr.io/gae-runtimes/buildpacks/php81/builder:$PHPBUILDER

## Builder an image with the latest tag (and install composer)
docker build $(pwd)/builder-8.1 \
    -t gcr.io/gae-runtimes/buildpacks/php81/builder:latest \
    --build-arg IMAGE=gcr.io/gae-runtimes/buildpacks/php81/builder \
    --build-arg TAG=$PHPBUILDER
