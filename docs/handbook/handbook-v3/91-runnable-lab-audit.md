# 91 — Runnable Lab Audit

A chapter is not fully practical merely because it contains code. A runnable lab must be reproducible from the current repository.

## Lab contract

Each major subsystem should provide:

1. **Goal** — the capability being learned.
2. **Prerequisites** — the exact preceding chapters.
3. **Starting state** — files or scaffold command used.
4. **Build steps** — minimal, ordered changes.
5. **Expected result** — observable behavior.
6. **Test** — Parikshak or the framework-supported test path.
7. **Break** — one controlled failure.
8. **Diagnose** — where to look and why.
9. **Source trace** — public API → dispatcher/runtime → implementation → test.
10. **Reset** — how to return to the clean state.

## Priority labs

The highest-value first-pass labs are:

- Plain PHP → SPP application.
- Container `bind()` and `singleton()` behavior.
- `pages.yml` routing.
- Attribute routing.
- CLI-generated routing/page artifacts.
- Middleware pipeline.
- Event publication/listener execution.
- Module discovery and activation.
- SPPDB query/connection/compiler path.
- SPPReport schema/report flow.
- LiveComponent hydration/dehydration lifecycle.
- SPPLive engine selection.
- SPPUX runtime/bootstrap and mounting.
- Authentication/authorization boundary.
- Parikshak feature test.
- Queue/Cron background execution.
- Transfer/promotion workflow.
- Multi-application context selection.

## Beginner rule

A lab should introduce one new architectural idea at a time. Advanced combinations belong in the Enterprise Capstone.

## Verification rule

Do not write a lab command from memory. Verify it against the current CLI implementation or repository command documentation first.
