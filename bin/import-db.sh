#!/bin/bash
# Local dev: drop and recreate the recruiter database from db/schema.sql.
# Extra arguments go to the mysql/mariadb client (e.g. -uroot -proot).
set -euo pipefail

SCHEMA="$(dirname "$0")/../db/schema.sql"

MYSQL_BIN="mysql"
if ! command -v "$MYSQL_BIN" &> /dev/null; then
    if command -v mariadb &> /dev/null; then
        MYSQL_BIN="mariadb"
    else
        echo "Error: mysql/mariadb client not found in PATH."
        exit 1
    fi
fi

echo "Using $MYSQL_BIN; recreating recruiter from $SCHEMA"
"$MYSQL_BIN" "$@" -e "DROP DATABASE IF EXISTS recruiter; CREATE DATABASE recruiter CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
"$MYSQL_BIN" "$@" recruiter < "$SCHEMA"
echo "Done."
