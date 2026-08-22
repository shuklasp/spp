# Book 6 Chapter 6 — Module Discovery and Compiled Registry

Trace a module from metadata to runtime activation:

```text
module source/manifest
→ discovery
→ dependency validation
→ compiled registry
→ activation
→ runtime services/resources
```

## Lab

Pick one module in the repository and create a source map from its manifest to one runtime feature.

## Rule

A generated registry is evidence of compilation/registration, not proof of every runtime guarantee. Follow the consuming code.