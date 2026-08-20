#!/bin/bash

# Przerywa wykonywanie skryptu w przypadku jakiegokolwiek błędu
set -e

COMPOSE="sudo docker compose -f .deploy/docker-compose.yml"

echo "🔍 Sprawdzanie stanu serwera..."

if [ -n "$($COMPOSE ps --status running --quiet)" ]; then
    echo "🔄 Serwer już działa — restartuję kontenery..."
    $COMPOSE restart
else
    echo "🚀 Serwer jest wyłączony — uruchamiam kontenery..."
    $COMPOSE up -d
fi

echo "✨ Gotowe! Serwer działa."
