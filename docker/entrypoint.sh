#!/bin/bash
set -e

# Capture environment for cron with proper quoting
while IFS='=' read -r key value; do
  printf 'export %s="%s"\n' "$key" "$value"
done < <(printenv) > /var/www/env_vars

# Start cron daemon for email worker
cron

# Run the command provided by Docker Compose/Dockerfile.
exec "$@"
