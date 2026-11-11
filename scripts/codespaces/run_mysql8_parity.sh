#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/../.."
if ! docker info >/dev/null 2>&1; then
  echo "Docker-outside-of-Docker is unavailable in this terminal." >&2
  exit 1
fi

docker compose -f .devcontainer/docker-compose.yml --profile mysql8 up -d mysql8 wordpress_mysql8
bash scripts/codespaces/bootstrap_mysql8_site.sh
bash scripts/codespaces/smoke_test_sites.sh

echo "PASS optional MySQL 8.0.46 candidate compatibility environment."
