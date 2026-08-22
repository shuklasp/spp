# V3 Priority Five: Source-Verified Runnable Labs

## Purpose

These labs are the first execution-focused validation layer for the five subsystems that were explicitly identified as changed in the August 2026 SPP source baseline:

- SPPDB
- SPPReport
- LiveComponent
- SPPLive
- SPPUX

The rule for all five is the same:

**Read the public concept → run the smallest example → observe the runtime → test the behavior → break it deliberately → trace the source → record the evidence.**

Exact command syntax and internal guarantees must be taken from the current repository implementation, not remembered from an earlier handbook revision.

## Lab 1 — SPPDB

### Goal

Understand the SPPDB public application boundary before studying the compiler and driver layers.

### Observe

Start by identifying the current SPPDB module initialization and primary database class in the source tree.

Then answer:

1. How is the database service made available to application code?
2. Where is driver selection represented?
3. Where does SQL compilation happen?
4. Where is connection reuse/pooling represented, if enabled by the current source?
5. How does XDB relate to the lower-level database services?

### Break exercise

Intentionally use an unsupported driver configuration or malformed connection definition in a disposable environment.

Record:

```text
configuration
→ discovery
→ driver resolution
→ connection failure
→ diagnostic surface
```

### Source trace

Begin at the public SPPDB entry point and move toward the driver/compiler implementation. Do not start with the deepest compiler class.

### Acceptance

The learner can explain why SPPDB is more than direct PDO construction and can identify the source location responsible for the current behavior.

---

## Lab 2 — SPPReport

### Goal

Treat reporting as a data/metadata pipeline rather than merely “export rows to HTML”.

### Observe

Trace the current reporting implementation and identify:

- connection boundary;
- schema inspection;
- query construction/validation boundary;
- execution boundary;
- output/report boundary.

### Break exercise

Use an invalid table/column reference or deliberately unsafe input in a controlled test case. Confirm which validation layer rejects it.

### Source trace

Start with the report service/facade used by application code. Follow its dependencies to SPPDB and schema validation.

### Acceptance

The learner can explain the difference between:

```text
report request
→ metadata/schema understanding
→ safe query construction
→ database execution
→ report result
```

and can identify the source files implementing each responsibility.

---

## Lab 3 — LiveComponent

### Goal

Understand LiveComponent as a lifecycle/state mechanism rather than as an AJAX helper.

### Observe

Create the smallest component containing one public piece of state and one action.

Trace the current lifecycle implemented by the repository, including the hooks that actually exist in the current source.

Pay particular attention to:

```text
boot
booted
hydrate
updating
updated
action/rendering
render
rendered
dehydrate
```

Only document hooks that are present in the current implementation.

### State-integrity exercise

Inspect how serialized public state is protected by the implementation. Where the current source uses an HMAC/checksum mechanism, identify:

- what is serialized;
- what is signed/verified;
- where verification occurs;
- what happens when verification fails.

Do not infer broader security guarantees from this one mechanism.

### Validation exercise

Trace the current validation integration, including the validator trait used by the implementation.

### Break exercise

Use a malformed or tampered serialized state in a controlled test. Record the exact failure behavior.

### Acceptance

The learner can explain the difference between:

```text
component state
transport payload
server lifecycle
rendered result
```

and can trace each through the current implementation.

---

## Lab 4 — SPPLive

### Goal

Understand SPPLive as the runtime/orchestration boundary around live transports and engines.

### Observe

Identify the current engine-selection logic and the engines represented by the repository.

The documentation must distinguish:

```text
component model
transport/engine
runtime selection
fallback
configuration
```

from the start.

### Comparison exercise

Run or inspect the currently configured alternatives and record:

| Concern | Engine A | Engine B | Engine C | Fallback |
|---|---|---|---|---|
| Availability requirement | verify source | verify source | verify source | verify source |
| Transport behavior | verify source | verify source | verify source | verify source |
| Operational dependency | verify source | verify source | verify source | verify source |
| Failure mode | verify source | verify source | verify source | verify source |

Do not fill the table from assumptions.

### Break exercise

Disable one engine's required dependency in a disposable environment and observe whether the runtime selects another engine or fails. Record the actual implementation path.

### Acceptance

The learner can explain why a component and its transport are separate concerns and can point to the current SPPLive selection logic.

---

## Lab 5 — SPPUX

### Goal

Understand SPPUX as part of the browser/runtime bootstrapping path rather than treating it as a generic JavaScript helper library.

### Observe

Trace the current SPPUX implementation and identify:

- runtime path resolution;
- UI asset path resolution;
- application-aware URI generation;
- mount/render bootstrap behavior;
- configuration inputs.

### Browser boundary exercise

For one SPPUX-enabled page, map:

```text
SPP application
→ rendered mount point
→ runtime asset
→ browser initialization
→ reactive/browser behavior
```

### Break exercise

Intentionally use an invalid runtime asset path or invalid application base configuration in a disposable setup and observe the exact failure surface.

### Acceptance

The learner can distinguish:

```text
SPPUX runtime bootstrap
≠
LiveComponent state model
≠
SPPLive transport
```

and can explain how the three can cooperate without being the same subsystem.

---

## Common evidence record

For every lab, keep this small record next to the test case:

```text
Source baseline: August 2026 repository
Primary source file(s): <actual path>
Related configuration: <actual path or none>
Related test/fixture: <actual path or none>
Observed behavior: <what was actually observed>
Architectural interpretation: <inference, clearly marked>
Unverified guarantees: <what the source does not establish>
```

This prevents future handbook updates from turning an observation or design interpretation into an unsupported framework guarantee.
