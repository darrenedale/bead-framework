#! /usr/bin/env bash

ROOT_PATH=$(realpath "$(dirname "$0")")

# specify one of src, test on the command-line
if [ "" != "$1" ]; then
  SA_CONFIG="$1"
else
  SA_CONFIG=src
fi

cd "$ROOT_PATH"
docker compose up -d php80
docker exec -it bead-test-80 ./vendor/bin/psalm --long-progress --no-diff --no-cache --no-file-cache --config=./analysis/psalm-"$SA_CONFIG".xml
