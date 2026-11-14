# Chapter 28 — SPP Framework Feature Inventory and Coverage Matrix

This chapter is the master inventory for deciding what the handbook and tutorial curriculum must teach.

The purpose is not to turn the handbook into a list of every PHP file. Instead, features are grouped by **architectural responsibility** and assigned one of three teaching levels:

- **Core mandatory** — every new SPP developer should learn it.
- **Specialized branch** — important SPP capability that deserves a dedicated tutorial/lab.
- **Contributed or application-specific** — documented when useful, but not part of the universal learning path.

## 28.1 Core runtime and application architecture

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Bootstrap and application loading | Core mandatory | First application + request lifecycle |
| Autoloading / PSR-4 | Core mandatory | First application + Kernel Hacker lab |
| `SPP\App` | Core mandatory | Application/context tutorial |
| Scheduler and application contexts | Core mandatory | Multi-application lab |
| Registry | Core mandatory | Dependency/context lab |
| Container / dependency injection | Core mandatory | Service lab |
| Module discovery | Core mandatory | Module lab |
| Module manifests/dependencies | Core mandatory | Module lab |
| Compiled module registry | Core mandatory | Module internals lab |
| Configuration | Core mandatory | Configuration lab |
| Settings / DB settings | Specialized branch | Configuration and operations lab |
| Events / EventHandler / SPPEvent | Core mandatory | Dedicated event lab |
| Middleware / Pipeline / MiddlewareKernel | Core mandatory | **First major framework deep-dive** |
| Routing / request dispatch | Core mandatory | Routing lab |
| Multi-tenant domain routing (`DomainRouter`) | Core mandatory | Domain routing lab |
| Single & Multi-Domain modes (`domain:mode`) | Core mandatory | Architecture modes & copy-paste lab |
| Domain application scoping (`domain:app:scope`) | Core mandatory | Domain security lab |
| Universal OS & Infra engine (`Infra`) | Core mandatory | Infrastructure & runtime lab |
| Application uninstaller & backups (`AppUninstaller`) | Core mandatory | Lifecycle & operations lab |
| API route model binding | Specialized branch | API lab |

## 28.2 MVC and application structure

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| MVC concepts | Core mandatory | Plain-PHP MVC before SPP |
| Controllers/handlers | Core mandatory | Main application tutorial |
| Models/entities | Core mandatory | Data branch |
| Services | Core mandatory | Main application tutorial |
| Repositories/data services | Core mandatory | Data branch |
| Views | Core mandatory | Presentation branch |
| Application feature boundaries | Core mandatory | Modules and architecture labs |

SPP should be explained as a framework capable of supporting MVC-style organization, while also extending beyond strict MVC with middleware, events, modules, reactive runtimes, multiple application contexts, and polyglot integration.

## 28.3 Presentation stack

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPPView | Core mandatory | Presentation branch |
| View locator/router/controller | Core mandatory | View internals lab |
| ViewTag | Core mandatory | ViewTag lab |
| Forms | Core mandatory | Form/validation lab |
| View validator | Core mandatory | Form/validation lab |
| Extended BladeOne / SPPBlade | Core mandatory | Blade migration lab |
| Drishyam | Core mandatory | Rendering/Blade integration lab |
| Asset orchestration | Specialized branch | Frontend assets lab |
| JavaScript generator | Specialized branch | Frontend integration lab |
| Twig-related support | Specialized branch | Template engine comparison lab |

## 28.4 Server-side reactive architecture

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| LiveComponent | Core mandatory | Dedicated branch |
| Lifecycle | Core mandatory | LiveComponent lab |
| Hydration/dehydration | Core mandatory | State lab |
| State signing/checksums | Core mandatory | Security lab |
| Computed state | Core mandatory | LiveComponent lab |
| Validation | Core mandatory | LiveComponent lab |
| Event dispatch | Core mandatory | Live/event integration lab |
| Lazy rendering | Specialized branch | Performance lab |
| Isolated rendering | Specialized branch | Performance lab |
| Streaming | Specialized branch | Live streaming lab |
| Downloads | Specialized branch | Live file/download lab |

