# Roadmap

The phases in the order they are built. A phase builds on the ones before it; there are
no dates. This document changes in the pull request that completes or reorders a phase.

Each security goal a phase must meet is listed in
[security/invariants.md](../security/invariants.md) and referenced here by its id.

## Phase 0 — Foundation ✅

Runtime core (environment, configuration, container, kernel), database layer, schema
deployment, internationalisation in the schema, quality gates, and a full architecture
review of all of it.

## Phase 1 — Consolidation (current)

Bringing the state in line before new features are built on it.

Done:

* PHP 8.4.27 and current dependencies
* Consistent license metadata (GPL-3.0-or-later)
* Documentation index, decision records, database and architecture documentation
* UTC enforced for every database session

Open:

* Development environment documentation, `.env.example`, a record of the `.env` format
* `SECURITY.md`: how to report a vulnerability
* Bring `oezcms_migration` in line with the plural naming rule, or record the exception
  ([ADR 0002](../adr/0002-name-schema-objects-by-type-and-module.md))
* Correct the outdated comment in `trg_locales_before_update` (the read cap now lives in
  `v_i18n_locale_chains`)
* Plugin architecture as a decision record, without code. Whether discovery may scan a
  directory at all follows from SEC-40 and has to be settled before any manifest format.

## Phase 2 — Media

Schema for media and files, storage outside the document root, metadata, console
commands. Covers the storage half of SEC-24.

**Delivery is not part of this phase.** Files are served through the application, which
needs the HTTP layer of phase 6. Until then media is complete as data and storage only.

## Phase 3 — Users

User core, salutations, profiles with a profile image from media.

## Phase 4 — Roles and permissions

Roles, permissions and their assignment (RBAC).

## Phase 5 — Audit

Audit table and the trigger convention that writes it. SEC-30, SEC-31.

Built before authentication on purpose, so that every administrative action is audited
from the first one onwards.

## Phase 6 — HTTP and authentication

The HTTP layer and the authentication built on it, together:

* Front controller, PSR-7/17 messages, PSR-15 middleware, routing, error pages, security
  headers, templating — SEC-20 to SEC-23
* Media delivery through the application — the delivery half of SEC-24
* Password hashing with pinned parameters and rehashing — SEC-10, SEC-11
* Password history, attempt limiting, reset, remember-me — SEC-12, SEC-14
* Sessions and devices, expiry through a scheduled database event — SEC-13

## Phase 7 — Multi-factor authentication

TOTP, passkeys through WebAuthn, YubiKey, recovery codes, e-mail. SEC-15, SEC-16.

## Phase 8 — Settings and integrations

Settings (the translation fallback depth becomes one of them), social links, license
information, API keys. Entities exposed through the API carry a `public_id`
([ADR 0004](../adr/0004-size-surrogate-keys-to-the-table.md)).

## Not scheduled

* Plugin system implementation — plugins register routes, events and templates, so it
  follows phase 6. SEC-40, SEC-41.
* Taint analysis with Psalm, once there is HTTP input to trace.
* `SQL SECURITY INVOKER` for routines, see
  [database-privileges.md](../deployment/database-privileges.md).
* First release. From then on migrations are immutable.
