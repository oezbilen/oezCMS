# Documentation

Each topic has exactly one home. A new document goes into the area it belongs to and is
listed here.

## Architecture

* [Architecture overview](architecture/overview.md) — building blocks, start-up, database accounts, what is not built yet

## Project

* [Roadmap](project/roadmap.md) — the phases in build order, what is done and what is open
* [Contributing](project/contributing.md) — requirements, setup, development principles, review process
* [Branching strategy](project/branching-strategy.md) — branch names, commits, pull requests, merging
* [Release process](project/release-process.md) — versioning and the release workflow

## Development

* [Development environment](development/environment.md) — requirements, setup, the `.env` format, running tests and quality gates
* [Coding standards](development/coding-standards.md) — language rules, naming, formatting, static analysis
* [Module boundaries](development/module-boundaries.md) — the layers, what each may depend on, how that is enforced
* [PSR interfaces](development/psr-interfaces.md) — which PHP standards the project commits to, and when
* [Testing guidelines](development/testing-guidelines.md) — isolation, fixtures, database tests, CI

## Database

* [Schema conventions](database/schema-conventions.md) — names, keys, column types, collations, where an invariant goes

## Deployment

* [Database connection](deployment/database-connection.md) — connection variables, socket or TCP, TLS, timeouts
* [Database privileges](deployment/database-privileges.md) — separate runtime and migration accounts, grants
* [Schema deployment](deployment/schema-deployment.md) — `db:deploy`, its phases, migration states, recovery

## Security

* [Security invariants](security/invariants.md) — every security goal as a testable statement, with its status

## Decisions

* [Architecture decision records](adr/README.md) — why the system is the way it is, one decision per record
