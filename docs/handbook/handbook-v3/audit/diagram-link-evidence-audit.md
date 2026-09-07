# Diagram, Link, and Evidence Audit

## Diagram audit

For every Mermaid diagram:

1. It must render as Mermaid, not as a prose representation.
2. Every node must correspond to a real concept discussed in the chapter.
3. The flow must reflect the documented responsibility boundaries.
4. A diagram should be removed when it repeats prose without adding architectural clarity.

## Link audit

Each V3 README must link only to files that exist on `handbook-v3`.

When a chapter is renamed:

- update the book README;
- update the top-level V3 README;
- update cross-links in the affected chapters;
- search for the old filename before committing.

## Evidence audit

Classify claims as:

- **Source verified** — implementation directly establishes the behavior.
- **Test verified** — tests/fixtures demonstrate the behavior.
- **Configuration verified** — manifests/configuration consumed by the implementation establish it.
- **Reference** — repository documentation describes it but implementation has not been independently traced.
- **Architectural interpretation** — a reasoned interpretation of how components fit together.
- **Planned/unverified** — not established as current behavior.

Do not convert interpretation into a guarantee.

## High-risk claims

These require explicit evidence before publication:

- performance superiority;
- stronger security than another framework;
- atomic or rollback-safe transfer;
- exactly-once processing;
- distributed consensus;
- transparent distributed objects;
- automatic AI recovery;
- protocol or transport guarantees;
- concurrency semantics.

## Audit outcome

The V3 handbook should be maintained as a source-first document. When code changes, the affected chapters and their evidence status must be reconsidered rather than assuming the prose remains valid.