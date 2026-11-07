# Stockland ERP Codespaces environment

This development container is an isolated engineering and QA workspace. It is not a production host.

## Included tools

- PHP 8.3 CLI and Composer from the Dev Containers PHP image.
- Node.js 22 and Python 3.12 for the existing build and QA scripts.
- GitHub CLI, MariaDB client, jq, ripgrep, shellcheck, zip and unzip.
- A private MariaDB 10.11 service on the internal Compose network.
- Locked PostCSS QA and PHPCS/WPCS dependencies from the repository lockfiles.
- WP-CLI installed only after its upstream SHA-256 sidecar matches the downloaded PHAR.
- PHP, YAML, Markdown, JavaScript, Docker and GitHub Pull Request editor extensions.

## Ports

Port 8080 is reserved for a local WordPress/plugin test preview. When a test server is started on 0.0.0.0:8080, Codespaces forwards it privately. The MariaDB port 3306 is internal to the Compose network and is not forwarded.

## Database safety

The Compose database is an isolated test database named stockland_dev. It permits an empty root password only inside the private, unpublished Compose network to keep first-time setup non-interactive. It is for synthetic test fixtures only.

Never import production databases, salary or employee documents, customer data, business secrets or production credentials into this service. Do not expose port 3306 or change its visibility to public.

## First terminal checks

Open Terminal, choose New Terminal, and run:

~~~bash
git status --short --branch
git rev-parse HEAD
php -v
composer --version
node -v
python3 --version
wp --info
mariadb --host=db --user=root stockland_dev -e "SELECT VERSION();"
~~~

The workspace setup command installs the repository's locked CSS QA and PHPCS/WPCS dependencies. GitHub Actions remains authoritative for the PHP 8.3/8.4 matrix and exact-package release gates.

## Start a private preview

Do not start a preview until an exact candidate package has been selected and extracted into a separate, disposable WordPress test instance. Start the test server bound to 0.0.0.0:8080; open the Ports tab in Codespaces and use the private forwarded link. Never use production credentials or production data for a preview.

## Deliberately not installed

No WooCommerce, third-party POS, inventory engine, user/role replacement, paid AI extension, production SMTP, production backup destination or production security agent is installed automatically. Stockland already has custom ERP/POS and authorization workflows. New plugins must be justified by a documented gap and tested for REST, capability, schema and performance compatibility before adoption.