## 28.5 SPP Live transports

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPP Live abstraction | Core mandatory | LiveComponent branch |
| AJAX fallback | Core mandatory | First live lab |
| SSE | Specialized branch | Transport lab |
| WebSocket | Specialized branch | Transport lab |
| Redis live engine | Specialized branch | Distributed/live transport lab |
| SQLite live engine | Specialized branch | Local/live transport lab |
| Upload/live handlers | Specialized branch | Upload lab |

## 28.6 SPPUX and browser runtime

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPPUX runtime | Core mandatory for reactive developers | Dedicated SPPUX branch |
| Signals | Core mandatory for SPPUX branch | SPPUX lab |
| Computed state | Core mandatory for SPPUX branch | SPPUX lab |
| Effects | Core mandatory for SPPUX branch | SPPUX lab |
| Batching/scheduler | Core mandatory for SPPUX branch | Reactive internals lab |
| Tagged templates | Core mandatory for SPPUX branch | Template lab |
| Event delegation | Core mandatory for SPPUX branch | Interaction lab |
| DOM reconciliation | Core mandatory for SPPUX branch | Reconciler lab |
| Error boundaries | Specialized branch | Reliability lab |
| Grid/UI components | Specialized branch | UI application lab |
| Server/client bridge | Core mandatory for integrated apps | Live + SPPUX integration lab |

## 28.7 Data and persistence

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPPDB abstraction | Core mandatory | Data branch |
| DB adapters | Core mandatory | Data internals lab |
| Entity model | Core mandatory | Entity branch |
| Entity query | Core mandatory | Query branch |
| Query builder | Core mandatory | Query branch |
| SPP XDB facade | Core mandatory for XDB users | Dedicated XDB branch (`$xdb->xbam()`, `$xdb->rag()`, `$xdb->temporal()`, `$xdb->merkle()`, `$xdb->live()`) |
| XML engine | Specialized branch | Native XML document database (`XMLEngine`) |
| SQLite engine | Specialized branch | SQLite-backed relational engine (`SQLiteEngine`) |
| Zero-Copy Flat Buffer (.xbam) | High-performance storage | 32-byte fixed node records with Dietz Pre/Post interval indexing |
| Universal Inverted Index | High-performance search | MarkLogic-style unified postings for paths, values, word tokens, and ranges |
| AI RAG & Tree Chunker | AI & LLM integration | Tree-aware hierarchical chunking, hybrid Vector+BM25, and agent tool calling |
| Bi-Temporal Time Travel | Enterprise data modeling | SQL:2011 / XTDB dual-timeline (Valid Time vs System Time) with As-Of queries |
| Cryptographic Merkle Ledger | Audit & compliance | Append-only SHA-256 hash chain, Merkle root, inclusion proofs, and tamper detection |
| Reactive Live Queries (CDC) | Real-time streaming | Standing query subscriptions generating Hotwire Turbo Streams (<turbo-stream>) and SSE |
| Advanced SQL Dialect | Query engine | CTEs (`WITH`), $O(N+M)$ Hash Joins, Outer Joins, Correlated `EXISTS`, UPSERT, Savepoints, Window Functions |
| W3C XQuery 3.1 & XQUF | Native XML standards | FLWOR expression execution and in-place tree mutations |
| MVCC Snapshot Isolation | Concurrency & transactions | Multi-Version Concurrency Control with Write-Ahead Logging (`_wal.jsonl`) |
| CRUD | Core mandatory | XDB/data lab |
| Schema | Core mandatory | Migration lab |
| Indexing | Specialized branch | XDB performance lab |
| Views | Specialized branch | XDB advanced lab |
| Pagination | Core mandatory | Data/API lab |
| Validation | Core mandatory | Data lab |
| ACL | Specialized branch | XDB security lab |
| Locking | Specialized branch | Concurrency lab |
| Transactions | Specialized branch | Transaction lab; source-verified semantics only |
| Encryption | Specialized branch | Data security lab |
| Observers | Specialized branch | Data/event lab |
| Query caching | Specialized branch | Cache/XDB lab |
| Migrations | Core mandatory | Migration lab |
| Seeders | Core mandatory | Data setup lab |
| XDB shell | Specialized branch | Operations lab |
| XDB CLI | Specialized branch | Operations lab |
| XDB factory/controller | Kernel Hacker | XDB internals |
| Raft-related implementation | Specialized/Kernel Hacker | Separate source-audited distributed-data lab |

