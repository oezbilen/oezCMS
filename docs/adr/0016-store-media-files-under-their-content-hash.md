# 0016. Store media files under their content hash

* Status: Proposed
* Date: 2026-11-26

## Context

Media is the first part of the system that writes files. An upload arrives with a name and
a type chosen by the client, and both are client data. A path built from that name has to
survive `..`, separators, Unicode normalisation, reserved device names and filesystems
that ignore case, and every one of those is a known way for sanitising to go wrong.

SEC-24 already requires that uploads live outside the document root and that a stored path
is never derived from a client-supplied name. Delivery comes with the HTTP layer of
phase 6, so until then the files are only data, and nothing outside the application
reads them by path.

The same image is often imported more than once, and a file on disk can change or vanish
without the database noticing.

## Decision

A stored file is named after the SHA-256 of its content, written as 64 lower-case hex
digits, in two levels of directories taken from its first four digits:

```
<MEDIA_STORAGE_PATH>/ab/cd/abcd…(64 hex digits)
```

The name has no extension. The module computes the hash from the bytes it stores and never
accepts one from outside. `media_files.sha256` holds it as an ASCII binary column
([0003](0003-store-identifiers-as-ascii-binary-columns.md)) with a check on
`^[0-9a-f]{64}$` and a unique key, so the database cannot hold a name of any other shape.

Writing follows three rules:

* The bytes go to a temporary file below the storage root and are renamed into place, so a
  path either holds a complete file or nothing. Root and target are on the same
  filesystem, so the rename is atomic.
* A path that exists is never written again. Its name is its content, so a second import
  of the same bytes reuses it.
* The file is written before its row. If the row fails, an orphaned file is left, which a
  check can find and remove. The other order would leave a row pointing at nothing.

`MEDIA_STORAGE_PATH` is required for media, must be absolute and is refused if it resolves
to `public/` or below it, after symbolic links are resolved.

## Alternatives considered

* **A sanitised form of the client's name.** It is exactly the transformation SEC-24 rules
  out, and the list of what sanitising has to handle keeps growing.
* **A random key such as a UUID.** As safe for SEC-24, but two imports of the same bytes
  become two files, and checking a file means storing a checksum beside the key, which is
  the hash again with a second name.
* **The bytes in the database as a `BLOB`.** Backups, replication and the buffer pool would
  carry every file, and delivery goes through PHP in either case. The database holds the
  rules about files, not their bytes ([0008](0008-keep-domain-rules-in-the-database.md)).
* **The hash with an extension.** It would give a misconfigured web server a reason to
  interpret the file. The type is a fact recorded in the database
  ([0017](0017-accept-media-types-by-content-from-an-allow-list.md)), not part of the name.
* **A flat directory.** It holds every file in one listing. Two levels give 65 536
  directories, enough that none grows large.

## Consequences

* Identical bytes are stored once. Several media entries can share one file, so removing
  an entry never removes bytes directly. Physical removal is a separate step that only
  runs once no row refers to the file, and it is not part of phase 2.
* Integrity can be checked by hashing a file again and comparing the result with its name.
  A `media:verify` command reports files that are missing, changed or not referenced.
* Stored bytes cannot be edited in place. A changed image is a new file under a new hash.
* The original file name survives only as display text on the media entry.
* Anyone who holds the same bytes can compute a file's name, so names stay internal like
  any other path. What leaves the system is the entry's `public_id`.
