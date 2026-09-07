# 64 — Handbook Documentation Quality Gate

## Purpose

A source-first handbook needs a release gate. A chapter is not finished merely because its Markdown file exists or its prose reads well.

This chapter defines the checks required before a handbook change is considered complete.

## Definition of done for a feature chapter

A substantial subsystem chapter should cover, where applicable:

1. concept and terminology;
2. general framework model;
3. SPP mapping;
4. practical build exercise;
5. current CLI/scaffold path when actually exposed;
6. Parikshak test exercise, or an explicit reason testing is not applicable;
7. deliberate failure lab;
8. source map;
9. architecture and boundaries;
10. comparison with alternatives;
11. trade-offs;
12. security/performance considerations supported by evidence;
13. cross-links to related chapters;
14. evidence status;
15. current-source verification date for source-sensitive material.

Not every chapter needs every subsection verbatim, but omissions must be intentional.

## Gate 1 — Source accuracy

Before merge:

- verify important class and method names against the current branch;
- verify file paths;
- verify configuration keys are actually consumed;
- verify CLI commands have current handlers before teaching them;
- distinguish generated documentation from executable source;
- inspect call sites when lifecycle behavior matters;
- resolve source/documentation contradictions explicitly.

### Fast source audit

For each central claim, record:

```text
Claim → source → caller → configuration → test → limitation
```

If the chain stops early, downgrade the claim rather than filling the gap with assumption.

## Gate 2 — API and example drift

Every code example must be checked for:

- current namespace/class name;
- current method signature;
- required imports;
- current configuration shape;
- current response/request behavior;
- current CLI syntax;
- current directory layout.

Avoid invented commands or pseudocode that looks like a real command. Label conceptual examples as conceptual.

## Gate 3 — Testing and failure lab

Where a subsystem has meaningful executable behavior:

1. provide a smallest useful test;
2. use Parikshak when it is the appropriate SPP testing path;
3. deliberately break one dependency or boundary;
4. show how to diagnose the failure;
5. identify the source responsible for the behavior.

A chapter that says only “this works” does not teach the reader how to reason when it does not work.

## Gate 4 — Diagram quality

Use the handbook diagram policy:

- Mermaid for architecture, lifecycle, sequence, decision, and data-flow diagrams;
- code blocks for code, configuration, literal layouts, CLI, and actual output;
- tables for simple comparisons;
- prose/lists for procedures and explanation.

Every Mermaid diagram must:

- render on GitHub;
- use syntax supported by GitHub's Mermaid renderer;
- contain meaningful labels;
- avoid unreadably small text or excessive node counts;
- represent actual architecture rather than decorative arrows.

If a diagram cannot be made source-accurate, replace it with a simpler diagram or prose.

## Gate 5 — Security and trust boundaries

Security-sensitive chapters require an explicit review of:

- authentication;
- authorization;
- session state;
- API exposure;
- data access;
- external trust boundaries;
- secrets/configuration;
- failure and fallback paths.

Do not convert the existence of a security module, attribute, token parser, or policy class into a blanket security guarantee.

When a development fallback exists, document it as a fallback rather than presenting it as a production control.

## Gate 6 — Strong claims

Search the chapter for claims containing ideas such as:

```text
secure
zero-trust
atomic
exactly-once
ACID
durable
fault-tolerant
scalable
high-performance
cluster
consensus
federated
self-healing
production-ready
```

Each such claim needs concrete evidence appropriate to its scope. If evidence is insufficient, rewrite the statement as a narrower implementation fact or mark it Planned/Unverified.

## Gate 7 — Navigation integrity

A new chapter is incomplete if readers cannot discover it from the canonical handbook navigation.

Check:

- `documentation/handbook/README.md`;
- relevant chapter cross-links;
- Chapter 60 completion plan;
- curriculum/roadmap references;
- no stale “Coming Soon” statements where the chapter now exists;
- no links to missing chapters unless they are intentionally future work.

The README is part of the documentation surface, not an optional index.

## Gate 8 — Architecture coherence

Before merging, ask:

- Does the new chapter contradict the SPP mental model?
- Does it duplicate another chapter without defining the relationship?
- Does it accidentally turn an implementation detail into a platform-wide rule?
- Does it confuse page, API, LiveComponent, SPPUX, service, event, middleware, queue, and IPC boundaries?
- Does it distinguish SPPDB, SPPDBPool, and SPPXDB where persistence is discussed?
- Does it preserve the distinction between source behavior and architectural guidance?

Use Chapter 66 when several SPP mechanisms can solve the same problem.

## Gate 9 — Freshness

A source-sensitive chapter should identify what was checked against the current source branch when the distinction matters.

Freshness review is especially important after:

- large sanitization/refactoring changes;
- module moves;
- generated-document regeneration;
- API changes;
- security changes;
- transport changes;
- persistence changes.

Do not treat a recent file modification timestamp as proof that the underlying behavior is new.

## Gate 10 — Reader usability

A beginner should be able to answer:

- What problem does this solve?
- Why does the mechanism exist?
- What is the smallest example?
- What should I build next?

An advanced reader should be able to answer:

- Where is the implementation?
- What calls it?
- What configuration changes it?
- What are the boundaries?
- What happens on failure?
- What is not guaranteed?

If either audience cannot answer these questions, the chapter needs another pass.

## Release checklist

Use this before declaring a handbook milestone complete:

```text
[ ] Source paths verified
[ ] API signatures verified
[ ] Configuration verified
[ ] CLI/scaffold claims verified
[ ] Evidence status assigned
[ ] Strong claims audited
[ ] Security/trust boundaries reviewed
[ ] Practical build included
[ ] Parikshak test included or N/A explained
[ ] Failure lab included or N/A explained
[ ] Source map included
[ ] Diagrams render on GitHub
[ ] Links checked
[ ] README navigation updated
[ ] Cross-links checked
[ ] No stale Coming Soon claims
[ ] No unsupported enterprise guarantees
[ ] Current-source freshness recorded where needed
```

## Documentation change workflow

```text
Research
  ↓
Source trace
  ↓
Draft chapter
  ↓
Build example
  ↓
Parikshak test
  ↓
Deliberate failure
  ↓
Source re-check
  ↓
Link/diagram/API audit
  ↓
README navigation
  ↓
Merge
```

The final source re-check is intentional. Writing can expose misunderstandings that are invisible during the initial research pass.

## Relationship to the rest of the handbook

- Chapter 60 defines the overall completion plan.
- Chapter 61 defines the learning order.
- Chapter 62 defines the continuous practical curriculum.
- Chapter 63 defines evidence classification.
- This chapter defines the release gate that applies to all of them.

Together these chapters turn the handbook from a collection of pages into a maintained learning and implementation reference.
