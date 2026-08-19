# Volume VII — SPPUX

## Chapter 9 — SPPUX Reactive Runtime

**Evidence:** `spp/modules/spp/drishyam/js/sppux.js`, `spp/modules/spp/drishyam/js/core/`, `spp/modules/spp/drishyam/js/sppux-bridge.js`, `spp/modules/spp/drishyam/js/sppux-grid.js`, `spp/modules/spp/drishyam/js/sppux-ui.js`, `spp/res/js/sppux.js`, `spp/res/js/sppux.standalone.js`, SPPUX tests and type definitions.

SPPUX is a client-side runtime included in SPP. The current source describes it as a modular runtime composed of reactive state, scheduling, template rendering, event handling, DOM reconciliation, and error-boundary modules, with additional UI modules and a bridge to the SPP server/live layer.

## 9.1 Runtime composition

The primary `sppux.js` facade imports these core modules:

- `core/reactive.js` — `Signal`, `Computed`, `effect`, `batch`, `createStore`, `SPPStore`;
- `core/scheduler.js` — asynchronous update queue and batch controls;
- `core/template.js` — `TrustedHTML`, `html`, `Fragment`, handler consumption;
- `core/events.js` — event-handler registration and delegation;
- `core/reconciler.js` — DOM reconciliation and attribute patching;
- `core/error-boundary.js` — error-boundary support.

```text
                    SPPUX Facade
                         │
       ┌─────────┬───────┼────────┬──────────┐
       ▼         ▼       ▼        ▼          ▼
   Reactive   Scheduler Template Events  Reconciler
       │         │       │        │          │
       └─────────┴───────┴────────┴──────────┘
                         │
                         ▼
                  Component runtime
                         │
                         ▼
                 DOM / browser UI
```

## 9.2 Reactive primitives

The runtime exports `Signal` and `Computed` plus `effect`, `batch`, `createStore`, and `SPPStore`. These primitives provide client-side reactive state independent of the PHP LiveComponent state model.

The handbook must keep these two reactive systems conceptually separate:

- **LiveComponent state** — server-side PHP component state and hydration.
- **SPPUX reactive state** — client-side JavaScript signals/stores and DOM updates.

They can interact through the live bridge, but they are not the same state store.

## 9.3 Update scheduling

`core/scheduler.js` provides a batched asynchronous update queue. The public facade re-exports `enqueue`, `flush`, `forceFlush`, `startBatch`, and `endBatch`.

This means SPPUX does not necessarily perform a DOM update immediately for every individual state operation; scheduling can group updates into a batch.

## 9.4 Tagged-template rendering

The runtime exposes `html` and `Fragment` from the template module. Components can therefore represent UI through tagged templates while the runtime retains the ability to track pending event handlers and produce trusted HTML representations.

The standalone/public API should be documented from the actual `core/template.js` implementation.

## 9.5 Event delegation

`core/events.js` provides handler registration/removal and event delegation. The current facade exposes `registerHandler`, `removeHandler`, `removeAllHandlers`, and `initDelegation`.

This is a concrete runtime mechanism, distinct from the PHP `SPPEvent` dispatcher.

## 9.6 DOM reconciliation

`core/reconciler.js` exports `reconcileDOM`, `patchAttributes`, and `longestIncreasingSubsequence`. The presence of the longest-increasing-subsequence helper indicates that the keyed reconciliation path uses an algorithmic optimization for ordered child updates.

The handbook will analyze the actual reconciliation implementation before stating complexity or describing exactly which DOM operations are minimized.

## 9.7 BaseComponent

The SPPUX facade defines a `BaseComponent` for JavaScript components. The repository also contains public type definitions (`sppux.d.ts`) and UI modules.

The client-side component model is therefore not just a set of widgets: it is a runtime abstraction over state, rendering, events, and lifecycle.

## 9.8 Error boundaries

`core/error-boundary.js` contributes `ErrorBoundaryMixin` and `findNearestErrorBoundary`. Error isolation is therefore explicitly part of the client runtime architecture.

## 9.9 SPPUX bridge

The Drishyam directory contains `sppux-bridge.js`. This bridge is the architectural seam between SPPUX and the broader SPP/Live environment. The handbook will document the actual messages and integration points from the bridge source rather than assuming that every SPPUX event becomes an SPP kernel event.

## 9.10 Grid and UI layers

The runtime is supplemented by dedicated `sppux-grid.js` and `sppux-ui.js` modules. These should be documented after the core runtime so that the reader understands the rendering/reactivity engine before learning the higher-level controls.

## 9.11 Standalone distribution

The repository contains `spp/res/js/sppux.standalone.js`, indicating a distribution path that can operate independently of the full server framework runtime. The handbook will compare standalone mode with integrated mode once the respective bootstrapping code has been audited.

## 9.12 Comparison with React/Vue

| Concern | React/Vue | SPPUX |
|---|---|---|
| Client-side reactive state | Yes | Yes |
| Batched scheduling | Yes | Yes |
| Declarative templates | JSX / templates | `html` tagged templates |
| Event delegation | Framework/runtime dependent | Dedicated module |
| DOM reconciliation | Yes | Dedicated reconciler |
| Error boundaries | Yes / framework-specific | Dedicated module |
| Server integration | Framework-specific | SPP bridge/live integration |

SPPUX is therefore best documented as a **reactive client runtime integrated with SPP**, not merely as a widget library.

## 9.13 Nerd track

The total-nerd chapters for SPPUX will go below component APIs and examine:

- signal dependency mechanics;
- batching and scheduler queues;
- tagged-template representation;
- pending event handler storage;
- event delegation registry;
- keyed reconciliation and LIS usage;
- attribute patching; and
- error-boundary traversal.

Only the parts verified from the implementation will be marked as normative behavior.
