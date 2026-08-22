# Book 2 Chapter 2 — SPP Registry and Dependency Injection

## 1. The general idea

Book 1 introduced a container as an object manager. SPP provides a Registry/container model through its application runtime.

The important distinction is between:

```text
What an object does
```

and:

```text
How the runtime obtains the object
```

## 2. Object graph

Suppose:

```text
Controller
   ↓
Service
   ↓
Repository
   ↓
Database
```

A manual application creates this graph itself. A container can construct the graph according to registered bindings and constructor dependencies.

## 3. Binding versus lifetime

The beginner rule remains:

```text
bind      → create according to the registered binding
singleton → reuse one shared instance according to the container's lifetime rules
```

Do not make every service a singleton merely because it can be shared. Mutable operation-specific state can leak between consumers when an object is incorrectly shared.

## 4. SPP application APIs

The repository documentation establishes application-level container operations including `make()`, `singleton()`, and `call()`.

Exact registration and resolution signatures should be checked against the current SPP source before copying code into an application.

## 5. Hands-on lab

Create:

```text
TaskController
TaskService
TaskRepository
```

Make the controller depend on the service and the service depend on the repository.

Resolve the application entry point through the SPP container rather than manually constructing the full graph.

## 6. Break it deliberately

Introduce:

- a missing dependency;
- an invalid binding;
- an incorrectly shared mutable service.

Observe which stage detects each problem.

## 7. Kernel Hacker

Trace:

```text
public resolution API
→ binding lookup
→ dependency inspection
→ instance construction
→ lifetime/cache handling
```

Then inspect tests or fixtures supporting the observed behavior.

## Checkpoint

> **Dependency Injection is the design; the container is the mechanism that assembles the dependency graph.**
