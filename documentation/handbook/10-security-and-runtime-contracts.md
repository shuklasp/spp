# 10. Security and Runtime Contracts

> Status: source-driven reference. Claims in this chapter are limited to mechanisms that are represented by the SPP source tree and its declared runtime contracts.

## 10.1 Security as a framework concern

SPP exposes security-sensitive behavior through framework infrastructure rather than requiring every controller or component to implement its own transport and authorization rules.

The handbook treats authentication, authorization, input validation, output rendering, event dispatch, LiveComponent state transfer, and inter-process communication as separate concerns. They must not be conflated.

## 10.2 Runtime contracts

The framework is organized around contracts between subsystems:

- application context and scheduler
- registry and service consumers
- modules and their manifests
- event producers and listeners
- rendering code and SPPView
- LiveComponent state and its transport engine
- SPPUX state and reconciliation/runtime services
- external applications and IPC adapters

The important engineering rule is to document the contract actually implemented by the corresponding interface/class rather than infer a protocol from a class name.

## 10.3 Trust boundaries

An enterprise SPP deployment can contain multiple trust boundaries:

```text
Browser
   |
   v
HTTP / Live transport
   |
   v
SPP application context
   |
   +--> Registry/services
   +--> Event system
   +--> Modules
   +--> Rendering
   |
   +--> IPC / external services
```

Each transition should be treated as an explicit boundary. Data crossing a boundary should be validated and normalized by the receiving layer.

## 10.4 Live state is not an authorization boundary

A client-provided LiveComponent value must never be treated as proof that an action is permitted. Authorization belongs to the server-side execution path. A state snapshot is transport data, not a security principal.

## 10.5 Rendering safety

SPPView ultimately produces executable/rendered PHP through its BladeOne-based compiler stack. Template authors must distinguish escaped output from intentionally raw output and ensure that data-origin boundaries are understood before using raw rendering constructs.

## 10.6 IPC safety

External application integration should authenticate and authorize calls independently of the fact that the caller is another application. An SPP application calling a Python, Node, Go, Java, or non-SPP service is crossing a trust boundary even when both services belong to the same deployment.

## 10.7 Evidence rule

The handbook will identify security mechanisms only after tracing them to the actual implementation, configuration, or tests. A generic enterprise recommendation is not automatically an SPP feature.
