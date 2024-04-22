#!/bin/bash

## Make sure we start out in the root application directory.
cd /workspace;

## Move our app.yaml in to place.
mv ./cloudbuild/assets/LIVE_app.yaml ./app.yaml

## Deploy the application to App Engine.
if [[ -z "${DEPLOY_NO_PROMOTE}" ]]; then
	gcloud app deploy --quiet --project $PROJECT_ID --version=$SHORT_SHA --no-cache app.yaml
else
	gcloud app deploy --quiet --project $PROJECT_ID --version=$SHORT_SHA --no-cache --no-promote app.yaml
fi