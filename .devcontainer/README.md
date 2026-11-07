# Stockland WordPress + dbDelta Codespaces Lab

This is an isolated development and runtime-test environment, not a production host.

## Runtime
- WordPress with Apache and PHP 8.3.
- MariaDB 10.11.19 and MySQL 8.0.46 on internal Compose networks.
- Node.js 22, Python 3.12, Composer, GitHub CLI and WP-CLI.
- PHP MySQL drivers, repository-locked PostCSS and PHPCS/WPCS dependencies.
- Private browser preview on port 8080. Neither database port is forwarded.

## First checks
Run in the Codespaces terminal:

    git status --short --branch
    git rev-parse HEAD
    php -v
    php -m
    wp --info
    MYSQL_PWD=root-dev-only mysql --host=db-mariadb --user=root -e "SELECT VERSION();"
    MYSQL_PWD=mysql-dev-only mysql --host=db-mysql --user=root -e "SELECT VERSION();"

Then authenticate GitHub CLI and run:

    gh auth login
    bash ci/wp-dbdelta/run-codespaces.sh

The runner downloads exact GitHub Actions artifacts by run ID and verifies their inner installer SHA-256 before testing. The old 16.2.9 artifact is used only to reproduce the historical approval-default defect. The corrected artifact is tested for preservation of DRAFT.

## Data safety
All databases and WordPress installs are synthetic and disposable. Never load production exports, credentials, employee/customer documents, salary data, payroll, sales, inventory moves, journals or payments. Keep port 8080 private. Never publish database port 3306.

The runtime suite keeps MySQL failures visible. Its current protected-baseline blocker is the nullable composite primary key in stock balances; a MariaDB pass cannot override a MySQL failure.

The original 16.2.13 package/source is not currently available as a pinned GitHub artifact. Testing that operational baseline remains NOT RUN until it is recovered.
