#!/bin/bash

cat > ./.env <<EOF
APP_ENV=local
APP_DEBUG=true
APP_KEY=$ENV_APP_KEY
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

CACHE_DRIVER=array

SESSION_DRIVER=datastore
SESSION_TABLE=laravel-sessions
SESSION_STORE=demo
SESSION_LIFETIME=10080
EOF