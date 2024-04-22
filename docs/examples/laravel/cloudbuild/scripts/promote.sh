#!/bin/bash

## Make sure we start out in the root application directory.
cd /workspace;

## Deploy the application to App Engine.
if [[ -z "${DEPLOY_NO_PROMOTE}" ]]; then
	gcloud app versions migrate --quiet --project $PROJECT_ID $SHORT_SHA
fi