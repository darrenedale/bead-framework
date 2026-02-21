#! /usr/bin/env bash

ROOT_PATH=$(realpath "$(dirname "$0")")
PHP_VERSION=8.1
SA_CONFIG=src

while [ "" != "$1" ]; do
  case "$1" in
    "-v")
      shift
      PHP_VERSION="$1"
      ;;

    *)
      # specify one of src or test on the command-line
      SA_CONFIG="$1"
      break 2
      ;;
  esac

  shift
done

cd "$ROOT_PATH"
docker compose -f ../docker-compose.yml up -d "bead-${PHP_VERSION}"
docker exec -it "bead-${PHP_VERSION}" ./vendor/bin/psalm --long-progress --no-diff --no-cache --no-file-cache --config=./analysis/psalm-"$SA_CONFIG".xml
