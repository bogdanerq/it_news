#!/bin/bash

set -e  # Exit on error

# directory with files: https://drive.google.com/drive/folders/1kTyjDdnx6H3wMizF2rGgzrLp42LfQ6XT?usp=drive_link
DB_DUMP_URL="https://drive.google.com/uc?export=download&id=1nK4NTXM2TY1DtbIqPnoDksT3WINpv_34"
DB_DUMP_PATH="./db_backup.tar.xz"
FILES_DIR_URL="https://drive.google.com/uc?export=download&id=16QZMf03oYcPDJUpxJl9QFjB6Chq2gNpi"
FILES_DIR_PATH="./files.tar.xz"

echo "📥 Downloading database dump..."
 curl -L "$DB_DUMP_URL" -o "$DB_DUMP_PATH"

echo "📂 Extracting database..."
 tar -xf "$DB_DUMP_PATH"

echo "📥 Downloading files directory..."
curl -L "$FILES_DIR_URL" -o "$FILES_DIR_PATH"

echo "📂 Extracting files..."
tar -xf "$FILES_DIR_PATH"

echo "🚀 Starting Docker containers..."
docker compose up -d

echo "⏳ Waiting for containers to initialize..."
sleep 15

echo "📦 Installing dependencies with Composer..."
docker compose exec php composer install

echo "🗄 Importing database..."
docker exec -i drupal-db-test mysql -u user -puser default < db_backup.sql

echo "📂 Merging files into sites/default/files..."
mkdir -p ./sites/default/files &&
cp -rT ./files ./sites/default/files

echo "⚙️ Importing Drupal configuration..."
docker compose exec php vendor/bin/drush cim -y

echo "🔄 Updating database..."
docker compose exec php vendor/bin/drush updb -y

echo "🧹 Clearing cache..."
docker compose exec php vendor/bin/drush cr

echo "🔑 Generating admin login link..."
docker compose exec php vendor/bin/drush uli
