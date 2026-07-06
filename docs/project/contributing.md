# Contributing to oezCMS

## Purpose

Thank you for considering contributing to oezCMS.

This document defines the rules and expectations for contributing to the project. The goal is to ensure a consistent, maintainable, and high-quality codebase.

---

# Code of Conduct

All contributors are expected to act respectfully and constructively.

* No harassment or disrespectful behavior
* Focus on technical arguments, not personal opinions
* Be open to feedback and code review discussions

---

# Getting Started

## Requirements

Before contributing, ensure you have:

* PHP 8.4 or higher
* Composer installed
* MariaDB 11 (for full integration testing)
* Git

---

## Setup

```bash id="contrib2"
git clone https://github.com/oezbilen/oezCMS.git
cd oezCMS
composer install
cp .env.example .env
```

Run tests:

```bash id="contrib3"
vendor/bin/phpunit
```

---

# Branching

All work must be done in a dedicated branch.

See:

```
docs/project/branching-strategy.md
```

Examples:

* `feature/environment`
* `feature/database`
* `fix/plugin-loader`
* `docs/development`

Never commit directly to `main`.

---

# Development Principles

## 1. Test-Driven Development (TDD)

All new features must follow TDD:

* Write failing tests first
* Implement minimal code to pass tests
* Refactor after tests pass

---

## 2. Small Changes

Keep changes small and focused.

Each Pull Request should:

* Address a single concern
* Be easy to review
* Pass all tests

---

## 3. Code Style

All code must follow:

```
docs/development/coding-standards.md
```

Key rules:

* PHP 8.4 strict types
* PSR-12 compliant formatting
* Immutable design where possible
* Dependency Injection only

---

## 4. Testing Requirements

Every contribution must include tests.

Required:

* Unit tests for logic
* Regression tests for bug fixes

Run tests:

```bash id="contrib4"
vendor/bin/phpunit
```

---

## 5. Static Analysis

Code must pass PHPStan:

```bash id="contrib5"
vendor/bin/phpstan analyse src --level=8
```

No warnings are allowed.

---

## 6. Commit Messages

We use Conventional Commits:

Format:

```text
<type>(<scope>): <subject>

<body>

<footer>
```

Examples:

```text id="contrib6"
feat(environment): add .env loader
fix(database): resolve connection issue
refactor(kernel): simplify boot process
docs(project): add contributing guide
test(environment): add parser tests
```

---

## 7. Pull Requests

Every Pull Request must include:

* Clear description of changes
* Linked issue (if applicable)
* Passing tests
* No PHPStan errors

---

## Review Process

All changes will be reviewed.

A Pull Request will be merged when:

* tests pass
* code meets standards
* design is consistent with architecture
* at least one reviewer approves

---

## Architecture Rules

The project follows a strict architecture:

* Database-first design
* Plugin-driven system
* Dependency Injection via Kernel Container
* No business logic in controllers or console entry points
* Services must be testable in isolation

---

## What NOT to do

Avoid:

* Large unrelated changes
* Mixing refactoring and feature work
* Direct commits to `main`
* Skipping tests
* Introducing global state

---

## Philosophy

oezCMS is designed to be:

* predictable
* modular
* testable
* extensible

Contributions should improve clarity, not complexity.
