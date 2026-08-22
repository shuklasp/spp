# Priority Five — Source Map

This map is a verification aid, not a substitute for reading the source.

| Subsystem | Start at | Then inspect | Evidence to seek |
|---|---|---|---|
| SPPDB | public SPPDB application boundary | module initialization, driver resolution, compiler path, connection management | tests/configuration/observed behavior |
| SPPReport | report facade/service | schema inspection, query validation, SPPDB execution, output/result path | report tests, schema validation, integration fixtures |
| LiveComponent | component base/public API | lifecycle hooks, state serialization, checksum/HMAC verification, validation integration | component tests, lifecycle fixtures |
| SPPLive | SPPLive facade/orchestrator | engine discovery/selection, configuration, fallback, transport boundary | transport configuration/tests |
| SPPUX | SPPUX runtime/bootstrap facade | URI/asset resolution, mount/bootstrap, configuration | browser/runtime examples and tests |

## Trace procedure

1. Find the class or service used by application code.
2. Identify the configuration or module activation path.
3. Follow the first important call into the runtime.
4. Find tests or fixtures that demonstrate the behavior.
5. Only then inspect lower-level helpers.

## Evidence labels

Use one of these labels in handbook prose:

- **Source verified** — behavior is directly established by current source.
- **Test verified** — behavior is demonstrated by a current automated test/fixture.
- **Configuration verified** — behavior is established through a current consumed configuration/manifold.
- **Observed** — behavior was actually exercised and recorded.
- **Architectural interpretation** — a reasoned explanation, not a direct guarantee.
- **Planned/unverified** — useful direction that is not established by current implementation evidence.

## Prohibited shortcuts

Do not treat:

- generated PHPDoc alone;
- an old handbook paragraph;
- a class name;
- a module directory name;
- or the existence of a configuration key

as proof of a broad runtime guarantee.
