# Book 4 Chapter 5 — LiveComponent Actions, Validation, Rendering, and Downloads

## 1. A component is an application boundary

A LiveComponent action is still server-side application behavior. The browser can request it, but the server decides whether it is valid and authorized.

## 2. Action lifecycle

A useful conceptual sequence is:

```text
incoming interaction
 → restore state
 → validate/normalize input
 → invoke action
 → update application state
 → render response
 → dehydrate state
```

The exact implementation order must follow the current LiveComponent source.

## 3. Validation integration

The changed implementation integrates `LiveValidatorTrait`.

This demonstrates an important framework principle: reactive UI validation should reuse the framework's validation concepts rather than inventing a completely separate validation model.

## 4. Rendering

The lifecycle includes rendering hooks and a `render()` operation. A component render should describe the component's current presentation state.

Do not put unrelated database transactions into render logic.

## 5. Downloads

Where the current LiveComponent implementation provides download responses, treat them as a response boundary rather than as ordinary HTML rendering.

## 6. Hands-on lab

Add a `completeTask` action to the Task Desk component.

Requirements:

1. authenticated caller;
2. authorized operation;
3. valid task state;
4. service-layer business rule;
5. component re-render or response;
6. test for success and failure.

## 7. Failure lab

Invoke the action:

- without authorization;
- with invalid component state;
- with invalid task state;
- after tampering with serialized state.

Identify the earliest rejecting layer.

## Checkpoint

> **A LiveComponent action is still a server operation. Reactivity changes the interaction model; it does not remove application, validation, or security boundaries.**
