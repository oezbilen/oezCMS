# Roadmap

The phases in the order they are built. A phase builds on the ones before it; there are
no dates. This document changes in the pull request that completes or reorders a phase.

Each security goal a phase must meet is listed in
[security/invariants.md](../security/invariants.md) and referenced here by its id.

## Phase 0 — Foundation ✅

Runtime core (environment, configuration, container, kernel), database layer, schema
deployment, internationalisation in the schema, quality gates, and a full architecture
review of all of it.

## Phase 1 — Consolidation ✅

Bringing the state in line before new features are built on it.

Done:

* PHP 8.4.27 and current dependencies
* Consistent license metadata (GPL-3.0-or-later)
* Documentation index, decision records, database and architecture documentation
* UTC enforced for every database session
* The deploy log follows the plural naming rule (`oezcms_migrations`)
* Development environment documentation, `.env.example`, a record of the `.env` format
* `SECURITY.md`: how to report a vulnerability
* The deploy log is out of the runtime account's reach
* The comment in `trg_locales_before_update` names where the read cap lives
* Plugin architecture as a decision record, without code
  ([ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md), proposed)
* Integration tests refuse a database not named as a test database (SEC-09)
* Routines and views run with their caller's rights
  ([ADR 0014](../adr/0014-run-routines-and-views-with-the-callers-rights.md), SEC-50)
* Locked dependencies audited daily, Dependabot, actions pinned to commit SHAs (SEC-51)

## Phase 2 — Media (next)

Schema for media and files, storage outside the document root, metadata, console
commands. Covers the storage half of SEC-24, and SEC-60 to SEC-63.

Decided in ADR [0016](../adr/0016-store-media-files-under-their-content-hash.md) (files
under their content hash), [0017](../adr/0017-accept-media-types-by-content-from-an-allow-list.md)
(types by content from an allow-list), [0018](../adr/0018-keep-media-texts-per-locale.md)
(texts per locale) and [0019](../adr/0019-wire-modules-in-the-entry-point.md) (modules wired
in the entry point), all proposed until the code exists.

Left out on purpose: thumbnails and other variants, folders, physical removal of files and
SVG.

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

* Plugin system implementation as designed in
  [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md), SEC-40 to
  SEC-48. Plugins register routes, events and templates, so it follows phase 6.
* Taint analysis with Psalm, once there is HTTP input to trace.
* Mutation testing with Infection, after phase 2. Media is the first module with PHP logic
  of its own, so before it there is little for mutations to measure.
* A rollback drill before the first release: going back to the previous release on a copy,
  code and database, written down as a procedure. Nothing describes a rollback yet.
* First release. From then on migrations are immutable.
