#!/bin/sh
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Add it in Render > Environment." >&2
  exit 1
fi

# Create/update database tables on every deploy (safe to re-run)
php artisan migrate --force

# First deploy ONLY: set RUN_SEEDER=true in Render, deploy, then delete it.
# The seeder isn't safe to re-run (it would hit duplicate emails).
if [ "$RUN_SEEDER" = "true" ]; then
  php artisan db:seed --force || echo "WARNING: seeder failed (data probably already exists), continuing..."
fi

# Cache config/views at runtime, when Render's env vars exist
php artisan config:cache
php artisan view:cache

exec apache2-foreground