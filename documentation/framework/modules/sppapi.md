# Core Module: SppAPI (Headless REST Framework)

SppAPI is the built-in Headless REST API routing layer for the SPP framework. It empowers any entity driven by the SPP infrastructure to be automatically securely exposed to front-end clients, mobile apps, or third-party integrations with zero custom controller code required.

---

## 1. Zero-Config CRUD Routing
Rather than manually creating an API Controller for every data structure, `SPPAPI::handle()` dynamically maps the HTTP method (`GET`, `POST`, `PUT`, `DELETE`) directly to underlying `SPPEntity` methods.

### Endpoint Structure
```
/api/v1/{entity_name}/{id?}
```
- `{entity_name}`: The canonical name of your entity (e.g. `User`, `Article`). This relies directly on the entity registry declared by your app's `module.yml`.
- `{id}`: An optional identifier mapped strictly for `GET` (fetch single), `PUT` (update), or `DELETE`.

---

## 2. Dynamic Payload Resolution
Because `SppAPI` rests entirely on top of `SPPEntity`, it inherits the framework's enterprise capabilities automatically.

### Reading Data (`GET`)
Retrieves entities automatically, filtering if necessary via query strings.
```bash
# Fetch a single article
curl -X GET /api/v1/Article/42

# Fetch filtered list
curl -X GET /api/v1/Article?status=published&author_id=7
```
**Enterprise Edge Cache:** GET requests automatically append proper HTTP `X-SPP-Cache-Tags` (e.g., `Article:42`, `Article_list`) for CDN caching.

### Writing Data (`POST` / `PUT`)
When you submit a JSON body, SppAPI parses the payload. Due to `SPPEntity`'s native schema mapping:
1. Valid columns are written strictly to native DB columns.
2. Unmapped attributes are transparently serialized into the `fields_data` dynamic property (if enabled) without SQL schema updates.
3. If `track_revisions: true` is set in the entity's `module.yml`, the engine seamlessly logs the delta logic on `PUT`.

```bash
# Create an Article with a dynamic meta property
curl -X POST /api/v1/Article \
  -H "Content-Type: application/json" \
  -d '{"title":"New Paradigm", "body":"...", "custom_SEO_score": 98}'
```

---

## 3. Localization Support
The API fully respects the framework's multi-lingual system. When a client passes an acceptable language identifier (e.g., via query string `?lang=fr` or standard `Accept-Language` headers), the SppAPI instructs the `SPPEntity` to invoke `setLanguage()`. 

The response will serve localized copies from `spp_entity_translations` while seamlessly persisting any updates dynamically.

---
[Back to Modules Index](index.md)
