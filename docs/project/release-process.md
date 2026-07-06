# Release Process

## Purpose

This document defines the release workflow for oezCMS.

The goal is to ensure stable, reproducible, and well-documented releases.

---

# Versioning

oezCMS follows **Semantic Versioning (SemVer)**:

```text id="ver1"
MAJOR.MINOR.PATCH
```

## Version Rules

* **MAJOR**: Breaking changes (incompatible API changes)
* **MINOR**: New features (backward compatible)
* **PATCH**: Bug fixes (backward compatible)

---

# Branching Model for Releases

Releases are prepared in dedicated branches:

```text id="br1"
release/<version>
```

Examples:

```text id="br2"
release/1.0.0
release/1.1.0
release/2.0.0
```

No new features are allowed in release branches.

Only:

* bug fixes
* documentation updates
* version bump
* final testing

---

# Release Workflow

## 1. Create Release Branch

```bash id="wf1"
git checkout -b release/1.0.0 main
```

---

## 2. Final Testing

Before release:

* run full test suite
* run static analysis
* validate plugin system
* check database migrations

```bash id="wf2"
vendor/bin/phpunit
vendor/bin/phpstan analyse src --level=8
```

---

## 3. Version Bump

Update version in:

* `composer.json`
* `config/app.php` (if applicable)
* documentation

---

## 4. Changelog

Each release must include a changelog entry:

```text id="wf3"
docs/changelog/1.0.0.md
```

Content includes:

* new features
* bug fixes
* breaking changes
* migration notes

---

## 5. Merge into Main

After approval:

```text id="wf4"
release/1.0.0 → main
```

Use **squash merge** or **merge commit**, depending on release complexity.

---

## 6. Tag Release

Every release must be tagged:

```bash id="wf5"
git tag -a v1.0.0 -m "oezCMS 1.0.0"
git push origin v1.0.0
```

---

## 7. Post-Release

After tagging:

* merge `main` back into development branches if needed
* close release branch
* update roadmap

---

# Hotfix Releases

Critical fixes follow a separate workflow:

```text id="hf1"
hotfix/<issue>
```

Example:

```text id="hf2"
hotfix/security-patch
```

Workflow:

1. branch from `main`
2. apply minimal fix
3. test thoroughly
4. merge into `main`
5. tag new patch version

---

# Release Criteria

A release is only valid if:

* all tests pass
* PHPStan Level 8 passes
* no known critical bugs exist
* documentation is updated
* no debug code remains

---

# CI Requirements

Every release must be validated in CI:

* PHPUnit
* PHPStan
* Composer install (clean environment)

---

# Backward Compatibility

* Minor and patch releases must not break public APIs
* Breaking changes require a major version bump
* Deprecated features should be marked before removal

---

# Philosophy

Releases must be predictable.

A release is not a feature dump, but a **controlled, stable snapshot** of the system.
