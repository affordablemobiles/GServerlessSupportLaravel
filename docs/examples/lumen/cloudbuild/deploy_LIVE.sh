#!/bin/bash

## Make sure we start out in the root application directory.
cd /workspace;

## Move our app.yaml in to place.
mv ./cloudbuild/assets/LIVE_app.yaml ./app.yaml

## Deploy the application to App Engine.
gcloud app deploy --quiet --project $PROJECT_ID --version=$COMMIT_SHA app.yaml