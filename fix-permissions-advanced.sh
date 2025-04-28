#!/bin/bash

echo "🔧 Smart Permission Fixer for Laravel Project"

# اسم کانتینر PHP
CONTAINER_NAME=$(docker ps --filter "name=_app" --format "{{.Names}}" | head -n 1)

if [ -n "$CONTAINER_NAME" ]; then
    echo "🐳 Detected Docker container: $CONTAINER_NAME"
    echo "➡️  Fixing permissions inside container..."

    docker exec -it "$CONTAINER_NAME" bash -c "
        chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache &&
        chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    "

    echo "✅ Permissions fixed inside container!"
else
    echo "🖥️  No running container detected. Fixing permissions locally..."

    chmod -R 775 ./storage ./bootstrap/cache
    sudo chown -R www-data:www-data ./storage ./bootstrap/cache

    echo "✅ Permissions fixed locally!"
fi
