# 63 — Feature Evidence and Status Model

## Purpose

A large framework handbook becomes unreliable when implementation, documentation, inference, and future intent are written in the same voice. This chapter defines the evidence model used to keep SPP documentation precise.

## Authority order

When sources disagree, use this order:

```text
Executable source
      ↓
Tests / fixtures
      ↓
Consumed configuration / manifests
      ↓
Repository documentation
      ↓
Architectural interpretation
```

This does not mean documentation is unimportant. It means a claim about **current behavior** must ultimately be reconciled with what the running implementation actually does.

## Evidence levels

| Level | Meaning | How it should be written |
|---|---|---|
| **Implemented** | Current source provides the behavior described. | “SPP does…” with a source landmark. |
| **Documented** | Repository documentation explicitly describes the capability, but implementation evidence is incomplete or not yet traced. | “Repository documentation describes…” |
| **Derived** | The behavior is inferred by combining concrete implementation facts. | State the facts and label the conclusion as derived. |
| **Guidance** | Recommended architecture, teaching advice, or design practice rather than an SPP guarantee. | “A reasonable approach is…” |
| **Planned/Unverified** | Intended, aspirational, incomplete, or not verified against current source. | Say so explicitly; do not present it as current behavior. |

## Claim discipline

Every important technical statement should answer three questions:

1. **What is the claim?**
2. **What evidence supports it?**
3. **What does the evidence not prove?**

For example:

> A class exposes a transaction method.

This proves the API exists. It does **not**, by itself, prove ACID semantics, rollback durability, nested-transaction behavior, crash recovery, or distributed transaction guarantees.

Likewise:

> A generated manifest declares an endpoint.

This proves metadata generation. It does not automatically prove that the endpoint is authenticated, authorized, rate-limited, or safe for production exposure.

## Evidence record

Use this compact form when adding or auditing a feature chapter:

```text
Claim:
Feature / subsystem:
Status:
Primary source:
Supporting test / fixture:
Consumed configuration or manifest:
Observed behavior:
Limitations / non-proven guarantees:
Last source verification:
Related handbook chapters:
```

The **limitations** field is mandatory for security-, transaction-, distributed-, AI-, transport-, or deployment-sensitive claims.

## Examples from SPP architecture

### SPPAI manifest authentication

The current AI facade can generate OpenAPI-like metadata. The generated manifest has been observed with an authentication type of `none` in the implementation under audit.

Therefore the safe handbook statement is not “the AI manifest is zero-trust.” The correct statement is that the manifest describes the API surface, while authentication and authorization must be established separately according to the actual request boundary.

**Status:** Implemented observation + Guidance about interpretation.

### XDB transaction API

The storage layer exposes transaction-oriented methods. The existence of `beginTransaction`, `commit`, and `rollBack` is evidence of an API surface; it is not proof of every transaction semantic an enterprise reader might infer.

**Status:** Implemented API; stronger transactional guarantees require additional evidence.

### SPPAuth

Repository documentation describes extensive identity/security capabilities. Individual capabilities should still be tied to their concrete implementation and call sites. A feature label such as “RBAC,” “MFA,” or “OAuth” should not be converted into a blanket claim that every application using the module is secure.

**Status:** Feature-specific implementation/documentation evidence; overall security posture remains application-dependent.

### Parikshak

Parikshak is treated by the handbook as SPP's primary testing engine and as part of the learning lifecycle. The curriculum therefore uses it repeatedly instead of isolating testing into one chapter.

**Status:** Implemented/documented project capability; individual test examples should still be verified against the current runner/API.

## How status changes

### Upgrade a claim

A claim can move upward when stronger evidence appears:

```text
Planned/Unverified
       ↓
Documented
       ↓
Derived
       ↓
Implemented
       ↓
Implemented + test evidence
```

The final state is not a new evidence label; the additional test evidence simply makes the implementation claim stronger.

### Downgrade a claim

If source inspection contradicts a handbook statement:

1. correct the handbook immediately;
2. record the discrepancy if it affects architecture;
3. downgrade the evidence level;
4. remove unsupported guarantees;
5. add or update a source audit where useful.

Do not preserve a stronger claim because it appeared in an older chapter, README, generated document, or marketing-style description.

## Negative evidence matters

A missing implementation path can be as important as an existing class.

Examples:

- a configuration key exists but is never consumed;
- a documented CLI command has no current handler;
- an event name appears in documentation but is not registered;
- a security mechanism exists but a relevant request path bypasses it;
- a generated manifest declares no authentication.

Such findings should be recorded as **limitations**, not hidden because they make the feature look less complete.

## Source-map conventions

Prefer source landmarks that are stable and meaningful:

```text
SPP runtime
  → spp/core/<relevant file>

Module facade
  → spp/modules/<module>/<relevant file>

Configuration
  → <module>/etc/<config file>

Test / fixture
  → <test or fixture path>
```

When a path has changed between source snapshots, state the version/branch and verify the current branch before publishing a new path.

## Avoiding common evidence errors

### “Class exists” → “feature is complete”

Wrong. A class proves only that code exists. Trace callers, configuration, lifecycle, and failure behavior.

### “Documentation says it” → “runtime guarantees it”

Wrong. Documentation is evidence of intended/documented behavior, not automatically of current execution.

### “Method returns true” → “operation succeeded durably”

Wrong. Inspect what the method actually does and what its callers test.

### “Security module exists” → “application is secure”

Wrong. Security is a property of the complete request/data/trust boundary and its configuration.

### “Distributed component exists” → “distributed guarantee exists”

Wrong. Names such as cluster, Raft, worker, federation, or bridge require source-level proof of the actual protocol and failure semantics before stronger claims are made.

## Audit frequency

Run an evidence audit whenever:

- a major source snapshot changes;
- a new subsystem chapter is added;
- a security-sensitive claim changes;
- a generated API/documentation surface changes;
- a deployment or distributed architecture diagram changes;
- a reader-facing guarantee is strengthened.

The September architecture audits in Chapters 81–84 are examples of this practice.

## The rule for authors

**Be precise about what is known, explicit about what is inferred, and conservative about what is not proven.**

A narrower true statement is better than a broader impressive statement that the source cannot support.
