#!/bin/bash

## Make sure we start out in the root application directory.
cd /workspace;

## Promote the version to production/serving.
if [[ -z "${DEPLOY_NO_PROMOTE}" ]]; then
	gcloud app versions migrate --quiet --project $PROJECT_ID $SHORT_SHA
fi