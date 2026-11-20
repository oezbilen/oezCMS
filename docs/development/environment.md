# Development Environment

Everything needed to run oezCMS and its tests locally. How the connection is configured
in detail is in [database-connection.md](../deployment/database-connection.md).

## Requirements

* PHP 8.4.27 or later with `pdo`, `pdo_mysql`, `json`, `curl`, `gd`, `openssl`, `sodium`,
  `mbstring`, and `pdo_sqlite` for the tests
* Composer 2
* MariaDB 11
* Git

## Setup

```bash
git clone https://github.com/oezbilen/oezCMS.git
cd oezCMS
composer install
cp .env.example .env
```

Create the databases and one account per purpose. Development uses the same split as
production, so a privilege the application should not have fails here first.

```sql
CREATE DATABASE oezcms          CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
CREATE DATABASE oezcms_log      CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
CREATE DATABASE oezcms_test     CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
CREATE DATABASE oezcms_test_log CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;

CREATE USER 'oezcms_runtime'@'localhost' IDENTIFIED BY '…';
CREATE USER 'oezcms_deploy'@'localhost'  IDENTIFIED BY '…';
CREATE USER 'oezcms_test'@'localhost'    IDENTIFIED BY '…';

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON oezcms.* TO 'oezcms_runtime'@'localhost';

GRANT ALL PRIVILEGES ON oezcms.* TO 'oezcms_deploy'@'localhost';
GRANT CREATE, SELECT, INSERT, UPDATE, DELETE ON oezcms_log.* TO 'oezcms_deploy'@'localhost';

GRANT ALL PRIVILEGES ON oezcms_test.* TO 'oezcms_test'@'localhost';
GRANT ALL PRIVILEGES ON oezcms_test_log.* TO 'oezcms_test'@'localhost';
```

| Account | Used by | Rights |
|---|---|---|
| `oezcms_runtime` | the application (`DB_USERNAME`) | data and routine calls in `oezcms`, no DDL, nothing in `oezcms_log` |
| `oezcms_deploy` | `db:deploy` (`DB_MIGRATION_USERNAME`) | the schema in `oezcms`, the deploy log in `oezcms_log` |
| `oezcms_test` | the integration tests (`TEST_DB_USERNAME`) | the two test databases only |

With separate accounts the deploy log lives in a database of its own
(`DB_MIGRATION_LOG_DATABASE`), because the runtime's grant on `oezcms` would otherwise
cover it ([ADR 0012](../adr/0012-keep-the-deploy-log-out-of-the-runtimes-reach.md)).

`ALL PRIVILEGES` for the deploy account is a simplification for development. The exact
list a production deploy account needs is in
[database-privileges.md](../deployment/database-privileges.md#deployment).

`'…'@'localhost'` matches connections through the Unix socket. When connecting over TCP
to `127.0.0.1` on a server running with `skip-name-resolve` (the MariaDB container image
does), create the accounts for `'…'@'127.0.0.1'` instead, or connect through
`DB_SOCKET`.

Enter the credentials in `.env`, then deploy the schema and check the installation:

```bash
php bin/console db:deploy
php bin/console version
```

## The `.env` file

`.env` is never committed. Values already present in the process environment take
precedence over the file, so a deployment can configure everything without one.

The format is deliberately strict ([ADR 0011](../adr/0011-parse-env-with-a-strict-parser-of-our-own.md)).
Every error names the file, the line and the reason.

| Rule | Example |
|---|---|
| One `KEY=VALUE` per line; keys match `[A-Za-z_][A-Za-z0-9_]*` | `DB_NAME=oezcms` |
| Empty lines and lines starting with `#` are ignored | `# comment` |
| An unquoted value ends at ` #` (space, hash) | `DB_PORT=3306 # default` |
| Single quotes are literal and cannot contain a single quote | `DB_PASSWORD='p@$$'` |
| Double quotes understand `\"`, `\\`, `\n`, `\t` and nothing else | `DB_PASSWORD="a\"b"` |
| After a closing quote only a comment may follow | `KEY="value" # note` |
| A key may appear only once | |
| No interpolation: `$` is an ordinary character | `DB_PASSWORD=pa$word` |
| No `export` prefix | |

Application values:

| Variable | Default | Notes |
|---|---|---|
| `APP_ENV` | `production` | One of `production`, `staging`, `development`, `testing` |
| `APP_DEBUG` | `false` | `true`/`1`/`yes`/`on` or `false`/`0`/`no`/`off`; refused in production |

The database values are listed in
[database-connection.md](../deployment/database-connection.md#variables).

## Tests

| Command | Runs |
|---|---|
| `composer test` | Both suites |
| `composer test:unit` | Unit and architecture tests, no database |
| `composer test:integration` | Tests against MariaDB |

`phpunit.xml` forces `APP_ENV=testing` and `APP_DEBUG=true`.

The integration tests read their connection from the process environment only. Values in
`.env` are not used. An empty variable counts as unset.

| Variable | Default |
|---|---|
| `TEST_DB_NAME` | required |
| `TEST_DB_USERNAME` | required |
| `TEST_DB_LOG_NAME` | `TEST_DB_NAME` followed by `_log` |
| `TEST_DB_HOST` | `127.0.0.1` |
| `TEST_DB_PORT` | `3306` |
| `TEST_DB_PASSWORD` | empty |

```bash
TEST_DB_NAME=oezcms_test TEST_DB_USERNAME=oezcms_test TEST_DB_PASSWORD='…' composer test:integration
```

Without `TEST_DB_NAME` or `TEST_DB_USERNAME` the integration tests are skipped, so
`composer test` also runs on a machine without a database. CI runs them with
`--fail-on-skipped`, because there a skip can only mean a broken workflow.

The tests deploy the schema and use the data through one connection, that is, as a single
account. The schema tests therefore keep the deploy log where a single account keeps it, in
`TEST_DB_NAME`: after a run, `oezcms_migrations` is found there. Only the tests covering a
separate log database write to `TEST_DB_LOG_NAME`, and they drop their table again, so that
database is normally empty. The test account needs full rights on both.

> **The test database is wiped.** Before each schema test every event, trigger, view,
> table and routine in `TEST_DB_NAME` is dropped and the schema is deployed fresh. Never
> point it at a database whose content matters.

The tests therefore refuse databases whose names do not say they are throwaway. Before
anything is dropped, every integration test fails with the reason unless:

* both names are lower case,
* `TEST_DB_NAME` ends in `_test` and `TEST_DB_LOG_NAME` in `_test_log`, and
* neither is the database that `DB_NAME` or `DB_MIGRATION_LOG_DATABASE` names in the
  process environment.

Right before the wipe, the tests also check that the connection really selected
`TEST_DB_NAME`. A name only states what a database is for, so never give such a name to
a database whose content matters.

## Quality gates

| Command | Checks |
|---|---|
| `composer cs:check` | PSR-12 and project style, without changing files |
| `composer cs:fix` | Applies the style |
| `composer analyse` | PHPStan level 10 with strict rules |
| `composer quality` | `cs:check`, `analyse` and `test` in one go |

CI runs the same three gates as separate jobs, the tests against a MariaDB 11 service
(`.github/workflows/php-quality.yml`). A change is ready for review when `composer quality`
passes locally.
