# Stockland ERP Codespaces — WordPress and database lab

This is an isolated development/QA environment, not a production host.

## Included
- PHP 8.3 workspace, Composer, Node.js 22, Python 3.12 and GitHub CLI.
- WordPress Apache/PHP 8.3 sites.
- Separate MariaDB 10.11 and MySQL 8.0.46 instances.
- WP-CLI, locked PostCSS and PHPCS/WPCS tooling.
- Exact SHA-256, ZIP CRC, path-safety and file inventory checks for .13 and .15.
- A .13-to-.15 update exercise against both database engines.

## Private previews
| URL | Service |
|---|---|
| http://localhost:8080 | WordPress + MariaDB 10.11 |
| http://localhost:8081 | WordPress + MySQL 8.0.46 |

Both ports are private Codespaces forwards. Databases are internal only; do not publish or forward port 3306.

## Checks
~~~bash
git status --short --branch
git rev-parse HEAD
php -v
composer --version
node -v
python3 --version
wp --info
docker compose -f .devcontainer/docker-compose.yml config --quiet
~~~

The post-create script verifies both tracked archives, installs locked QA tooling and bootstraps WordPress. Reports and disposable SQL dumps are saved under artifacts/codespaces/, which is Git-ignored.

Rerun package identity checks:
~~~bash
python scripts/codespaces/verify_candidate_archives.py --report artifacts/codespaces/package-intake.json --extract-root /tmp/stockland-candidates
~~~

Rerun the WordPress update exercise:
~~~bash
bash scripts/codespaces/bootstrap_wordpress.sh
~~~

## Safety
- Use only the candidate ZIPs and synthetic WordPress data.
- Never load production backups, credentials, employee/customer/supplier data, payroll or HR documents.
- Compose credentials are disposable and must never be reused.
- Never publish/forward DB ports.
- The .15 ZIP is a review candidate, not an approved production release.
- Do not install generic POS, inventory, finance, HR or role-management plugins. Stockland already has custom services and workflows.
