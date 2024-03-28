THIS := $(realpath $(lastword $(MAKEFILE_LIST)))
HERE := $(shell dirname $(THIS))

.PHONY: all fix lint

all: lint

fix:
	php -n -dmemory_limit=12G -dzend_extension=opcache.so -dopcache.enable_cli=On -dopcache.jit_buffer_size=128M $(HERE)/vendor/bin/php-cs-fixer fix --config=$(HERE)/.php-cs-fixer.php

lint:
	php -n -dmemory_limit=12G -dzend_extension=opcache.so -dopcache.enable_cli=On -dopcache.jit_buffer_size=128M $(HERE)/vendor/bin/php-cs-fixer fix --config=$(HERE)/.php-cs-fixer.php --dry-run
