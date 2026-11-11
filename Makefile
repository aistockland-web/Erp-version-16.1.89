SHELL := /usr/bin/env bash
.RECIPEPREFIX := >

.PHONY: codespaces-validate packages-static packages-e2e mysql8-e2e smoke

codespaces-validate:
>python3 -m json.tool .devcontainer/devcontainer.json >/dev/null
>for script in .devcontainer/initialize-host.sh .devcontainer/post-create.sh scripts/codespaces/bootstrap_wordpress_sites.sh scripts/codespaces/bootstrap_mysql8_site.sh scripts/codespaces/smoke_test_sites.sh scripts/codespaces/run_mysql8_parity.sh; do bash -n "$$script"; done
>python3 ci/codespaces/validate_candidate_packages.py --extract-root /tmp/stockland-candidates

packages-static:
>npm ci --prefix ci/cssqa --ignore-scripts --no-audit --no-fund
>python3 ci/codespaces/validate_candidate_packages.py --extract-root /tmp/stockland-candidates
>node ci/codespaces/check_web_assets.js /tmp/stockland-candidates/16.2.13/stockland-erp-app
>node ci/codespaces/check_web_assets.js /tmp/stockland-candidates/16.2.15/stockland-erp-app

packages-e2e:
>bash .devcontainer/initialize-host.sh
>docker compose -f .devcontainer/docker-compose.yml up -d --build
>docker compose -f .devcontainer/docker-compose.yml exec workspace bash .devcontainer/post-create.sh
>docker compose -f .devcontainer/docker-compose.yml exec workspace bash scripts/codespaces/smoke_test_sites.sh

mysql8-e2e:
>bash scripts/codespaces/run_mysql8_parity.sh

smoke:
>bash scripts/codespaces/smoke_test_sites.sh
