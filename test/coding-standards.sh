#! /usr/bin/env bash

ROOT_PATH=$(realpath "$(dirname "$0")")

# specify one of src, test, standards on the command-line
if [ "" != "$1" ]; then
  CS_CONFIG="$1"
else
  CS_CONFIG=src
fi

cd "$ROOT_PATH"
docker compose up -d php83
docker exec -it bead-test-83 ./vendor/bin/phpcs "$CS_CONFIG/"
