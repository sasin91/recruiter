# make test | test-db | lint | shellcheck | schema | check | extension
#
# Tests are .phpt files (as in php-src) in a tests/ folder next to the code,
# run by bin/run-tests.php. Without make (e.g. PowerShell), run the same:
#   php bin/run-tests.php -q taxonomy/php/tests modules

PHP ?= php
JOBS ?= $(shell nproc 2>/dev/null || echo 4)
TESTS ?= taxonomy/php/tests modules
MYSQL ?= mysql -h127.0.0.1 -uroot
DB ?= recruiter_schema_check

.PHONY: check test test-db lint shellcheck schema extension

check: lint test

test:
	$(PHP) bin/run-tests.php -q -j$(JOBS) --show-diff $(TESTS)

# The tests that need MariaDB/MySQL (skipped by `make test`): they get a
# scratch database and may drop tables in it.
DB_TEST ?= recruiter_test
DB_PASSWORD ?=
test-db:
	$(MYSQL) -e "CREATE DATABASE IF NOT EXISTS $(DB_TEST) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
	QUEUE_TEST_DSN="mysql:host=127.0.0.1;dbname=$(DB_TEST)" QUEUE_TEST_PASSWORD="$(DB_PASSWORD)" \
		$(PHP) bin/run-tests.php -q --show-diff modules/queue/tests

# Also: the server prints what the user reads (views), so the app's own JS
# builds no markup. The one way in for HTML is a <template> filled with what
# the server printed, on lines marked "// server HTML". Trongate's own
# modules (setup, validation, trongate_control) are left as they come.
OWN_JS = $(filter-out modules/setup/js modules/validation/js modules/trongate_control/%,$(wildcard modules/*/js modules/*/*/js))
lint:
	find . -name '*.php' -not -path './.git/*' -not -path './packages/*' -print0 | xargs -0 -n1 -P$(JOBS) $(PHP) -l > /dev/null
	@! grep -rnE 'createElement\(|(inner|outer)HTML *[+]?=([^=]|$$)|insertAdjacentHTML' $(OWN_JS) | grep -v '// server HTML' \
		|| { echo 'JS above builds markup: print it in a view (see modules/cv_match/views) instead.'; exit 1; }

shellcheck:
	shellcheck --severity=warning bin/*.sh

# Applies db/schema.sql to an empty database twice: it must be re-runnable.
schema:
	$(MYSQL) -e "DROP DATABASE IF EXISTS $(DB); CREATE DATABASE $(DB) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
	$(MYSQL) $(DB) < db/schema.sql
	$(MYSQL) $(DB) < db/schema.sql

# The Jobnet joblog extension as the zip the browser stores take
# (build/jobnet-joblog-<version>.zip): the folder without its README and
# store/ (listing images and texts, uploaded by hand).
EXT = extensions/jobnet-joblog
EXT_VERSION = $(shell sed -n 's/^  "version": "\(.*\)",$$/\1/p' $(EXT)/manifest.json)
extension:
	mkdir -p build
	rm -f build/jobnet-joblog-$(EXT_VERSION).zip
	cd $(EXT) && zip -qr ../../build/jobnet-joblog-$(EXT_VERSION).zip manifest.json *.js icons
	@echo build/jobnet-joblog-$(EXT_VERSION).zip
