#!/bin/sh

PHPBUILDER=$(tail -n 1 /workspace/phpbuilder)

## Pull the latest image...
docker pull gcr.io/gae-runtimes/buildpacks/php74/builder:$PHPBUILDER

## Mark it with the latest tag...
docker tag gcr.io/gae-runtimes/buildpacks/php74/builder:$PHPBUILDER gcr.io/gae-runtimes/buildpacks/php74/builder:latest

## Make the workspace dir writable by the reduced permissions user from the builder...
find . -mindepth 1 -maxdepth 1 \( -path ./node_modules \) -prune -o -print0 |xargs -0 chown -R 33:33 /workspace