# Book 2 Chapter 6 — The SPP CLI as a Framework Interface

## 1. Why a framework needs a CLI

A framework CLI turns repetitive development and operational work into framework-aware commands.

Typical responsibilities include:

- generating application artifacts;
- inspecting runtime state;
- managing configuration;
- working with routes/modules;
- running tests;
- running scheduled work;
- administering application state.

## 2. SPP's CLI is broader than scaffolding

Think of the SPP CLI as three layers:

```text
SPP CLI
 ├── generators/scaffolds
 ├── one-shot framework commands
 └── interactive SPP command mode
```

The repository contains a substantial command surface. The exact command names and arguments are part of the source/documentation contract and should be copied from the current repository rather than from an old tutorial.

## 3. Interactive SPP mode

The interactive mode should be understood as a framework-aware developer environment.

The conceptual workflow is:

```text
enter SPP command mode
       ↓
inspect framework/application state
       ↓
perform framework operation
       ↓
inspect result
```

Treat the interactive prompt as a convenience and diagnostic interface, not as a replacement for application runtime behavior.

## 4. Scaffolding as education

A generated file is useful teaching material.

After running a generator:

1. open every generated file;
2. identify which parts are required by the runtime;
3. identify which parts are conventions;
4. remove one generated element and test again.

## 5. Hands-on lab

Use the current SPP CLI to create or inspect a Task Desk artifact.

Record:

```text
command
arguments
created files
runtime consequence
```

Then reproduce the result manually where practical.

## 6. Failure lab

Use an invalid command or configuration argument and identify whether the CLI itself rejects the input or the generated artifact later fails at runtime.

## 7. Kernel Hacker

Trace a CLI command from its registration to its handler implementation and any framework services it resolves.

## Checkpoint

> **The SPP CLI is a framework-aware developer interface: it can generate artifacts, operate on the runtime, and provide an interactive SPP working mode.**
