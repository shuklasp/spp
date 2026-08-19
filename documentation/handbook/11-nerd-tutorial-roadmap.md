# 11. Total-Nerd Application Tutorial Roadmap

> This roadmap defines the implementation sequence for the canonical hands-on tutorial. Concrete APIs will be filled only after the corresponding source classes, examples, and tests have been traced.

## 11.1 One domain, four implementation levels

The tutorial builds one enterprise domain repeatedly so readers can compare the architectural cost of each approach.

1. Plain PHP application
2. SPP application using the framework runtime
3. Reactive application using LiveComponent
4. Reactive UI using SPPUX

The domain will use realistic enterprise concerns: authentication, authorization, validation, persistence, auditing, events, background work, reporting, and integration with an external service.

## 11.2 Level 1 — Plain PHP

The reader implements the application without relying on SPP abstractions. The purpose is to establish the problems that a framework must solve:

- dependency creation
- configuration
- request lifecycle
- routing
- rendering
- validation
- persistence
- events
- testing

## 11.3 Level 2 — SPP runtime

The same application is migrated into SPP using the verified application, scheduler, registry, module, event, and rendering abstractions.

The tutorial will explain every generated file rather than treating generators as magic.

## 11.4 Level 3 — LiveComponent

The server-rendered application is upgraded incrementally:

- identify interactive regions
- introduce a LiveComponent
- bind state
- handle actions
- integrate validation
- connect framework events
- preserve module boundaries
- introduce the appropriate SPP Live transport

## 11.5 Level 4 — SPPUX

The UI is progressively moved to the SPPUX runtime where appropriate. The tutorial will distinguish server-side application state from client-side UI state and will document the actual SPPUX runtime and reconciliation APIs.

## 11.6 Enterprise-grade constraints

The final tutorial will include:

- multiple application boundaries
- module ownership
- environment-specific configuration
- least-privilege service access
- validation at trust boundaries
- structured logging and correlation IDs where implemented
- test seams and deterministic fixtures
- failure handling
- deployment topology
- external service integration

## 11.7 Framework migration tracks

Separate sidebars will map the same implementation to readers arriving from:

- Laravel / Livewire
- Symfony / Twig
- Django
- Spring Boot
- ASP.NET Core
- React / Vue
- Flutter

These are conceptual mappings, not claims that SPP implements the other frameworks' APIs.

## 11.8 Source-evidence rule

Every tutorial step that names an SPP class, command, attribute, configuration key, manifest field, transport, or lifecycle method will be checked against the repository source before being committed.
