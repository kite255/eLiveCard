#!/bin/sh

mkdir -p storage/app/public
mkdir -p storage/app/livewire-tmp
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

chmod -R 775 storage bootstrap/cache || true

php artisan optimize:clear || true

php -d upload_max_filesize=25M \
    -d post_max_size=30M \
    -d memory_limit=512M \
    artisan serve --host=0.0.0.0 --port=80