# Volume VI — Live Runtime

## Chapter 8 — SPP Live Transport Engines

**Evidence:** `spp/modules/spp/spplive/`, especially `LiveEngineInterface.php`, `AjaxFallbackEngine.php`, `SqliteLiveEngine.php`, `RedisLiveEngine.php`, `SSEHandler.php`, `WebsocketLiveEngine.php`, `class.spplive.php`, `class.liveemitter.php`.

SPP separates the server-side LiveComponent model from its transport/runtime engines. The `spplive` module contains multiple engine implementations rather than forcing the component layer to depend on one transport.

## 8.1 Transport abstraction

`LiveEngineInterface` defines the contract for live execution. Concrete implementations in the source include:

- `AjaxFallbackEngine`;
- `SqliteLiveEngine`;
- `RedisLiveEngine`; and
- `WebsocketLiveEngine`.

The module also contains dedicated `SSEHandler` and `UploadHandler` components.

This gives SPP a transport-selection architecture:

```text
                   LiveComponent
                         │
                         ▼
                   SPP Live layer
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
       AJAX/Safe       SSE/WebSocket   shared/live state
       fallback        real-time       Redis/SQLite
```

The exact mapping between engine selection and deployment environment is implementation-specific and belongs in the transport reference pages.

## 8.2 AJAX fallback

`AjaxFallbackEngine` provides a non-persistent request/response path. This is important because it allows LiveComponent functionality to work without requiring a WebSocket infrastructure.

## 8.3 SQLite and Redis engines

The repository contains `SqliteLiveEngine` and `RedisLiveEngine`. These engines demonstrate that SPP Live is not tied to one in-memory PHP mechanism; the source explicitly supports alternate state/transport coordination backends.

The handbook will document the precise data structures and coordination semantics from those classes rather than assuming that Redis and SQLite provide equivalent behavior.

## 8.4 Server-Sent Events

`SSEHandler` provides a server-sent event path. It is relevant to features such as progressive component output and streaming, especially where one-way server-to-browser delivery is sufficient.

## 8.5 WebSockets

`WebsocketLiveEngine` implements the WebSocket-oriented live path. It is part of the same SPP Live subsystem and can therefore participate in LiveComponent dispatch/streaming behavior without changing component business logic.

## 8.6 LiveEmitter

`LiveEmitter` is a legacy-compatible emission helper used by the deprecated `LiveComponent::emit()` family. New code should prefer the current `dispatch()` API documented in the LiveComponent chapter, while legacy applications may still use the emitter path.

## 8.7 Why transport separation matters

```text
Component code
      │
      ▼
SPP Live API
      │
      ├── AJAX fallback
      ├── SSE
      ├── WebSocket
      ├── SQLite-backed coordination
      └── Redis-backed coordination
```

The component model remains focused on application state and UI behavior, while transport concerns are delegated to the live engine layer.

## 8.8 Enterprise deployment questions

When designing a production deployment, the following questions should be answered from the actual engine implementation:

1. Does a deployment require persistent connections?
2. Where is live state stored?
3. How are multiple workers coordinated?
4. How are uploads routed?
5. Which engine is selected for a given environment?
6. How are failures surfaced and recovered?

The handbook will answer each question with source-backed configuration and operational guidance instead of prescribing one universal transport.

## 8.9 Comparison

| Requirement | SPP Live |
|---|---|
| No WebSocket infrastructure | AJAX fallback available |
| One-way push | SSE handler |
| Persistent bidirectional transport | WebSocket engine |
| Alternative shared backends | Redis / SQLite engines |
| Component-level event API | `dispatch()` |
| Legacy event emission | `emit()` / `LiveEmitter` |

## Kernel Hacker note

The architectural boundary between `LiveComponent` and `SPP Live` is one of the most important design decisions in the framework. It allows the PHP component API to remain stable while the runtime transport can vary by deployment constraints.
