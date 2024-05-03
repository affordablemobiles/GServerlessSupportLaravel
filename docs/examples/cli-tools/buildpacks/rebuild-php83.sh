#!/bin/bash

## Builder an image with the latest tag (and install composer)
docker build $(pwd)/builder-8.3 \
    -t gcr.io/gae-runtimes/buildpacks/php83/builder:latest \
    --build-arg IMAGE=gcr.io/gae-runtimes/buildpacks/php83/run \
    --build-arg TAG=latest
