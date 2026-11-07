# Stockland WordPress + dbDelta Codespaces Lab

This is an isolated development and runtime-test environment, not a production host.

## Runtime
- WordPress with Apache/PHP 8.3.
- MariaDB 10.11.19 and MySQL 8.0.46 on separate internal Compose networks.
- Node 22, Python 3.12, Composer, GitHub CLI, WP-CLI.
- Repository-locked PostCSS and PHPCS/WPCS dependencies.
- Private browser preview on port 8080. Database ports are not forwarded.

## First checks

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

The harness downloads only pinned GitHub Actions artifacts and verifies inner installer hashes. It reproduces the historical compensation-approval defect using the pre-fix package and tests the corrected package for preservation of DRAFT.

## Data safety
All databases and WordPress installs are synthetic and disposable. Never load production exports, credentials, employee/customer documents, payroll, sales, inventory movements, journals or payments. Keep port 8080 private. Never publish database port 3306.

The harness keeps MySQL failures visible. The known protected-baseline blocker is the nullable composite primary key in stock balances; a MariaDB pass does not override a MySQL failure.

The exact 16.2.13 package/source is not yet available as a pinned GitHub artifact. Testing that operational baseline remains NOT RUN until recovered.
