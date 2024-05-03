#!/bin/sh

## Pull the latest image...
docker pull gcr.io/gae-runtimes/buildpacks/php83/run:latest

## Builder an image with the latest tag (and install composer)
docker build /workspace/cloudbuild/scripts/builder \
    -t gcr.io/gae-runtimes/buildpacks/php83/run:latest-composer \
    --build-arg IMAGE=gcr.io/gae-runtimes/buildpacks/php83/run \
    --build-arg TAG=latest

## Make the workspace dir writable by the reduced permissions user from the builder...
chown 33:33 /workspace
find . -mindepth 1 -maxdepth 1 \( -path ./node_modules \) -prune -o -print0 |xargs -0 chown -R 33:33