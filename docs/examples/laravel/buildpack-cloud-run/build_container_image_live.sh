#!/bin/sh

PHPBUILDER=$(tail -n 1 /workspace/phpbuilder)

bash ./generate_env_live.sh

pack build --builder eu.gcr.io/gae-runtimes/buildpacks/php74/builder:$PHPBUILDER --publish eu.gcr.io/$PROJECT_ID/example-service:$COMMIT_SHA --env-file ./.env
