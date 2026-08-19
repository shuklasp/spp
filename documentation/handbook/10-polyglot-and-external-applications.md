# Volume VIII — Polyglot and External Integration

## Chapter 10 — Polyglot Bridges and External Application Integration

**Evidence:** `spp/core/Polyglot/`, `spp/services/`, `spp/lib/polyglot/`, `spp/lib/python/`, `spp/lib/node/`, `spp/lib/go/`, `spp/lib/java/`, `spp/lib/dotnet/`, `spp/modules/contrib/`, `spp/docs/tutorials/integrating_external_apps.md`, integration commands.

SPP contains an explicit polyglot subsystem and an external-application integration layer. The architecture is broader than PHP-to-HTTP calls: the repository includes bridge interfaces/factories, language-specific bridge implementations, runtime helper libraries, daemon services, and integration modules.

## 10.1 Polyglot bridge abstraction

The core PHP layer contains:

- `PolyglotBridgeInterface`;
- `PolyglotBridgeFactory`;
- `DefaultBridge`;
- `CompilerBridge`;
- `DotNetBridge`;
- `GoBridge`; and
- `JavaBridge`.

This is an adapter/factory architecture:

```text
                 SPP PHP application
                         │
                         ▼
              PolyglotBridgeFactory
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
       Go bridge      Java bridge    .NET bridge
          │              │              │
          └─────── language/runtime ────┘
                         │
                         ▼
                 external process/runtime
```

The factory allows application code to work through a common bridge abstraction while the concrete runtime differs by language.

## 10.2 Runtime support in the repository

The tree contains language assets and/or services for:

- C++;
- .NET;
- Go;
- Java;
- Node.js;
- Perl; and
- Python.

Examples include `services/*/daemon_service.*`, language helper libraries under `lib/`, and compiled/managed bridge artifacts for .NET/Java where present in the supplied source package.

The existence of these assets is evidence of integration support; each language adapter will receive its own reference chapter before the handbook makes claims about deployment guarantees.

## 10.3 Polyglot command surface

The repository contains CLI documentation and commands for polyglot operations including bridge execution, workers, status, listing, asynchronous execution, and polyglot partial generation.

This makes polyglot integration a first-class developer workflow rather than an undocumented side channel.

## 10.4 External applications

The repository also contains an integration tutorial and contributed modules for external systems. `spp/modules/contrib/sppdrupal` contains a Drupal application adapter, and the integration documentation describes installation and routing of an external application under an SPP-managed URL path.

The supported architectural concept is therefore:

```text
                       SPP Router / Integration layer
                                  │
                 ┌────────────────┴────────────────┐
                 ▼                                 ▼
           native SPP app                    external app
                 │                                 │
          SPP modules/views                  native runtime
                 │                                 │
                 └──────────── integration ────────┘
```

The external application can retain its own runtime rather than being rewritten as an SPP module.

## 10.5 Integration modes

The source and documentation show several different integration patterns. They should not be collapsed into one generic "IPC" mechanism.

### Native embedding / routing bypass

The external application can be installed under a public path and configured so SPP routing bypasses that path for the external runtime.

### Application adapters

Contributed modules can provide PHP-side adapters around external systems. These adapters expose integration-specific services to SPP rather than pretending the external application is an ordinary SPP module.

### Polyglot bridge

A polyglot bridge crosses a language/runtime boundary through the bridge abstraction and associated runtime service.

### Webhook / API integration

The repository contains integration controllers, webhook-related classes, and API modules. These are protocol integrations rather than process embedding.

## 10.6 Multi-application versus polyglot

These are separate layers:

| Layer | SPP mechanism |
|---|---|
| Multiple SPP applications | `Scheduler` + application context |
| SPP module composition | Module manifests/registries |
| External SPP-facing service | Integration modules/adapters |
| Other language runtime | Polyglot bridges/services |
| External application runtime | Integration/router adapters |

This distinction prevents a common architectural error: treating every external service as if it were an SPP module.

## 10.7 Enterprise architecture pattern

The implemented building blocks can be composed like this:

```text
                         SPP host
                            │
        ┌───────────────────┼────────────────────┐
        ▼                   ▼                    ▼
   SPP App A            SPP App B          integration layer
        │                   │                    │
      modules             modules          external systems
        │                                        │
        │                             ┌──────────┼───────────┐
        │                             ▼          ▼           ▼
        └───────────────────────── Python     Node.js     .NET/Go/Java
```

The diagram is a composition of verified building blocks; it is not a claim that every transport between every node already exists.

## 10.8 Security boundary

Cross-runtime integration must be treated as a trust boundary. Authentication, authorization, signature validation, network controls, and payload validation belong to the specific adapter/transport implementation. The handbook will therefore document the actual security behavior of each bridge and integration module rather than asserting a universal SPP security protocol.

## 10.9 Comparison with other ecosystems

| Need | Typical framework pattern | SPP building blocks |
|---|---|---|
| Multiple apps in one PHP host | Separate deployments | Scheduler contexts |
| Plugins/modules | Package/bundle | SPP Module |
| Python/Go/Java integration | HTTP/custom RPC | Polyglot bridge + service layer |
| External legacy application | Reverse proxy | Integration/router adapter also available |
| Frontend live runtime | Framework-specific | SPP Live + SPPUX |

## 10.10 Nerd track

The deep-dive chapters will inspect:

- bridge interface and factory resolution;
- process/service invocation contracts;
- serialization formats used by each bridge;
- daemon lifecycle;
- timeout/error handling;
- external-app installation/routing behavior;
- webhook ingress and adapters; and
- how SPP context metadata is propagated across an integration boundary where the source supports it.

No transport is labeled "IPC" merely because it crosses a process boundary; its actual protocol and implementation will be named explicitly.
