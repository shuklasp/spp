# 93 — Canonical Handbook Fileset

This file defines the canonical publishing rule for `documentation/handbook`.

## Canonical principle

The handbook must contain **only the current documentation set**. Historical drafts, superseded tutorials, duplicate chapter variants, generated research snapshots, and obsolete numbering should not remain in the published handbook directory.

## Keep

Keep files that are part of the current learning/reference structure, including:

- `00-handbook-status.md`
- `README.md`
- `01-getting-started.md`
- the current numbered foundation chapters;
- the current deep-reference chapters;
- the current architecture-audit chapters;
- the current migration/porting guides;
- the current framework- and application-level developer guides;
- the current testing/Parikshak material;
- supporting curriculum and quality-control chapters that are linked by the canonical README.

## Remove

Remove a file from the published handbook directory when it is:

- an earlier version replaced by a newer chapter;
- a duplicate covering the same material without a distinct learning purpose;
- a stale tutorial branch superseded by a current deep-reference chapter;
- an obsolete numbering alias;
- a research scratchpad that is not intended for readers;
- generated output that belongs in another documentation surface;
- a temporary audit artifact no longer needed by the canonical handbook.

## Decision rule for similarly named chapters

When two chapters overlap, keep the one that is:

1. source-verified against the current SPP implementation;
2. linked by `README.md`;
3. aligned with the current learning roadmap;
4. free of stale framework assumptions;
5. useful as either a tutorial, reference, architecture audit, or migration guide with a clearly different purpose.

Do not retain both merely because they have different numbers.

## Publishing invariant

The canonical handbook directory should satisfy:

```text
README links → existing file

existing published file → current purpose

superseded file → removed

duplicate file → removed or explicitly classified
```

The purpose of this file is governance; it is not itself a replacement for the README navigation.
