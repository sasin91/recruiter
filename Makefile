# make test | lint | shellcheck | schema | check
#
# Tests are .phpt files (as in php-src) in a tests/ folder next to the code,
# run by bin/run-tests.php. Without make (e.g. PowerShell), run the same:
#   php bin/run-tests.php -q taxonomy/php/tests modules

PHP ?= php
JOBS ?= $(shell nproc 2>/dev/null || echo 4)
TESTS ?= taxonomy/php/tests modules
MYSQL ?= mysql -h127.0.0.1 -uroot
DB ?= recruiter_schema_check

.PHONY: check test lint shellcheck schema

check: lint test

test:
	$(PHP) bin/run-tests.php -q -j$(JOBS) --show-diff $(TESTS)

lint:
	find . -name '*.php' -not -path './.git/*' -not -path './packages/*' -print0 | xargs -0 -n1 -P$(JOBS) $(PHP) -l > /dev/null

shellcheck:
	shellcheck --severity=warning bin/*.sh

# Applies db/schema.sql to an empty database twice: it must be re-runnable.
schema:
	$(MYSQL) -e "DROP DATABASE IF EXISTS $(DB); CREATE DATABASE $(DB) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
	$(MYSQL) $(DB) < db/schema.sql
	$(MYSQL) $(DB) < db/schema.sql
