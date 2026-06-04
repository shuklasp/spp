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

## 5. Upcoming Phase: Module Consolidation
In Phase 7, redundant modules like `sppauth`, `sppprofile`, and `sppgroups` will be merged into a single **Identity** domain. Data migrations will be provided.
