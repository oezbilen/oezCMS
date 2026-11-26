# 0017. Accept media types by content from an allow-list

* Status: Proposed
* Date: 2026-11-26

## Context

An upload carries a file name and a declared type, and the client chooses both. An
extension says nothing about the bytes behind it, and a declared `Content-Type` is the
same claim in a header.

Some formats are active content. An SVG may contain script, and opened from the
application's origin it runs with that origin's rights. A limit on size depends on the
format: a sensible ceiling for a photograph is far too low for a PDF.

The schema already holds the domain's rules
([0008](0008-keep-domain-rules-in-the-database.md)). Recognising a format needs the bytes,
which only PHP has.

## Decision

The accepted types are rows of `media_types`, seeded by a migration. Each row names a MIME
type as an identifier, a maximum size in bytes and whether it is active. The first set is
`image/jpeg`, `image/png`, `image/webp`, `image/gif`, `image/avif` and `application/pdf`.

The type of an import is detected from its content with `finfo`. The file name and any
declared type are not consulted. The work is split between PHP and the database:

* PHP detects the type and measures the size.
* The database decides. A `media_files` row references its type by foreign key, and a
  `BEFORE INSERT` trigger refuses a type that is inactive and a size above its limit.

A type unknown to `media_types` cannot be referenced, so it is refused as well. Deactivating
a type stops new imports and leaves stored files untouched.

SVG is not on the list. Accepting it needs a decision of its own: a sanitiser, delivery
that never renders it inline, or both.

## Alternatives considered

* **An allow-list of extensions.** It checks what the client wrote, not what it sent.
* **The declared `Content-Type`.** The same claim, made in a header instead.
* **The list in PHP or in the configuration.** A row cannot reference a constant, so the
  rule would leave the database, and the limits would be a second configuration to keep in
  step with the schema.
* **Re-encoding every image.** It strips metadata and breaks files that are two formats at
  once, but it changes the bytes, needs an image library and belongs with image variants,
  which phase 2 leaves out.
* **SVG with a sanitiser.** Sanitising SVG is a project of its own, and nothing needs it.

## Consequences

* `ext-fileinfo` joins `require` in the change that first calls `finfo`.
* What `finfo` recognises depends on the libmagic PHP was built against, AVIF especially.
  A test imports a sample of every seeded type, so a build that cannot detect one fails
  instead of refusing uploads in production.
* A new type is a migration, and its size limit is reviewed with it.
* Detection names a format. It does not make a file safe. A file that is valid JPEG and
  also something else passes. Phase 6 has to deliver every file with its stored type,
  `X-Content-Type-Options: nosniff` and a `Content-Disposition`, so that the browser does
  not reinterpret it.