## 28.8 Authentication and security

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Authentication | Core mandatory | Identity branch |
| WebGuard | Core mandatory | Identity branch |
| TokenGuard/API auth | Core mandatory for API developers | API/security branch |
| Roles | Core mandatory | Authorization branch |
| Rights/permissions | Core mandatory | Authorization branch |
| Policy/context evaluation | Specialized branch | Authorization deep dive |
| CSRF | Core mandatory | **Web Security Lab** |
| Sanitization | Core mandatory | **Web Security Lab** |
| Rate limiting | Core mandatory | **Web Security Lab** |
| Throttling middleware | Core mandatory | **Web Security Lab** |
| Security headers | Core mandatory | **Web Security Lab** |
| Session/security checks | Core mandatory | Security branch |
| MFA | Core mandatory for authentication branch | Identity lab |
| Remember-me | Specialized branch | Identity lab |
| Session/device revocation | Specialized branch | Identity operations lab |

## 28.9 API architecture

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPPAPI | Core mandatory for service/API apps | Dedicated API branch |
| API responses | Core mandatory | API branch |
| API resources | Core mandatory | API branch |
| Pagination | Core mandatory | API branch |
| Route model binding | Specialized branch | API branch |
| JWT auth | Core mandatory for secured APIs | API security lab |
| API middleware | Core mandatory | API security lab |
| API documentation | Specialized branch | OpenAPI/API docs lab |
| AJAX API/live action support | Specialized branch | Live/API lab |

## 28.10 Audit, revisions, diff, and content promotion

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Audit subsystem | Core mandatory for enterprise apps | Audit branch |
| Delta engine | Specialized branch | Revision/diff lab |
| Revision manager | Specialized branch | Revision lab |
| Offline content preparation | Specialized branch | Content promotion lab |
| Transfer/package workflow | Specialized branch | **Offline → Live promotion lab** |
| Zero-downtime migration analysis | Specialized branch | Deployment lab |
| Rollback/recovery | Specialized branch | Promotion/recovery lab |

These must remain distinct from ordinary schema migrations.

## 28.11 Workflow

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Workflow manager | Core mandatory for workflow applications | Dedicated workflow branch |
| State transitions | Core mandatory | Workflow lab |
| Approval chains | Specialized branch | Approval-chain project |
| Wizard/controller workflow | Specialized branch | Wizard project |
| Timeouts | Specialized branch | Workflow operations lab |
| Workflow events | Specialized branch | Workflow/event integration |

## 28.12 Cache, logging, observability, reporting

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Cache abstraction | Core mandatory | Operations lab |
| File/Redis cache | Specialized branch | Cache backend lab |
| Cache tags | Specialized branch | XDB/cache lab |
| Logging | Core mandatory | Diagnostics lab |
| Audit logging | Core mandatory for enterprise apps | Audit branch |
| Reporting | Specialized branch | Reporting project |
| Scheduled reports | Specialized branch | Cron/reporting lab |
| OpenTelemetry exporter | Specialized/enterprise | Observability branch |

