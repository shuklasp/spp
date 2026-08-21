# 55. Feature-to-Tutorial Coverage Matrix

This matrix is the handbook's **coverage contract**.

A feature is not considered fully covered simply because the word appears in a chapter.

A major feature should have as many of these stages as the implementation warrants:

```text
CONCEPT
BUILD
TEST
BREAK/DEBUG
SOURCE TRACE
ARCHITECTURE
WHEN NOT TO USE
```

---

## 55.1 Core framework features

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Framework model | 50, 51 | 31 | — | 53 | 50 |
| MVC | 50, 31 | 31, 51 | 41 | 53 | 50 |
| Application/context | 31, 50 | 33 | 41 | 53 | 02, 33 |
| Registry | 34 | 34 | 41 | 53 | 03 |
| Dependency injection | 34, 50 | 34 | 41 | 53 | 03 |
| Configuration | 35 | 35 | 41 | 53 | 35, config sources |
| Middleware | 32 | 32 | 41 | 53 | 04, middleware source |
| Events | 33 | 33 | 41 | 53 | 04, event source |
| Routing / pages | 36 | 36 | 41 | 53 | 36, routing engine |
| CLI routing | 35/36, 36 CLI | 35/36 | 41 | 53 | 36, CLI source |
| Modules | 37 | 37 | 41 | 53 | 05, module source |
| Scaffolding | 30, 37 | 37 | 41 | 53 | commands/stubs |

---

## 55.2 Presentation

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| SPPView | 38 | 38 | 41 | 53 | 06, View source |
| Extended BladeOne | 38 | 38 | 41 | 53 | Blade integration |
| ViewTags | 38 | 38 | 41 | 53 | ViewTag source |
| Drishyam | 38 | 38 | 41 | 53 | Drishyam source |
| Forms | 38 | 38 | 41 | 53 | form scaffolds |
| Validation | 38, 40 | 38 | 41 | 53 | validators |
| Assets/resources | 38 | 38 | 41 | 53 | SPPView/resource source |

---

## 55.3 Data and persistence

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Entity model | 40 | 40 | 41 | 53 | entity source |
| SPPDB | 40 | 40 | 41 | 53 | sppdb |
| XDB facade | 40 | 40 | 41 | 53 | sppxdb |
| Query builder | 40 | 40 | 41 | 53 | query source |
| Pagination | 40 | 40 | 41 | 53 | paginator |
| Migrations | 40, 47 | 40, 47 | 41 | 53 | migration managers |
| Seeders | 40 | 40 | 41 | 53 | seeder source |
| Indexing | 40 | 40 | 41 | 53 | index source |
| Validation | 40 | 40 | 41 | 53 | validation source |
| Transactions | 40 | 40 | 41 | 53 | implementation-specific |
| Locking | 40 | 40 | 41 | 53 | implementation-specific |
| ACL | 40, 43 | 40, 43 | 41 | 53 | ACL/security source |
| Observers | 40 | 40 | 41 | 53 | observer source |
| XDB administration | 40 | XDB shell branch | 41 | 53 | CLI/source |

---

## 55.4 Identity and security

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Authentication | 17, 43 | 43 | 41 | 53 | Auth source |
| Authorization/RBAC | 43 | 43 | 41 | 53 | Auth/security source |
| CSRF | 43 | 43 | 41 | 53 | security source |
| Sanitization | 43 | 43 | 41 | 53 | security source |
| Rate limiting | 43 | 43 | 41 | 53 | security source |
| Throttling | 43 | 43 | 41 | 53 | security source |
| Security headers | 43 | 43 | 41 | 53 | security source |
| JWT/API auth | 42, 43 | 42/43 | 41 | 53 | SPPAPI/Auth source |

---

## 55.5 Testing and quality

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Parikshak | 41 | 41 | 41 | 53 | Parikshak source |
| Unit tests | 41 | 41 | 41 | 53 | Parikshak |
| Integration tests | 41 | 41 | 41 | 53 | Parikshak |
| API tests | 42, 41 | 42 | 41 | 53 | API + Parikshak |
| Database isolation | 41 | 41 | 41 | 53 | RefreshDatabase/source |
| Event tests | 33, 41 | 33 | 41 | 53 | event tests |
| Workflow tests | 44, 41 | 44 | 41 | 53 | workflow + Parikshak |
| Reactive tests | 48–50, 41 | 48–50 | 41 | 53 | Live/UX source |

---

## 55.6 API and application services

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| SPPAPI | 42 | 42 | 41 | 53 | SPPAPI source |
| API resources | 42 | 42 | 41 | 53 | API resource source |
| API responses | 42 | 42 | 41 | 53 | API response source |
| Pagination | 42 | 42 | 41 | 53 | API paginator |
| Route model binding | 42 | 42 | 41 | 53 | binding source |
| API documentation | 42 | 42 | 41 | 53 | API docs source |
| AJAX/live actions | 42, 49 | 42/49 | 41 | 53 | API/Ajax source |

---

