# 0001. Record architecture decisions

* Status: Accepted
* Date: 2026-11-16

## Context

The reasons behind this project's decisions live in three places, none of them meant to
keep them.

Most were written into commit messages on feature branches. Branches are squash-merged,
so `main` keeps one combined message per pull request, and the individual commits that
carried the reasoning leave `main`'s history with the branch. The combined message can
also end up as nothing but the branch name.

Some reasoning sits inside topic documents, next to the description of the current state
(for example why the module boundaries have one axis and not two). Those documents are
edited as the system changes, and every edit can lose the reasons behind it.

The rest exists only in review discussions.

## Decision

Decisions are recorded as architecture decision records in `docs/adr/`, one file per
decision, numbered `NNNN-title.md`. The format is Michael Nygard's (status, context,
decision, consequences) with an added section for the alternatives that were turned down.

The rules for when a record is written and how it changes are in the
[directory README](README.md).

## Alternatives considered

* **Commit messages only.** They are reviewed and versioned, but squash merging removes
  them from `main`'s history, and nobody reads history to learn why a convention exists.
* **Topic documents.** These describe the present and must stay editable; reasons kept in
  them get rewritten along with it.
* **MADR.** A richer template with decision drivers and pros and cons per option. For a
  project with one maintainer that structure gets filled with ceremony; the alternatives
  section takes over its useful part.
* **A wiki.** Lives outside the repository, so a decision is not reviewed together with
  the change it decides.

## Consequences

* A significant change brings one more file, written while the reasons are still known.
* Decisions already taken are recorded afterwards, together with the topic document they
  belong to, rather than in one large catch-up.
* Reasoning now embedded in topic documents moves into records when those documents are
  next revised, and the documents link to them.
