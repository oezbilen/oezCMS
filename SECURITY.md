# Security Policy

## Supported versions

oezCMS has not been released yet. Until the first release, security fixes are made on
`main` only.

## Reporting a vulnerability

Please do not report security problems through public issues, pull requests or
discussions.

Report them privately through GitHub instead: open the repository's **Security** tab and
choose **Report a vulnerability**, or go directly to
<https://github.com/oezbilen/oezCMS/security/advisories/new>.

A useful report contains:

* the affected component, file or database object, and the commit you tested,
* the steps to reproduce it, or a proof of concept,
* the impact as you understand it.

## What happens next

The report becomes a private security advisory, where it is confirmed, discussed and
fixed. The fix is prepared privately, published, and the advisory made public together
with it. You are credited in the advisory unless you prefer not to be.

Please allow time for a fix to be published before disclosing the problem elsewhere; the
timing is agreed in the advisory.

## Scope

In scope is everything in this repository, including the database schema and its
routines.

Not in scope:

* vulnerabilities in third-party dependencies — please report those upstream; oezCMS
  updates once a fix is available,
* findings that require prior administrative access to the server or the database,
* output of automated scanners without a demonstrated impact.

The security properties the project commits to are listed, each with its test or its
status, in [docs/security/invariants.md](docs/security/invariants.md).
