# Volume XVII — Migration and Comparison

## Chapter 23 — Coming to SPP from Other Frameworks

This chapter is for developers who already know another framework. It is deliberately conceptual: the goal is to map ideas, not pretend that SPP implements another framework's APIs.

---

## 23.1 The most important rule

Do not translate every SPP concept into the vocabulary of your previous framework.

A better method is:

```text
Your existing concept
        ↓
What problem was it solving?
        ↓
Which SPP subsystem solves that problem?
```

This prevents false equivalence.

---

## 23.2 If you come from Laravel

Laravel developers will already recognize several ideas:

| Laravel concept | SPP concept to investigate |
|---|---|
| Application/bootstrap | `sppinit.php` + `App` + Scheduler |
| Service container | SPP application/Registry containers |
| Middleware | `MiddlewareKernel` + `Pipeline` |
| Events/listeners | `SPPEvent` + `EventHandler` |
| Packages/modules | SPP modules and manifests |
| Blade | Drishyam/extended BladeOne through SPPView |
| Livewire | SPP `LiveComponent` + SPP Live |
| Alpine/client reactivity | SPPUX where client-side state is appropriate |

The biggest conceptual difference is that SPP explicitly models **multiple application contexts in one runtime** through the Scheduler.

---

## 23.3 If you come from Symfony

Symfony developers will find the following familiar:

- dependency injection;
- middleware/request processing concepts;
- event dispatch;
- templating;
- modular bundles/packages;
- configuration-driven runtime behavior.

The important SPP-specific concepts to learn early are:

1. the Scheduler/application context model;
2. the Registry's dual role as hierarchical runtime data and container access;
3. the SPP module compiler/compiled registry; and
4. the separation between LiveComponent, SPP Live, and SPPUX.

Do not assume Symfony's event, routing, or container lifecycle is identical to SPP's.

---

## 23.4 If you come from Django

A useful conceptual map is:

```text
Django application/project concepts
        ↓
SPP App + application context + modules

Django middleware
        ↓
SPP MiddlewareKernel/Pipeline

Django URL dispatch
        ↓
SPP route/page/API dispatch

Django template layer
        ↓
SPPView/Drishyam
```

The mental model that needs the largest adjustment is the idea that SPP can host multiple named application contexts in one runtime.

---

## 23.5 If you come from Spring Boot

Spring developers will recognize:

- dependency injection;
- application configuration;
- event-driven extension;
- modular application structure;
- service/application-layer separation.

Map the architecture by responsibility rather than by class name.

For example:

```text
Spring dependency injection
        ↓
SPP Container

Spring application context
        ↓
SPP App + Scheduler context

Spring events
        ↓
SPPEvent
```

The lifecycles are not API-compatible; the map is conceptual.

---

## 23.6 If you come from ASP.NET Core

The request-pipeline and dependency-injection concepts are useful starting points.

A conceptual mapping is:

| ASP.NET Core idea | SPP area |
|---|---|
| Middleware pipeline | MiddlewareKernel/Pipeline |
| Dependency injection | SPP Container |
| Configuration | SPP application/framework configuration |
| Controllers/endpoints | Route/page/request handlers |
| Razor-like rendering | SPPView/Drishyam |
| External process integration | Polyglot/integration subsystem |

Again, use the mapping to orient yourself, not to infer unverified behavior.

---

## 23.7 If you come from React

React developers need one especially important distinction:

**SPPUX is the browser runtime. LiveComponent is server-side PHP.**

A React-style mental model may tempt you to put all state into the browser.

In an SPP application, keep authoritative business decisions on the server.

Use SPPUX for browser-local reactive state where that is appropriate.

Use LiveComponent when the interaction needs server-side component state/business behavior.

---

## 23.8 If you come from Vue

The same server/client distinction applies.

Vue-style client reactivity maps conceptually to SPPUX reactive state, templates, scheduling, event delegation, and DOM reconciliation.

But SPPUX is its own runtime. Do not assume Vue component lifecycle or reactivity semantics apply automatically.

---

## 23.9 If you come from Flutter

Flutter developers are accustomed to a component/widget-oriented UI runtime.

SPP can offer a different split:

```text
Server application
    SPP App + services + SPPView

Server-reactive UI
    LiveComponent

Browser-local reactive UI
    SPPUX
```

The important architectural decision is where a state actually belongs: server authority versus client-local interaction.

---

## 23.10 Migration strategy

Do not rewrite a mature application into “SPP style” all at once.

A safer migration usually follows:

1. identify the existing application boundary;
2. introduce SPP bootstrap/application context;
3. move reusable infrastructure to services/container resolution;
4. move cross-cutting request logic into middleware;
5. isolate cohesive features as modules where justified;
6. move presentation into SPPView where beneficial;
7. introduce LiveComponent only for genuinely interactive regions;
8. add SPPUX only where browser-local reactivity provides a real benefit; and
9. introduce external/polyglot boundaries only when they solve a real integration or operational problem.

---

## 23.11 The migration mistake to avoid

The worst migration strategy is to rename classes and folders until they look like SPP.

Architecture is not vocabulary.

The meaningful migration is to move responsibilities into the SPP boundary that actually owns them.

---

## Kernel Hacker note

The best cross-framework migration tool is a **problem-to-boundary map**.

Ask:

> “What recurring engineering problem was the old framework solving here?”

Then identify the SPP subsystem that owns that responsibility today.

That method scales across PHP frameworks, Python frameworks, JVM frameworks, .NET, and client runtimes without pretending that their APIs are equivalent.

### Source map

- Handbook Chapters 1–22
- framework/application source paths cited by the corresponding subsystem chapters
