#!/bin/bash

# Get current project path (absolute)
CURRENT_PROJECT_PATH=$(pwd)

echo "✅ Stopping all other Docker Compose projects..."

# Find all docker-compose.yml files in subdirectories of PhpstormProjects
find ~/PhpstormProjects -type f -name "docker-compose.yml" | while read compose_file; do
    PROJECT_DIR=$(dirname "$compose_file")

    # Skip the current project
    if [[ "$PROJECT_DIR" != "$CURRENT_PROJECT_PATH"* ]]; then
        echo "🛑 Shutting down project in: $PROJECT_DIR"
        (cd "$PROJECT_DIR" && docker-compose down)
    fi
done

# Start the current project
echo "🚀 Starting current project in: $CURRENT_PROJECT_PATH"
docker-compose up -d
