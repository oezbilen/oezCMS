# 0011. Parse .env with a strict parser of our own

* Status: Accepted
* Date: 2026-07-27 (recorded 2026-11-16)

## Context

`.env` carries the database credentials. The first parser accepted almost anything and
produced wrong values without a word: `PASSWORD="abc\"def"` became `abc\`, `KEY="abc`
became `"abc`, `export KEY=value` defined a key literally named `export KEY`, and a
duplicate key silently kept its first value. A wrong password read this way surfaces
later as a failed login to the database, far from its cause.

The established libraries (vlucas/phpdotenv, symfony/dotenv) parse correctly, but both
expand variables inside values (`$VAR`, `${VAR}`).

## Decision

oezCMS parses `.env` itself, against a small documented format: plain, single-quoted
(literal) or double-quoted values with four escape sequences, comments, and nothing else.
There is no interpolation. Duplicate keys and anything the format does not define are
errors that name the file, the line and the reason. The process environment takes
precedence over the file.

The format is described in [environment.md](../development/environment.md#the-env-file).

## Alternatives considered

* **phpdotenv or symfony/dotenv.** Interpolation means a `$` in a password is expanded
  unless it is escaped. A generated password that happens to contain one breaks in a way
  that is hard to see.
* **Fixing the lenient parser case by case.** Every leniency is a place where a typo
  becomes a different value instead of an error.

## Consequences

* A `.env` written for another tool may be rejected; the error says where and why.
* There is no `export`, no interpolation and no multi-line value apart from `\n`.
* The parser is maintained here. Its tests pin every rule of the format.
