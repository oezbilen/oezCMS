# Testing Guidelines

## Purpose

The oezCMS test suite follows a strict Test-Driven Development (TDD) approach. Every test must be deterministic, isolated, and reproducible on any development machine or CI environment.

---

## General Principles

* Write the test before implementing the feature.
* Keep tests small and focused.
* Every test must be independent.
* Tests must never depend on execution order.
* Tests must always produce the same result.

---

## Test Isolation

Each test must create its own data and clean up after itself.

Allowed:

* Temporary directories
* Temporary files
* In-memory objects
* Test-specific database records

Avoid:

* Shared writable files
* Shared state between tests
* Global mutable state
* Depending on previous test execution

---

## Temporary Files

Tests that require files must create them in a temporary directory.

Example:

* `sys_get_temp_dir()`
* Project runtime test directory

Temporary files and directories must always be removed during `tearDown()`.

Repository fixtures must never be modified during test execution.

---

## Fixtures

Fixtures are intended for static resources only.

Examples:

* SQL files
* JSON
* XML
* YAML
* Images
* Translation files
* Template files

Fixtures must be treated as read-only.

---

## Environment Variables

Tests must never rely on the developer's local environment.

Environment variables required by a test must be explicitly created during the test and removed afterwards.

---

## Database Tests

Database tests must:

* use a dedicated test database
* never connect to a production database
* clean up created data
* be repeatable

Integration tests live in `tests/Integration` and require a dedicated MariaDB test database configured via `TEST_DB_HOST`, `TEST_DB_PORT`,
`TEST_DB_NAME`, `TEST_DB_USERNAME` and `TEST_DB_PASSWORD`. When these variables are absent the integration suite is skipped. Run it locally
with `composer test:integration`.

---

## Assertions

Every test should verify one specific behavior.

Avoid combining multiple unrelated assertions in a single test.

---

## Naming

Test methods should describe behavior.

Good examples:

* `testLoadsEnvironmentFile()`
* `testIgnoresCommentLines()`
* `testDoesNotOverrideExistingEnvironmentVariables()`

Avoid generic names such as:

* `testOne()`
* `testEnvironment()`
* `testConfig()`

---

## Performance

Tests should execute as quickly as possible.

Avoid unnecessary I/O, network access, or expensive initialization.

---

## Continuous Integration

Every commit must keep the test suite green.

No feature should be merged unless:

* all tests pass
* static analysis passes
* coding standards are satisfied

---

## Philosophy

The test suite is considered part of the application.

A feature is not complete until it is covered by automated tests.
