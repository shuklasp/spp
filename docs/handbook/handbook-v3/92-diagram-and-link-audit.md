# 92 — Diagram and Link Audit

## Diagram audit

For every Mermaid diagram:

- verify Mermaid syntax;
- verify the labels correspond to current SPP terminology;
- verify the flow is derived from source or clearly marked as a conceptual model;
- remove decorative diagrams;
- replace accidental text diagrams when a real architecture diagram is intended.

## Link audit

For every Markdown link:

- verify the target exists on `handbook-v3`;
- verify directory depth and filename case;
- verify the target is the canonical teaching chapter when one exists;
- label older documents as reference rather than primary learning material.

## Consistency audit

Check that the same concept uses the same term across books. Examples:

- application context;
- middleware pipeline;
- event handler;
- module;
- entity;
- hydration/dehydration;
- transport engine;
- browser runtime;
- transfer/promotion.

## Evidence audit

Every strong assertion should be traceable to one of:

- executable source;
- tests/fixtures;
- consumed configuration/manifests;
- current repository documentation.

Architectural interpretation must be labeled as interpretation.
