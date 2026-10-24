#!/usr/bin/env bash

set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root_dir"

version="$(node -p "require('./package.json').version")"
archive="blessing-skin-server-${version}.zip"

composer install --no-dev --prefer-dist --no-progress --optimize-autoloader
yarn install --frozen-lockfile --non-interactive
yarn build
cp resources/assets/src/images/bg.webp public/app/
cp resources/assets/src/images/favicon.ico public/app/

rm -f "$archive"
zip -9 -r "$archive" \
  app bootstrap config database plugins public resources/lang resources/views \
  resources/misc/textures routes storage vendor .env.example artisan LICENSE \
  README.md README-zh.md index.html \
  -x 'Dockerfile' '*/Dockerfile' \
     'docker-compose*.yml' '*/docker-compose*.yml' \
     '.dockerignore' '*/.dockerignore'

echo "Created $archive"
