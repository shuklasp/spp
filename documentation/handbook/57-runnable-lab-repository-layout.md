# 57 — Runnable Tutorial Lab Repository Layout

This chapter defines how the SPP repository should hold executable tutorial material without duplicating the framework itself or creating a second, unofficial application architecture.

## 57.1 Goals

The tutorial code must be:

- easy for a beginner to locate;
- tied to a specific handbook stage;
- reproducible from a known framework state;
- testable with the repository's supported tooling;
- clearly separated from framework source;
- safe to compare with the corresponding handbook chapter.

## 57.2 Recommended top-level structure

```text
tutorials/
  00-plain-php-taskdesk/
  01-first-spp-app/
  02-middleware/
  03-events/
  04-registry-di/
  05-configuration/
  06-routing/
  07-modules/
  08-views-forms/
  09-data-xdb/
  10-security/
  11-parikshak/
  12-api/
  13-workflow/
  14-queue-cron/
  15-reporting-observability/
  16-storage-transfer/
  17-ai/
  18-livecomponent/
  19-spplive/
  20-sppux/
  21-polyglot-ipc/
  22-multiapp/
  23-enterprise-capstone/
```

This is a **documentation target structure**. A directory should only be added when it can be populated with source-verified, runnable material.

## 57.3 What each lab should contain

```text
README.md
START-HERE.md
EXPECTED.md
BREAK-IT.md
SOURCE-MAP.md
TESTING.md
```

Where appropriate, include the actual application files and tests for that stage.

### README.md

Explain the purpose and prerequisites.

### START-HERE.md

Give the exact first action a beginner should take.

### EXPECTED.md

Describe what should happen when the lab is working.

### BREAK-IT.md

Give one deliberate, recoverable failure exercise.

### SOURCE-MAP.md

Map the learner's code to the relevant SPP implementation.

### TESTING.md

State the verified test command/mechanism and what behavior it proves.

## 57.4 One evolving application versus independent labs

Use both.

### Continuous application

The Task Desk evolves through the main course.

This teaches architectural continuity.

### Independent branch labs

Specialized branches may use smaller isolated projects when the subsystem would otherwise distract from its central idea.

This keeps advanced topics focused.

## 57.5 Synchronization rule

A tutorial source file and its handbook chapter must never silently diverge.

When an example changes:

1. update the runnable lab;
2. update its test;
3. update the handbook example;
4. update the source map;
5. record any behavior/status change in the feature-evidence material.

## 57.6 Version pinning

A runnable tutorial must state the framework revision/version against which its exact commands and APIs were verified.

If the tutorial is intentionally version-flexible, avoid presenting implementation-sensitive details as universal.

## 57.7 No invented scaffolding

A lab may use a command only when the repository verifies it.

If the command cannot be source- or documentation-verified, the lab must say so and provide the conceptual step without fabricating syntax.

## 57.8 Test-first expectation

Each lab should have at least one observable testable behavior.

For major framework subsystems, the lab should integrate the supported Parikshak workflow instead of inventing a separate test model.

## 57.9 Cleanup

Tutorial code should be minimal enough for a beginner to understand.

Do not copy the entire framework into a lab.

Do not copy unrelated production code merely to make a demo work.

The learner should be able to identify:

```text
my application code
framework code
configuration
runtime-generated artifacts
```

## 57.10 Completion rule

A lab is ready for the final handbook only when:

- the starting state is clear;
- the result is observable;
- the test path is defined;
- the break/fix exercise is safe;
- the source map is accurate;
- the example does not rely on undocumented assumptions.
