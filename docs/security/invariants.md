# Security Invariants

Each entry below is written so that it can be shown false. "Uses OWASP guidance" cannot
be; "the Nth failed attempt within the window is refused" can.

The status column names either the test that holds an invariant or what it is waiting on.
A pending entry is a commitment rather than a wish: when the subsystem is built, the
invariant is built with it and the entry gets a test name. An entry is never removed
because it turned out to be inconvenient — it is satisfied, or it is changed with the
reason recorded.

Ids are numbered with gaps so a group can grow without renumbering the rest.

## Held today

| Id | Invariant | Held by |
|---|---|---|
| SEC-01 | An exception message never contains query parameter values. | `DatabaseExceptionTest::testDoesNotExposeParameterValuesInMessage` |
| SEC-02 | A connection never executes more than one statement per call. | `MariaDbConnectionFactoryTest::testOptionsDisableMultiStatementExecution` |
| SEC-03 | Where a CA is configured the server certificate is verified, and that verification is not separately switchable. | `MariaDbConnectionFactoryTest::testAppliesTlsCertificateAuthority` |
| SEC-04 | Schema changes use a different database login from the runtime one. | `MariaDbConnectionFactoryTest::testUsesConfiguredMigrationCredentials`, [database-privileges.md](../deployment/database-privileges.md) |
| SEC-05 | Debug output cannot be enabled while the environment is production. | `KernelTest::testRejectsDebugInProduction` |
| SEC-06 | No identifier reaches SQL text without passing an allow-list. | `DatabaseTest::testCallProcedureRejectsInvalidProcedureName`, `…RejectsInvalidParameterName` |
| SEC-07 | The dependency set that is tested is the dependency set that ships. | `composer.lock` tracked, `composer validate --strict` in CI |
| SEC-08 | With separate migration credentials, the deploy log lives in a database the runtime account holds no privilege on. | `MariaDbConnectionFactoryTest::testRequiresAMigrationLogDatabaseWithSeparateMigrationCredentials`, `DbDeployIntegrationTest::testKeepsTheLogInTheConfiguredLogDatabase`, [database-privileges.md](../deployment/database-privileges.md) |

## Authentication — pending

| Id | Invariant | Waiting on |
|---|---|---|
| SEC-10 | Passwords are hashed with Argon2id using parameters pinned in this project, never with the language's defaults. | Auth |
| SEC-11 | A successful login re-hashes the password whenever the pinned parameters have changed since it was stored. | Auth |
| SEC-12 | Failed authentication attempts are counted per account and per source address, and refused past the configured threshold within the window. The count survives a process restart. | Auth |
| SEC-13 | The session identifier is replaced whenever the authenticated identity or its privilege level changes: login, completion of MFA, logout. | Sessions |
| SEC-14 | An unknown account and a wrong password are indistinguishable in both response and timing. | Auth |
| SEC-15 | MFA recovery codes are stored hashed and each is accepted at most once. | MFA |
| SEC-16 | A WebAuthn assertion whose sign counter went backwards is refused. | WebAuthn |

**SEC-10.** "Argon2id" alone is not a decision. PHP's defaults for memory, time and
threads change between versions, so a stored hash would silently reflect whichever
version created it. The parameters belong in this project's code, which is also what
makes SEC-11 meaningful.

**SEC-15.** Hashed, but not with Argon2id. Ten codes mean the check has to try all of
them, which would be ten deliberately expensive hashes per attempt. Argon2id buys
resistance against guessing weak, user-chosen secrets; a recovery code is 128 bits of
randomness, where a fast hash is the correct choice and the slow one is only a denial of
service against ourselves.

**SEC-16.** Two rules hide inside "handle the counter correctly". A counter of zero from
an authenticator that reports zero every time is not a regression and must be accepted —
several authenticators, including many platform ones, never implement the counter. A
counter that *decreases* is a signal that the credential may have been cloned, and that
assertion is refused rather than logged.

## HTTP — pending

| Id | Invariant | Waiting on |
|---|---|---|
| SEC-20 | Every state-changing request authenticated by a cookie carries a CSRF token that is verified. | HTTP |
| SEC-21 | Session cookies are set `Secure`, `HttpOnly` and `SameSite=Lax` or stricter. | HTTP |
| SEC-22 | The Content-Security-Policy contains neither `unsafe-inline` nor `unsafe-eval`. | HTTP |
| SEC-23 | Template output is escaped by default, and every `\|raw` carries a comment naming why that value is trusted. | Templating |
| SEC-24 | Uploaded files are stored outside the document root and delivered through the application. A stored path is never derived from a client-supplied name. | Media |

**SEC-20** is scoped to cookie authentication on purpose. A request authenticated by a
bearer token needs no CSRF token — but only as long as that endpoint refuses cookie
authentication, because an endpoint accepting both is exactly as vulnerable as one
accepting only cookies.

**SEC-22** has a consequence that costs nothing now and a rewrite later: no inline
scripts, no inline styles, no `onclick` attributes in any template. Every script gets a
nonce or a hash. Deciding this after the templates exist means going back through all of
them.

**SEC-24** is the nearest of these — Media is the next subsystem on the roadmap.

## Audit — pending

| Id | Invariant | Waiting on |
|---|---|---|
| SEC-30 | Every administrative action is recorded with actor, time, action and target. | Audit |
| SEC-31 | Audit rows are insert-only. No `UPDATE`, no `DELETE`. | Audit |

**SEC-31** is enforced by triggers rather than by convention, and it can be built before
anything writes to the table, because it is a property of the table and not of its
callers. The application account additionally receives no `UPDATE` or `DELETE` grant on
it, so the trigger is the second line rather than the only one.

## Plugins — pending

| Id | Invariant | Waiting on |
|---|---|---|
| SEC-40 | Plugin code is never loaded if the plugin directory, any file in it or any parent directory up to the project root is writable by the runtime process, or if it contains a symbolic link. | Plugin loader, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-41 | A plugin receives only the core services behind the capabilities it declared; asking for any other is refused. This limits the official API and is not a sandbox. | Plugin loader, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-42 | A plugin is loaded only while its directory matches the digest recorded at activation; only `plugin:upgrade` records a new one. | Plugin loader, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-43 | A plugin name matches `^[a-z][a-z0-9]{1,23}$`, equals its directory name and is not reserved, before it is used in a path, identifier or class name. | Plugin manifest, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-44 | A plugin is not enabled unless the running core and every plugin it depends on satisfy its declared constraints. | Plugin manager, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-45 | A plugin that is present but not enabled is never registered with an autoloader. | Plugin loader, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-46 | The activation registry is not writable by the runtime process and is only ever replaced atomically. | Plugin manager, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-47 | The deploy refuses a plugin file that creates, alters or drops an object outside the plugin's namespace, or contains `DEFINER`, `GRANT` or `REVOKE`. A guard against mistakes, not a security boundary. | Plugin deploy, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
| SEC-48 | Once a plugin is recorded as disabled, none of its functions, procedures, views, triggers or events exist in the database. | Plugin manager, [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md) |
