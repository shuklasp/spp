# Lekhak CMS: Media & Storage Path Architecture

Lekhak operates on a fully self-contained, portable storage model. All assets, documents, database rows, and uploaded media are structured and resolved contextually within the active application's root workspace directory, allowing Lekhak to function standalone without global state dependencies.

---

## 1. Upload Directory Path Resolution

When dynamic visual content is added via the Lekhni Editor or Lekhak asset managers, Lekhni delegates folder resolution to `StorageOrchestrator` and `LekhniApi`:

```php
// Resolve Upload Directory from Active App's Data Dir (var/)
$dataDir = \SPP\App::getApp()->getDataDir();
$customPath = \SPP\App::getGlobalSettings('lekhni.media_path');
```

The system negotiates storage destinations based on the following hierarchy:

1.  **Custom Absolute Path**: If `lekhni.media_path` is specified in `etc/config.yml` and starts with an absolute indicator (e.g. `/`, `\`, or `C:`), Lekhni mounts files directly to that absolute path.
2.  **Custom Relative Path**: If the registered path is relative, Lekhni resolves it relative to the application's source root (`\SPP\App::getApp()->getAppSrcDir()`).
3.  **Encapsulated Default**: If no custom path is registered, the system deposits uploads inside the application's local sandbox: `var/media/lekhni/`.

---

## 2. Public Browser URL Resolution

To ensure that embedded media renders flawlessly in browser previews and live nodes across arbitrary deployment subdirectories, public URLs are calculated dynamically relative to the application base URI:

```php
$baseUrl = defined('APP_BASE_URI') ? APP_BASE_URI : '';
$relPath = substr($target, strlen(SPP_APP_DIR));
$publicUrl = rtrim($baseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $relPath), '/');
```

This dynamic mapping guarantees that moving the entire site folder to another directory or domain will **never** break media links or assets.

---

## 3. Storage APIs (`LekhniApi`)

Lekhni exposes lightweight RESTful APIs to feed visual components during visual composition:
*   **`upload_media`**: Accepts file payloads via POST, verifies folder schema, sanitizes filenames with unique Unix timestamps, and returns a browser-safe public URL.
*   **`list_media`**: Performs server-side scans of the designated upload directory to feed visual galleries and dropzones dynamically.

---
[Back to Index](index.md)
