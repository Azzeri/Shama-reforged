#!/bin/bash
#
# Collects performance-related facts about the running app. Safe to run any
# time (read-only). Usage, in the app directory:  bash .deploy/diagnose.sh
#
cd "$(dirname "$0")/.."
COMPOSE="sudo docker compose -f .deploy/docker-compose.yml"
URL="${1:-http://localhost}"

echo "== git";        git log -1 --format='%h %s (%cr)'
echo "== containers"; sudo docker ps --format '{{.Names}}\t{{.Status}}' | grep laravel

echo "== php / opcache"
$COMPOSE exec -T app php -r '
$s = function_exists("opcache_get_status") ? opcache_get_status(false) : null;
echo "PHP ", PHP_VERSION, "\n";
echo "opcache (cli check): ", ini_get("opcache.enable") ? "on" : "off", "\n";
'
# FPM is what serves requests — ask it through a tiny script.
$COMPOSE exec -T app sh -c 'echo "<?php \$s=opcache_get_status(false); echo json_encode([\"enabled\"=>\$s[\"opcache_enabled\"]??false,\"cached_scripts\"=>\$s[\"opcache_statistics\"][\"num_cached_scripts\"]??0,\"hit_rate\"=>round(\$s[\"opcache_statistics\"][\"opcache_hit_rate\"]??0,1),\"validate_timestamps\"=>ini_get(\"opcache.validate_timestamps\")]);" > public/__opcache.php'
echo -n "opcache (fpm): "; curl -s "$URL/__opcache.php"; echo
$COMPOSE exec -T app rm -f public/__opcache.php

echo "== laravel config (from .env)"
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|LOG_LEVEL|SESSION_DRIVER|CACHE_STORE|QUEUE_CONNECTION|DB_HOST|BROADCAST_CONNECTION)=' .env
echo -n "config cached: "; [ -f bootstrap/cache/config.php ] && echo yes || echo NO
echo -n "routes cached: "; ls bootstrap/cache/routes-*.php >/dev/null 2>&1 && echo yes || echo NO

echo "== response times from the server itself (ttfb / total, seconds)"
for path in /up /login /up /login; do
  curl -s -o /dev/null -w "$path\t%{time_starttransfer}\t%{time_total}\t%{size_download}B\t%{http_code}\n" "$URL$path"
done

echo "== gzip"
curl -s -o /dev/null -D - -H 'Accept-Encoding: gzip' "$URL/login" | grep -i -E '^content-encoding|^server' || echo "no content-encoding header"

echo "== db round trip"
$COMPOSE exec -T app php -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$t = microtime(true); for ($i = 0; $i < 20; $i++) { DB::select("select 1"); }
printf("20 queries: %.1f ms\n", (microtime(true) - $t) * 1000);
'

echo "== load"; uptime; free -m | head -2
