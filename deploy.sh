#!/bin/bash
# deploy.sh — jalankan setiap deploy ke production

set -e

echo "🚀 Starting deployment..."

# Maintenance mode
php artisan down --refresh=15

# Install dependencies (production)
composer install --optimize-autoloader --no-dev

# Run database migrations
php artisan migrate --force

# Cache config, routes, views, events
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Cache icons (if blade-icons used)
php artisan icons:cache 2>/dev/null || true

# Build frontend assets
npm ci --production=false
npm run build

# Restart queue workers (pick up new code)
php artisan queue:restart

# Bring application back up
php artisan up

echo "✅ Deployment complete!"
