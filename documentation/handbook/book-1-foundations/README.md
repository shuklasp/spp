# Book 1 — Foundations

This book starts from the assumption that the reader may know PHP but has never used a framework.

## Learning contract

Every chapter follows:

**Problem → Plain PHP → General framework idea → SPP → Build → Test → Break → Diagnose → Source trace → Architectural choice**

## Chapters

1. [What Happens When You Open a Website?](01-what-happens-when-you-open-a-website.md)
2. [Why Frameworks Were Invented](02-why-frameworks-were-invented.md)
3. [Frameworks in Context](03-frameworks-in-context.md)
4. [MVC from First Principles](04-mvc-from-first-principles.md)
5. [HTTP Request and Response Lifecycle](05-http-request-response-lifecycle.md)
6. [Containers, Dependency Injection, `bind()`, and `singleton()`](06-containers-and-di.md)
7. [Services and Business Logic](07-services-and-business-logic.md)
8. [Routing Concepts](08-routing-concepts.md)
9. [`pages.yml`: the Page-Oriented Routing Paradigm](09-pages-yml-architecture.md)
10. [Attribute Routing](10-attribute-routing.md)
11. [Creating Routing Artifacts from the SPP CLI](11-cli-route-generation.md)
12. [API Routing](12-api-routing.md)
13. [Live and Reactive Routing](13-live-routing.md)
14. [Configuration Architecture](14-configuration-architecture.md)
15. [Application Contexts](15-application-contexts.md)

## Why the ordering changed

The older handbook introduced framework APIs too early. V3 deliberately teaches the underlying problem first, then the general framework concept, and only then the SPP implementation.

Routing is split into multiple chapters because SPP supports several application paradigms. The learner first understands routing as a concept, then studies page-oriented routing, attribute/code-oriented routing, CLI generation, API routing, and reactive routing.

## Source baseline

This V3 branch is maintained as a source-synchronized handbook. Claims about current SPP behavior must be verified against the SPP implementation and tests for the documented source baseline.
