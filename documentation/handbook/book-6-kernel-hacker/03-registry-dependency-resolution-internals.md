# Book 6 Chapter 3 — Registry and Dependency Resolution Internals

Start with a public `make()`/binding call and trace:

```text
request to resolve
→ binding lookup
→ dependency inspection
→ constructor invocation
→ lifetime/cache handling
```

## Lab

Trace TaskService creation and identify every dependency the container resolves.

## Rule

Do not confuse a convenient helper API with the implementation contract underneath it.
