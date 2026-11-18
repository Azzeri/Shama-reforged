#!/bin/bash
#
# Deploys the code currently checked out in this repository.
# Run from anywhere: `bash .deploy/deploy.sh`. Used by the GitHub Actions
# "deploy" workflow after it has pulled the latest main (see .deploy/README.md).

# Przerywa wykonywanie skryptu w przypadku jakiegokolwiek błędu
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="sudo docker compose -f .deploy/docker-compose.yml"

echo "🚀 Rozpoczynam wdrażanie nowej wersji aplikacji ($(git log -1 --format='%h %s'))..."

# 1. Upewnienie się, że kontenery działają
echo "🐳 Uruchamianie kontenerów..."
$COMPOSE up -d

# 2. Upewnienie się, że foldery tymczasowe istnieją
echo "📁 Sprawdzanie struktury folderów..."
mkdir -p storage/framework/{cache,sessions,views} storage/app/tmp public/build

# 3. Instalacja zależności PHP (Composer)
echo "📦 Instalacja paczek Composer..."
$COMPOSE exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

# 4. Generowanie assetów (Vite / NPM) za pomocą jednorazowego kontenera
echo "🌐 Kompilacja plików frontendu (Vite)..."
sudo docker run --rm -v "$(pwd)":/app -w /app node:20 sh -c "npm install && npm run build"

# 5. Nadanie uprawnień do plików dla Dockera i Nginxa
echo "🔒 Ustawianie uprawnień do folderów..."
sudo chown -R :www-data storage bootstrap/cache public/build
sudo chmod -R 777 storage bootstrap/cache
sudo chmod -R 755 public/build

# 6. Tryb serwisowy tylko na czas migracji i cache'u (krótko); zawsze
#    wyłączany na końcu, także gdy coś po drodze się wysypie
echo "🚧 Włączanie trybu serwisowego..."
$COMPOSE exec -T app php artisan down --retry=15 || true
trap 'echo "🔓 Wyłączanie trybu serwisowego..."; $COMPOSE exec -T app php artisan up || true' EXIT

# 7. Wykonanie migracji bazy danych
echo "🗄️ Uruchamianie migracji bazy danych..."
$COMPOSE exec -T app php artisan migrate --force

# 8. Czyszczenie i budowanie produkcyjnego cache Laravela
echo "⚡ Optymalizacja i cache'owanie aplikacji..."
$COMPOSE exec -T app php artisan optimize:clear
$COMPOSE exec -T app php artisan optimize
$COMPOSE exec -T app php artisan view:cache

echo "✨ Wdrożenie zakończone sukcesem! Aplikacja działa."