## 28.13 Storage and files

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Sovereign File facade (`\SPP\Core\File`) | Core mandatory | File services lab |
| Cross-platform atomic writes (`putAtomic`) | Core mandatory | File resilience lab |
| Storage multi-disk abstraction (`\SPP\Core\Storage`) | Core mandatory | Storage branch |
| Disk interface (`DiskInterface`) | Core mandatory | Storage architecture lab |
| Local disk (`LocalDisk`) | Core mandatory | Storage lab |
| Pure-PHP cloud S3/R2 storage (`S3Disk`) | Specialized/enterprise | Cloud storage lab |
| In-memory virtual disk (`MemoryDisk`) | Specialized branch | Serverless & testing lab |
| Fluent directory query builder (`FileFinder`) | Core mandatory | File operations lab |
| Expiring signed URLs (`temporaryUrl`) | Specialized/security | Secure documents lab |
| HTTP 206 range streaming (`streamDownload`) | Specialized branch | Media & downloads lab |
| Multi-tier MIME & magic-byte sniffer | Core mandatory | File verification lab |
| Linked/synchronized storage | Specialized branch | Deployment/content lab |
| File transfer | Specialized branch | Content promotion lab |

## 28.14 Scheduler, cron, workers, asynchronous work

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Scheduler | Core mandatory | Foundations |
| Cron scheduler | Core mandatory for operations | Scheduled-work branch |
| Cron run/list/flush | Specialized branch | Operations lab |
| Queues/workers | Specialized/enterprise | Worker branch |
| Long-running services | Specialized branch | Worker/runtime lab |
| Asynchronous polyglot workers | Specialized branch | Polyglot branch |

## 28.15 Polyglot, Multi-Language Hosting & Service Mesh

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Declarative Application Manifest (`ForeignAppRegistry`, `etc/apps.yml`) | Core enterprise | Multi-language catalog & routing lab |
| Subpath Virtualization Engine (`SubpathVirtualizer`, `SubpathGateway`) | Core enterprise | Route directory & subdomain virtualization lab |
| Sovereign Cross-Platform Process Supervisor (`AppSupervisor`) | Core enterprise | Daemon lifecycle & port allocation lab |
| Ambient Sensing Protocol & Zero-Dependency SDKs (Python, Node, PHP, WordPress) | Specialized branch | Microservice SDK & SSO lab |
| Sovereign Inter-App Service Mesh (`\SPP\Mesh`, `MeshServiceDispatcher`) | Core enterprise | Inter-app RPC & event bus lab |
| Unified Developer Studio Workbench (`/sppdev#apps`, `/sppdev#mesh`) | Core mandatory | Developer Studio workbench lab |
| Polyglot proxy | Specialized branch | Polyglot lab |
| Bridge interface/factory | Specialized branch | Bridge internals |
| Language bridges (Python, Node, Go, C++, Java, .NET) | Specialized branch | Language-specific labs |
| External application adapters | Specialized branch | External integration branch |
| Drupal/legacy integration | Contributed/specialized | External application lab |
| Protocol/security boundaries | Core enterprise | Integration security lab |

## 28.16 AI

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPPAI facade | Specialized branch | AI branch |
| AI driver interface | Specialized branch | AI architecture lab |
| Multiple provider drivers | Specialized branch | Provider integration lab |
| AI configuration | Specialized branch | AI operations lab |
| AI exception/self-healing tutorial | Specialized | Reliability/AI branch |

The handbook should document provider abstractions and concrete integrations without turning provider-specific behavior into a universal SPP guarantee.

## 28.17 Language and internationalization

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| SPPLang | Specialized branch | i18n lab |
| ContentTranslator | Specialized branch | Translation lab |
| TranslatableEntity | Specialized branch | Data/i18n lab |
| Import/export language resources | Specialized branch | Localization operations |

## 28.18 Developer tooling and framework meta-programming

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| CLI | Core mandatory | CLI branch |
| Generators | Core mandatory | Generator lab |
| Documentation generator | Specialized branch | Framework tooling |
| PHPDoc generation | Specialized branch | Framework tooling |
| OpenAPI generation | Specialized/API | API documentation lab |
| Blade/View generators | Specialized | Presentation tooling |
| Entity/Form/CRUD generators | Specialized | Scaffolding lab |
| Polyglot generators | Specialized | Polyglot tooling |
| SPPUX generators | Specialized | SPPUX tooling |

