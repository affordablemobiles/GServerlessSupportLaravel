THIS := $(realpath $(lastword $(MAKEFILE_LIST)))
HERE := $(shell dirname $(THIS))

.PHONY: all fix lint

all: lint

fix:
	php $(HERE)/vendor/bin/php-cs-fixer fix --config=$(HERE)/.php-cs-fixer.php

lint:
	php $(HERE)/vendor/bin/php-cs-fixer fix --config=$(HERE)/.php-cs-fixer.php --dry-run