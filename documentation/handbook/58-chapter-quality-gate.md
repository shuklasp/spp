# 58. Chapter Quality Gate

This handbook is source-driven documentation. A chapter is complete only when it passes the following gate.

## Content gate

- [ ] Explains the problem before introducing the API.
- [ ] Defines new terminology.
- [ ] Distinguishes framework concepts from SPP-specific implementation.
- [ ] Includes a beginner path.
- [ ] Includes an advanced/internal path.
- [ ] Explains when the feature should not be used.

## Example gate

- [ ] Code follows repository-verified APIs.
- [ ] Commands are verified against repository command documentation/source.
- [ ] Configuration keys are verified.
- [ ] Examples show complete surrounding context when needed.
- [ ] No invented methods/classes/configuration names.

## Diagram gate

- [ ] Diagram represents an actual architecture, lifecycle, sequence, or data flow.
- [ ] Diagram is concise.
- [ ] Mermaid syntax is valid for GitHub rendering.
- [ ] Every node/relationship is supported by source or clearly marked as conceptual.
- [ ] No ASCII pseudo-diagrams are used where a real diagram is required.

## Hands-on gate

- [ ] A learner can build the feature.
- [ ] A Parikshak test is provided where testing is meaningful.
- [ ] A deliberate failure exercise exists.
- [ ] A debugging path exists.
- [ ] A source-trace exercise exists.

## Architecture gate

- [ ] Explains where the feature sits in the runtime.
- [ ] Explains interactions with neighboring SPP subsystems.
- [ ] Identifies trust/security boundaries where relevant.
- [ ] Identifies performance implications where relevant.
- [ ] Identifies operational/deployment implications where relevant.

## Evidence gate

Use this order of authority:

```text
executable source
    ↓
tests / fixtures
    ↓
consumed configuration / manifests
    ↓
repository documentation
    ↓
architectural interpretation
```

If evidence conflicts, the handbook must record the conflict instead of silently converting a documented claim into a framework guarantee.

## Link gate

- [ ] Internal links resolve.
- [ ] Chapter numbers/titles match the current index.
- [ ] Source maps point at existing repository paths.
- [ ] Deprecated or superseded material is clearly labeled.

## Final reader test

A chapter should answer five questions:

1. **Why does this feature exist?**
2. **How would I solve the same problem without a framework?**
3. **How does SPP solve it?**
4. **How do I use and test it?**
5. **How does SPP implement it internally?**
