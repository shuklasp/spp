# 90 — Repository-Wide V3 Audit Checklist

Use this checklist after framework source changes and before calling the handbook release-ready.

## 1. Source synchronization

- [ ] Record the exact SPP source baseline commit/date.
- [ ] Identify changed files for Scheduler, Registry, MiddlewareKernel, Events, Modules, Routing, SPPDB, SPPReport, LiveComponent, SPPLive, SPPUX, CLI, Parikshak, Transfer, AI, and application-context code.
- [ ] Map every changed public behavior to at least one handbook chapter.
- [ ] Update source maps when class, method, file, or module locations move.
- [ ] Remove claims whose implementation evidence disappeared.

## 2. Beginner correctness

- [ ] Define every new framework term before using it.
- [ ] Explain the general framework concept before the SPP API.
- [ ] Show the plain-PHP problem that motivated the feature.
- [ ] Explain what SPP does for the developer.
- [ ] Avoid assuming MVC knowledge unless the chapter is explicitly advanced.

## 3. Examples

- [ ] Every command is present in the current CLI implementation or current command documentation.
- [ ] Every PHP example uses current public APIs.
- [ ] Every configuration example matches current manifests/config loaders.
- [ ] Examples do not silently rely on features described as planned or unverified.
- [ ] Failure examples describe the actual failure boundary.

## 4. Diagrams

- [ ] Mermaid syntax is valid.
- [ ] Diagram labels match current concepts.
- [ ] No architecture diagram is represented as plain text when Mermaid is appropriate.
- [ ] No diagram claims a runtime step that is only an architectural interpretation.
- [ ] Duplicate diagrams are removed.

## 5. Testing

- [ ] Major feature chapters contain a Parikshak test path where supported.
- [ ] Tests demonstrate both normal and failure behavior.
- [ ] The test chapter explains what is being verified.
- [ ] Testing claims are distinguished from performance claims.

## 6. Architecture

- [ ] Explain when a feature should be used.
- [ ] Explain when a simpler approach is preferable.
- [ ] Distinguish module, application-context, process, and external-system boundaries.
- [ ] Distinguish server state, transport, and browser state in reactive chapters.
- [ ] Do not infer distributed guarantees from local abstractions.

## 7. Links and navigation

- [ ] Every chapter is reachable from a book README.
- [ ] Every book is reachable from the V3 README.
- [ ] Relative links use the correct directory depth.
- [ ] No chapter points to a superseded canonical chapter without explicitly labeling it as reference material.
- [ ] Migration and Kernel Hacker tracks are discoverable.

## 8. Release baseline

- [ ] Update V3 README source baseline.
- [ ] Update completion status honestly.
- [ ] Record known gaps explicitly.
- [ ] Re-run the audit after any major source synchronization.
