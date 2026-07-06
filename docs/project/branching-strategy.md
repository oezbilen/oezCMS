# Branching Strategy

## Purpose

This document defines the Git branching strategy used by the oezCMS project.

The goals are:

* Maintain a clean Git history
* Keep changes isolated
* Simplify code reviews
* Enable stable releases
* Support collaborative open-source development

---

# Main Branch

## `main`

The `main` branch is always considered production-ready.

Rules:

* Never commit directly to `main`.
* Every change must be introduced through a Pull Request.
* All tests must pass before merging.
* Static analysis must pass before merging.

---

# Feature Branches

All new functionality must be developed in feature branches.

Naming convention:

```text
feature/<name>
```

Examples:

```text
feature/environment
feature/database
feature/kernel
feature/console-cli
feature/plugin-system
feature/i18n
feature/authentication
```

Each feature branch should focus on a single feature or subsystem.

---

# Documentation Branches

Documentation changes should be developed separately.

Naming convention:

```text
docs/<topic>
```

Examples:

```text
docs/development
docs/project
docs/architecture
```

Documentation branches may contain multiple related commits.

---

# Bug Fix Branches

Bug fixes should use:

```text
fix/<name>
```

Examples:

```text
fix/environment-loader
fix/plugin-discovery
fix/router
```

Bug fixes should remain as small as possible.

---

# Refactoring Branches

Large refactorings should be isolated.

Naming convention:

```text
refactor/<name>
```

Examples:

```text
refactor/container
refactor/database
refactor/kernel
```

No new functionality should be introduced during refactoring.

---

# Release Branches

Release preparation should happen in dedicated branches.

Naming convention:

```text
release/<version>
```

Examples:

```text
release/1.0.0
release/1.1.0
release/2.0.0
```

Release branches are used for:

* final testing
* documentation updates
* version changes
* release notes

No new features should be added.

---

# Hotfix Branches

Critical production fixes use:

```text
hotfix/<name>
```

Examples:

```text
hotfix/security
hotfix/sql-injection
hotfix/authentication
```

Hotfixes should be merged into both `main` and the active development branch.

---

# Branch Lifetime

Branches should be short-lived.

Small branches are preferred over long-running branches.

Merge completed work promptly.

Delete merged branches after integration.

---

# Commit Strategy

Commits should remain small and focused.

Each commit should:

* compile successfully
* pass all tests
* represent one logical change

Avoid combining unrelated changes.

---

# Commit Messages

The project follows the Conventional Commits specification.

Format:

```text
<type>(<scope>): <subject>

<body>

<footer>
```

Examples:

```text
feat(environment): add environment loader

fix(database): handle connection timeout

refactor(kernel): simplify boot process

docs(project): add branching strategy

test(environment): add parser tests

chore(ci): update GitHub Actions
```

---

# Pull Requests

Each Pull Request should address a single topic.

A Pull Request should include:

* a clear description
* linked issue (if applicable)
* passing tests
* updated documentation when required

Large Pull Requests should be avoided.

---

# Merge Strategy

Use **Squash and Merge** for feature branches unless preserving individual commits provides clear value.

This keeps the `main` branch readable while allowing detailed development history within feature branches.

---

# Development Workflow

Typical workflow:

```text
main
   │
   ├── feature/environment
   │        │
   │        └── Merge
   │
   ├── feature/database
   │        │
   │        └── Merge
   │
   ├── feature/kernel
   │        │
   │        └── Merge
   │
   └── feature/plugin-system
```

Every feature starts from the latest `main`.

---

# Continuous Integration

Every branch should pass:

* PHPUnit
* PHPStan
* future coding style checks

Broken branches should never be merged.

---

# Philosophy

Branches represent isolated units of work.

Small, focused branches are easier to review, easier to test, and easier to maintain than long-running development branches.
