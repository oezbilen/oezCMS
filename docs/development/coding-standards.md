# Coding Standards

## Purpose

This document defines the coding standards for the oezCMS project.

The primary goals are:

* Consistency
* Readability
* Maintainability
* Testability
* Security
* Long-term stability

All source code should follow these guidelines.

---

# General Principles

* Keep code simple.
* Prefer readability over cleverness.
* Avoid premature optimization.
* Write self-documenting code.
* Keep functions and classes focused on a single responsibility.
* Every change should be covered by automated tests.

---

# PHP Version

The minimum supported version is:

```text
PHP 8.4
```

Do not introduce compatibility code for older PHP versions.

Always use modern language features when they improve readability.

---

# File Structure

Every PHP source file must begin with:

```php
<?php

declare(strict_types=1);
```

The namespace declaration follows immediately afterwards.

---

# Strict Types

All PHP files must use:

```php
declare(strict_types=1);
```

No exceptions.

---

# Namespaces

Namespaces follow the PSR-4 structure.

Example:

```text
OezCMS\Core
OezCMS\Database
OezCMS\Security
OezCMS\Console
```

Namespaces must match the directory structure.

---

# Class Design

Prefer:

* `final` classes
* immutable objects
* constructor injection

Avoid inheritance unless there is a clear architectural reason.

Composition is preferred over inheritance.

---

# Readonly

Use `readonly` whenever an object should not change after construction.

Example:

```php
public readonly Config $config;
```

Immutable objects are preferred whenever practical.

---

# Constructors

Use constructor property promotion where appropriate.

Example:

```php
public function __construct(
    private readonly Config $config
) {
}
```

Avoid unnecessary assignments inside constructors.

---

# Dependency Injection

Never instantiate services inside business logic.

Preferred:

```php
$database = $container->get(Database::class);
```

Avoid:

```php
new Database(...);
```

outside bootstrap code.

---

# Type Declarations

Always declare parameter types.

Always declare return types.

Avoid mixed types.

Prefer:

```php
public function load(): void
```

instead of:

```php
public function load()
```

---

# Properties

Properties should always declare visibility.

Avoid public mutable properties.

Preferred:

```php
private readonly Config $config;
```

---

# Methods

Methods should be short.

Aim for one responsibility per method.

Extract complex logic into private helper methods.

---

# Visibility

Use the most restrictive visibility possible.

Preferred order:

* private
* protected
* public

---

# Exceptions

Throw specific exceptions.

Avoid generic RuntimeException where a domain-specific exception exists.

Example:

```text
EnvironmentException
ConfigurationException
DatabaseException
```

---

# Naming

Use descriptive names.

Classes:

```text
Environment
Config
DatabaseConnection
PluginManager
```

Methods:

```text
load()
boot()
connect()
register()
```

Boolean methods:

```text
isLoaded()
hasPlugin()
canWrite()
```

Variables:

```text
$database
$environment
$config
```

Avoid abbreviations.

---

# Comments

Write code that explains itself.

Use comments only when explaining intent.

Avoid comments that simply repeat the code.

Good:

```php
// Prevent overriding system environment variables.
```

Bad:

```php
// Increment counter.
$counter++;
```

---

# PHPDoc

Use PHPDoc only when it adds value.

Examples:

* templates
* generics
* complex array structures
* public APIs

Do not duplicate information already expressed through type declarations.

---

# Formatting

Follow PSR-12.

Additionally:

* Four spaces for indentation.
* Unix line endings (LF).
* UTF-8 encoding.
* One class per file.
* One statement per line.

---

# Strings

Use single quotes unless interpolation is required.

Preferred:

```php
'Hello'
```

Use double quotes only when needed.

---

# Arrays

Prefer short array syntax.

```php
[]
```

instead of

```php
array()
```

---

# Control Structures

Always use braces.

Preferred:

```php
if ($condition) {
    ...
}
```

Avoid single-line statements without braces.

---

# Magic Values

Avoid magic strings and magic numbers.

Use constants or enums where appropriate.

---

# Enums

Use enums instead of string constants whenever possible.

Example:

```text
BootState
UserStatus
PluginState
```

---

# Security

Never trust user input.

Always validate external data.

Always use prepared statements.

Escape output according to its target context.

Never store plaintext passwords.

---

# Logging

Never use:

```php
var_dump()
print_r()
die()
exit()
```

inside application code.

Use the project's logging facilities.

CLI entry points may return exit codes.

---

# Testing

Every new feature must include tests.

Bug fixes should include regression tests.

Tests must follow the project's Testing Guidelines.

---

# Static Analysis

All code must pass:

* PHPStan Level 8+

Warnings must not be ignored without justification.

---

# Architecture

The project follows a database-first architecture.

Core principles:

* Dependency Injection
* Immutable configuration
* Plugin-driven architecture
* Separation of concerns
* Single Responsibility Principle

Business logic belongs in services.

Bootstrap logic belongs in the Kernel.

Console and HTTP entry points should remain thin.

---

# Philosophy

Code is written for humans first and computers second.

Readable code outlives clever code.

Consistency is more valuable than personal preference.
