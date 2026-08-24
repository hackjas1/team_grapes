#!/bin/bash
set -e

# Clear stale build-time caches so runtime environment variables from Render are used
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Cache config, routes, and views with active runtime environment
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Ensure permissions are correct on storage and bootstrap cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Execute main container command (apache2-foreground)
exec "$@"
