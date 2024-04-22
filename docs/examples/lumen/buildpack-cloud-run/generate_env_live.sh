#!/bin/bash

cat > ./.env <<EOF
APP_NAME="Example Application"
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
EOF