## 55.7 Workflow and background processing

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Workflow | 44 | 44 | 41 | 53 | workflow source |
| Approval chains | 44 | 44 | 41 | 53 | workflow source |
| Wizard | 44 | 44 | 41 | 53 | wizard source |
| Timeouts | 44, 43 | 44 | 41 | 53 | workflow/cron source |
| Saga/compensation | 44 | 44 | 41 | 53 | implementation-specific |
| Queue | 43 | 43 | 41 | 53 | SppQueue/source |
| Workers | 43 | 43 | 41 | 53 | worker source |
| Cron | 43 | 43 | 41 | 53 | Cron/Scheduler source |
| Scheduled reports | 42, 43 | 42/43 | 41 | 53 | report cron source |

---

## 55.8 Storage, content, reporting, observability

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Storage abstraction | 45 | 45 | 41 | 53 | Storage/Disk source |
| Local disk | 45 | 45 | 41 | 53 | LocalDisk |
| i18n | 45 | 45 | 41 | 53 | SPPLang source |
| Translatable entities | 45 | 45 | 41 | 53 | TranslatableEntity |
| Reporting | 42 | 42 | 41 | 53 | report module |
| Report viewer | 42 | 42 | 41 | 53 | report viewer |
| Audit | 45 | 45 | 41 | 53 | SPPAudit |
| Revisions/diffs | 47 | 47 | 41 | 53 | DeltaEngine/RevisionManager |
| Logging | 42 | 42 | 41 | 53 | logger |
| OpenTelemetry where implemented | 42 | 42 | 41 | 53 | exporter source |

---

## 55.9 Migration, transfer, and promotion

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Schema migration | 40, 47 | 40 | 41 | 53 | migration managers |
| Data transfer | 47 | 47 | 41 | 53 | transfer implementation |
| Offline preparation | 47, 51 | 47, 51 | 41 | 53 | promotion architecture |
| Diff/revision | 47 | 47 | 41 | 53 | DeltaEngine/RevisionManager |
| Staging | 47 | 47 | 41 | 53 | deployment architecture |
| Promotion | 47 | 47 | 41 | 53 | deployment architecture |
| Rollback/recovery | 47, 53 | 47 | 41 | 53 | recovery path |
| Zero-downtime compatibility | 47 | 47 | 41 | 53 | implementation-specific |

---

## 55.10 AI

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| SPPAI facade | 44 | 44 | 41 | 53 | SPPAI |
| Driver abstraction | 44 | 44 | 41 | 53 | AIDriverInterface |
| Provider drivers | 44 | 44 | 41 | 53 | driver implementations |
| AI-backed application feature | 44, 51 | 51 | 41 | 53 | application + AI |
| AI failure handling | 44, 53 | 44 | 41 | 53 | exception/driver source |
| Self-healing tutorial | 44 | 44 | 41 | 53 | repository tutorial |

---

## 55.11 Reactive architecture

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| LiveComponent | 48 | 48 | 41 | 53 | livecomponent source |
| Lifecycle | 48 | 48 | 41 | 53 | source |
| Public state | 48 | 48 | 41 | 53 | source |
| Hydration/dehydration | 48 | 48 | 41 | 53 | source |
| Validation | 48 | 48 | 41 | 53 | source |
| Streaming | 48 | 48 | 41 | 53 | source |
| Lazy/isolated rendering | 48 | 48 | 41 | 53 | source |
| SPP Live | 49 | 49 | 41 | 53 | transport source |
| AJAX fallback | 49 | 49 | 41 | 53 | transport source |
| SSE | 49 | 49 | 41 | 53 | transport source |
| WebSocket | 49 | 49 | 41 | 53 | transport source |
| Redis / SQLite transport support where implemented | 49 | 49 | 41 | 53 | transport source |
| SPPUX | 50 | 50 | 41 | 53 | SPPUX source |
| Signals/reactive state | 50 | 50 | 41 | 53 | SPPUX source |
| Scheduler/batching | 50 | 50 | 41 | 53 | SPPUX source |
| Templates/events | 50 | 50 | 41 | 53 | SPPUX source |
| Reconciliation | 50 | 50 | 41 | 53 | SPPUX source |
| Error boundaries | 50 | 50 | 41 | 53 | SPPUX source |
| Server/client bridge | 50 | 50 | 41 | 53 | SPPUX/Live integration |

---

## 55.12 Integration and enterprise architecture

| Feature | Concept | Build | Test | Debug | Source / architecture |
|---|---|---|---|---|---|
| Polyglot architecture | 51 | 51 | 41 | 53 | bridge/source |
| IPC | 51 | 51 | 41 | 53 | bridge/protocol source |
| External non-SPP app | 51 | 51 | 41 | 53 | integration boundary |
| Multiple application contexts | 52/49 | 52 | 41 | 53 | Scheduler/App |
| Shared services | 52 | 52 | 41 | 53 | architecture |
| Trust boundaries | 43, 49, 51 | 51 | 41 | 53 | security/integration |
| Deployment topology | 49, 53 | capstone | 41 | 53 | deployment |
| Enterprise capstone | 53, 51 | 53 | 41 | 53 | whole system |

---

## 55.13 What “fully covered” means

For a **core framework feature**, the target is:

```text
concept ✅
build ✅
test ✅
break/debug ✅
source trace ✅
architecture ✅
```

For a **specialized feature**, the target may be:

```text
concept ✅
build ✅
test ✅
source trace ✅
```

For a **contributed or application-specific feature**, a reference entry may be sufficient unless the feature is promoted into the main learning curriculum.

This matrix is reviewed whenever repository source or scaffold scans reveal a new framework capability.
