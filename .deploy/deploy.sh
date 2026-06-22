#!/bin/bash

# Przerywa wykonywanie skryptu w przypadku jakiegokolwiek błędu
set -e

echo "🚀 Rozpoczynam wdrażanie nowej wersji aplikacji..."

# 1. Pobranie najnowszego kodu z repozytorium
echo "📥 Pobieranie zmian z Git..."
git pull origin main

# 2. Upewnienie się, że foldery tymczasowe istnieją
echo "📁 Sprawdzanie struktury folderów..."
mkdir -p storage/framework/{cache,sessions,views} storage/app/tmp public/build

# 3. Instalacja zależności PHP (Composer)
echo "📦 Instalacja paczek Composer..."
sudo docker compose -f .deploy/docker-compose.yml exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

# 4. Generowanie assetów (Vite / NPM) za pomocą jednorazowego kontenera
echo "🌐 Kompilacja plików frontendu (Vite)..."
sudo docker run --rm -v $(pwd):/app -w /app node:20 sh -c "npm install && npm run build"

# 5. Nadanie uprawnień do plików dla Dockera i Nginxa
echo "🔒 Ustawianie uprawnień do folderów..."
sudo chown -R :www-data storage bootstrap/cache public/build
sudo chmod -R 777 storage bootstrap/cache
sudo chmod -R 755 public/build

# 6. Wykonanie migracji bazy danych
echo "🗄️ Uruchamianie migracji bazy danych..."
sudo docker compose -f .deploy/docker-compose.yml exec -T app php artisan migrate --force

# 7. Czyszczenie i budowanie produkcyjnego cache Laravela
echo "⚡ Optymalizacja i cache'owanie aplikacji..."
sudo docker compose -f .deploy/docker-compose.yml exec -T app php artisan optimize:clear
sudo docker compose -f .deploy/docker-compose.yml exec -T app php artisan optimize
sudo docker compose -f .deploy/docker-compose.yml exec -T app php artisan view:cache

echo "✨ Wdrożenie zakończone sukcesem! Aplikacja działa."
