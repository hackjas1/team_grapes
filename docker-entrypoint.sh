#!/bin/bash
set -e

# Create all necessary storage directories if missing
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Grant full read/write permissions for web server
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear stale caches so dynamic runtime config is always used
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Execute main container command
exec "$@"
