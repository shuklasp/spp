# Core Module: SppAjax

SppAjax is the framework's specialized handler for asynchronous requests and API communications. It provides a standardized way for the frontend to interact with backend services.

---

## 1. Basic Philosophy
SppAjax follows the **"Unified Response"** philosophy. Every AJAX request, whether it succeeds or fails, should return a consistent JSON structure that the frontend can parse reliably. This eliminates "silent failures" and simplifies frontend error handling.

---

## 2. Architecture
The module functions as a specialized router for requests carrying the `X-Requested-With: XMLHttpRequest` header or a specific `ajax=1` parameter.

### Key Components:
*   **Response Factory**: Generates consistent JSON envelopes `{status, data, message}`.
*   **Action Router**: Maps incoming AJAX actions to specific module methods or class functions.
*   **Security Guard**: Automatically validates CSRF tokens for all state-changing (POST/PUT/DELETE) requests.

---

## 3. API & Usage

### Backend: Creating an AJAX Endpoint
```php
class MyApi {
    public static function getUserData($params) {
        $user = \SPP\DB::row("SELECT * FROM users WHERE id = ?", [$params['id']]);
        
        if (!$user) {
            return \SPP\Ajax::error("User not found");
        }
        
        return \SPP\Ajax::success($user);
    }
}
```

### Frontend: Calling an AJAX Endpoint
```javascript
spp.ajax('my_module/getUserData', { id: 42 })
    .then(data => {
        console.log("User Name:", data.name);
    })
    .catch(error => {
        alert("Error: " + error);
    });
```

---

## 4. Response Structure
Standard SppAjax responses always follow this format:
```json
{
    "status": "success",
    "data": { ... },
    "message": "Optional message"
}
```
In case of error:
```json
{
    "status": "error",
    "message": "Detailed error description"
}
```

---
[Back to Modules Index](index.md)