## 28.19 Testing with Parikshak

Parikshak is a dedicated SPP testing framework and therefore must be used throughout the tutorial curriculum, not merely documented at the end.

| Test surface | Teaching treatment |
|---|---|
| Test cases/assertions | First Parikshak lab |
| Application-aware testing | Core testing branch |
| Runner | Core testing branch |
| Faker | Test-data lab |
| RefreshDatabase | Database lab |
| API interaction | API test lab |
| Events | Event test lab |
| Middleware | Middleware test lab |
| Module tests | Module lab |
| Workflow tests | Workflow lab |
| LiveComponent tests | Reactive testing lab |
| Integration tests | Enterprise branch |

## 28.20 Specialized application modules

The repository also contains application/contributed domains such as reporting, school/domain modules, documentation/content tooling, Drupal integration, and other application-specific features.

These should be catalogued, but the handbook should distinguish them from universal kernel features.

## 28.21 Declarative Manifest OS & Component Governance

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Directory manifests (`__spp_file_props.yml`) | Core mandatory | Manifest OS mastery lab |
| High-performance SQLite WAL compilation | Specialized/enterprise | Performance & storage lab |
| Deno-style capability sandboxing | Core mandatory for security | Web security & sandbox lab |
| Static data flow taint tracking | Specialized branch | Security & static analysis lab |
| Declarative SSR & Progressive Hydration | Core mandatory for views | Reactive view & partials lab |
| Multi-database connection routing | Specialized branch | Data routing & replication lab |
| Continuous AST self-healing (`props:heal`) | Core mandatory | Developer tooling & watcher lab |
| Software Bill of Materials (SBOM) | Specialized/enterprise | Supply chain & provenance lab |
| AI attention beacons & semantic vector RAG | Specialized branch | AI & knowledge graph lab |

## 28.22 Sovereign App Store, Publisher Governance, and Host Node Telemetry

| Area | Teaching level | Tutorial treatment |
|---|---|---|
| Publisher Self-Service Registration & Bcrypt Hashing | Core mandatory | Identity & Store lab |
| User Status Lifecycle & Administrative Suspension | Core mandatory for security | Moderation & governance lab |
| Central Content Moderation Queue (`/admin/moderation`) | Core enterprise | Operations & governance lab |
| Granular Project Visibility (`public`, `personal`, `group`, `team`) | Core mandatory | Project architecture lab |
| Self-Service Project Wizard & Auto-Scaffolding | Core mandatory | Developer DX & store lab |
| Package-to-Project Auto-Linking (`pkg:install` badge) | Core mandatory | Distribution & showcase lab |
| Public Publisher Vanity Profiles (`/store/publisher`) | Specialized branch | Community & publisher lab |
| Semantic Versioning Release Wizard & Deprecation | Specialized branch | Package lifecycle lab |
| Bare-Metal Node Telemetry Console (`/admin/server`) | Core enterprise | Sovereign Linux operations lab |
| Zero-cPanel Sovereign Hosting Architecture (AlmaLinux 9) | Core enterprise | Deployment topology lab |

## 28.23 Final curriculum rule

A subsystem belongs in the **core tutorial** when a normal SPP developer needs it to understand the framework.

A subsystem gets a **mandatory feature lab** when the capability is important but too specialized to fit naturally into the core story.

A subsystem becomes a **specialized branch** when it represents a distinct application architecture, such as XDB, SPPUX, workflow, APIs, AI, storage, polyglot integration, reporting/observability, or offline-content promotion.

A subsystem is **reference-only/contributed** when it is application-specific and should be learned when the developer actually adopts that module.

This classification is not a judgment about how sophisticated a module is. It is a teaching decision designed to keep the handbook detailed without making the beginner path unreadable.
