# Module Boundaries

## The layers

```
src/
  Core/              framework: bootstrapping, container, configuration, database access
  Console/           CLI delivery
  Http/              HTTP delivery (does not exist yet)
  Modules/
    I18n/
    Media/
    Users/
```

| Layer | May use |
|---|---|
| `Core` | nothing of ours above it |
| `Modules\X` | `Core`, its own namespace, `Modules\Y\Api` |
| `Console`, `Http` | `Core`, `Modules\*\Api` — and not each other |
| `Modules\X\Api` | `Core`, other modules' `Api` — and not `Modules\X`'s own internals |

`Core` knowing nothing above it is what keeps it a framework rather than a shelf. Feature
code placed there could not be reached from anywhere, so it stops being an option instead
of merely being discouraged.

A module not knowing `Console` or `Http` is the same idea pointed the other way: a feature
that cannot name a delivery mechanism cannot quietly become one delivery mechanism's
feature.

## Why one axis and not two

A layered tree (`Domain`, `Application`, `Infrastructure`) alongside feature modules was
considered and rejected. Both are reasonable orderings, but together they give every class
two plausible homes: does i18n logic belong under `Domain/I18n` or `Modules/I18n/Domain`?
That ambiguity is not a side effect of dumping grounds, it is how they form — when two
answers are defensible, code lands wherever the author was looking.

The second reason is specific to this project. oezCMS is database-first, and much of what
a `Domain` layer would hold lives in MariaDB: `fn_i18n_translate` *is* the fallback rule,
`v_i18n_locale_chains` *is* the chain model, the write guards are triggers. There is still
no PHP class for i18n at all. A layer cake over a schema that already holds its own
invariants produces mostly empty folders — and an empty folder with an authoritative name
attracts whatever needs a home.

## What a module publishes

`Modules\X\Api` holds the interfaces, value objects and exceptions other code may use.
Everything else is internal and may be rewritten without asking anyone.

The surface may not depend on the module's internals. If it did, the internals would be
published along with it, whatever the directory layout claims — and that is also the
mechanical form of "no concrete infrastructure in `Api`": an interface, a value object or
an exception has no need of them. The reverse direction stays open, since an internal
class implementing an interface from `Api` is exactly what `Api` is for.

`Api` is kept flat. Subdividing it into `Contract/`, `Dto/` and `Event/` is the layer cake
again, one level down. If `Api` becomes hard to read, that is a signal the module publishes
too much, not that it needs folders.

## How the rules are enforced

`tests/Architecture/LayerBoundaryTest.php`, in the normal test run. It reads the namespace
and imports of every file under `src/` and reports anything crossing a boundary, including
a file in a namespace no layer claims.

A class from this project is always imported, never written out fully qualified in a file
body. That is not a matter of taste: the check reads imports, so an inline reference would
pass through it unseen. Inside a namespace only a leading backslash reaches another
namespace — the same name without it resolves relative to the current one and does not
exist — so refusing `\OezCMS\…` in a body makes the import analysis complete rather than
approximate. Both rules are checked by the same test.

Group imports, several imports in one statement, unterminated imports and bracketed
namespaces are refused outright rather than parsed approximately. The style gate already
prevents the first three; refusing them here keeps the analysis correct on its own, since
reading half a statement would hide whatever the other half depends on.

**Known limit:** a class name assembled as a string is invisible to this — and to
`nikic/php-parser` as well, since there is no name to inspect. That is a limit of static
analysis, not of the technique chosen here.

**When this is replaced:** by `deptrac` or `phpat`, once the rule set outgrows a single
file. Both parse properly and both cost a dependency, a configuration format and, for
deptrac, a second CI step. At four layers that trade is not worth making, which is a
judgement about size and will stop being true.

## Deliberately open

**The inside of a module.** No rule constrains how a module arranges itself yet. A
four-layer scheme would presume a rich PHP domain, and for i18n the domain is SQL. The
pattern should be read off two or three real modules rather than decreed now; Media is the
first that will have PHP worth arranging, because files live in a filesystem.

**Cycles between modules.** The rules permit `Media → I18n\Api` and `I18n → Media\Api` at
the same time, which is a cycle a modular monolith should not have. It is not checked yet
because with one module it could not occur, and building the check would force a decision
about sharing the scanner that nothing can answer today. The trigger is concrete: the
second module.

## Plugins

A plugin is a module that ships separately, and the same rules apply: `Core` and the
published surface of other modules, nothing deeper. Discovery, registration and permissions
are a separate decision.
