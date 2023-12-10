# Smoke test application

This directory contains a basic web application using the framework which provides a quick smoke test for various
features.

To extend the application, add a routes file (or add routes to an existing routes file) and probably some views, then
add links to the routes in a view, ideally in the `navbar.php` include.

To get it running, bring up the docker containers defined in `docker-compose.yml` and point your browser at
http://localhost:6080.

```shell
# from project root
cd test/smoke
docker-compose up -d

# or if you have an up-to-date docker engine installed
docker compose up -d
```

The fpm image has xdebug installed and configured, so you should be able to debug from your IDE.
