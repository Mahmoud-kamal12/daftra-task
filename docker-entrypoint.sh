#!/bin/bash
set -e

if [ "$1" = 'apache2-foreground' ]; then
    # Run migrations
    php artisan migrate --force
fi

exec "$@"
