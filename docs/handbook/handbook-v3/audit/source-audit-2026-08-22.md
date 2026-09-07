# SPP Handbook V3 — Source Audit 2026-08-22

## Scope

This audit checks the V3 handbook against the repository source currently visible on the documentation branch.

Primary subsystems reviewed:

- LiveComponent
- SPPLive
- SPPUX
- SPPDB
- SPPReport

## Source evidence located

The repository contains current implementation and generated documentation for the target modules, including:

- `spp/modules/spp/spplive/module.yml`
- `spp/modules/spp/drishyam/module.yml`
- `spp/modules/spp/sppdb/modinit.php`
- `spp/modules/spp/sppreport/class.sppreport.php`
- LiveComponent handbook/reference material
- SPPLive phpdoc output
- SPPUX phpdoc and application-development documentation
- SPPDB phpdoc output

## Audit rules

1. Executable source is authoritative for current behavior.
2. Tests/fixtures are stronger evidence than prose.
3. Generated API documentation is useful for inventory, but not sufficient to establish broad guarantees.
4. Older handbook material must not override current implementation.
5. Claims such as atomicity, distributed guarantees, exactly-once delivery, automatic recovery, or transparent distributed execution require explicit source/test evidence.

## Current documentation status

The V3 chapters for the five changed subsystems have been separated by concern:

- LiveComponent: state lifecycle and hydration/dehydration.
- SPPLive: engine-selection/transport architecture.
- SPPUX: browser-runtime/bootstrap and mounting.
- SPPDB: driver/connection/compiler architecture.
- SPPReport: reporting, schema discovery, validation, and execution concerns.

## Remaining QA work

The next audit pass should execute or inspect, where available:

- representative examples in each V3 chapter;
- Parikshak examples for the five subsystems;
- links from the V3 README to all chapter files;
- Mermaid diagram syntax;
- CLI examples against current command definitions;
- source-map paths after future code changes.

This file is an audit record, not a substitute for tests.