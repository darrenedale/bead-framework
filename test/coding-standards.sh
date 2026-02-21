#! /usr/bin/env bash

ROOT_PATH=$(realpath "$(dirname "$0")")
PHP_VERSION=8.1
CS_CONFIG=src

while [ "" != "$1" ]; do
  case "$1" in
    "-v")
      shift
      PHP_VERSION="$1"
      ;;

    *)
      # specify one of src, test, standards on the command-line
      CS_CONFIG="$1"
      break 2
      ;;
  esac

  shift
done

cd "$ROOT_PATH"
docker compose -f ../docker-compose.yml up -d "bead-${PHP_VERSION}"
docker exec -it "bead-${PHP_VERSION}" ./vendor/bin/phpcs "${CS_CONFIG}/"
