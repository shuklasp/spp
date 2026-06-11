# SPP Modernization Guide

Welcome to the modernized version of the SPP Framework! This guide covers the key architectural changes and how to use the new features.

## 1. Security First
The new `sppsecurity` module is now part of the core ecosystem.
- **CSRF Protection:** Use `SPPMod\Sppsecurity\Middleware\CsrfMiddleware` to protect all state-changing routes. Generate tokens using the `SPPSecurityProvider`.
- **XSS Prevention:** `SPPSanitizer` provides context-aware escaping (html, js, attribute).
- **Rate Limiting:** `ThrottleMiddleware` handles IP-based rate limiting using the new Token Bucket implementation via the SPP Cache.

## 2. API Development
- **SPPApiResponse:** Use `SPPApiResponse::success()` and `SPPApiResponse::error()` for standardized JSON outputs.
- **Resources:** `SPPApiResource` acts as an intermediary layer to format entity models into JSON arrays.
- **Pagination:** `SPPPaginator::paginateQuery()` automatically counts and limits SQL queries.

## 3. Database Migrations
Database schemas are now version-controlled.
- **Create a migration:** `php spp.php make:migration create_users_table`
- **Run migrations:** `php spp.php migrate`
- **Rollback:** `php spp.php migrate:rollback`

## 4. Developer Experience (DX)
- **.env Support:** Add a `.env` file at the root. The new `DotEnvLoader` will automatically populate `$_ENV` and `$_SERVER`.
- **Ignition Error Pages:** `SPPErrorHandler` replaces the blank white screen of death with a modern, beautifully formatted stack trace when `app.debug` is enabled.
- **Parikshak Upgrades:** The testing module now includes `SPPTestRunner`, Database Factories (`SPPFactory`), and an API request simulation trait (`InteractsWithApi`).

## 5. Distributed Shared Registry & Fault-Tolerant Circuit Breaker
The core `\SPP\Registry` has been completely upgraded to a distributed architecture using a robust Adapter pattern (`SharedStorageInterface`).
- **Auto-Discovery**: Automatically mounts the Redis memory syncing adapter (`RedisSharedStorage`) for horizontally scaled microservices if the Redis extension is active.
- **Circuit Breaker Pattern**: If the Redis memory cluster drops the connection mid-request or suffers a network partition, the core Registry instantly intercepts the failure and gracefully degrades back to atomic disk-based storage (`FileSharedStorage`). This ensures absolute 100% configuration sync uptime and guarantees the framework never crashes due to an infrastructure outage.
- **Application Isolation**: Fully preserves contextual siloing. Different applications executing in the same memory space seamlessly register and fetch keys from their respective isolated segments using `\SPP\Scheduler::getContext()`.

## 6. Upcoming Phase: Module Consolidation
In Phase 7, redundant modules like `sppauth`, `sppprofile`, and `sppgroups` will be merged into a single **Identity** domain. Data migrations will be provided.
