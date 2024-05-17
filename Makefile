THIS := $(realpath $(lastword $(MAKEFILE_LIST)))
HERE := $(shell dirname $(THIS))

.PHONY: all fix lint

all: lint

fix:
	php -n $(HERE)/vendor/bin/php-cs-fixer fix -vvv --config=$(HERE)/.php-cs-fixer.php

lint:
	php -n $(HERE)/vendor/bin/php-cs-fixer fix -vvv --config=$(HERE)/.php-cs-fixer.php --dry-run
