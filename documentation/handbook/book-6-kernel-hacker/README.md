# Book 6 — Kernel Hacker

This book is for readers who already understand the framework concepts and want to trace how SPP actually implements them.

## Chapters

1. How to Read the SPP Source
2. Runtime Bootstrap and Scheduler Tracing
3. Registry and Dependency Resolution Internals
4. MiddlewareKernel and Pipeline Internals
5. Event Discovery and Dispatch Internals
6. Module Discovery and Compiled Registry
7. Routing Discovery and Compilation
8. SPPDB Driver and Compiler Internals
9. LiveComponent Lifecycle Internals
10. SPPLive Engine Selection Internals
11. SPPUX Bootstrap and Asset Resolution Internals
12. Source-Driven Documentation and Upgrade Auditing

## Kernel Hacker rule

Start from the public API, locate the activation metadata, trace the orchestrator, inspect tests, and only then descend into implementation details.
