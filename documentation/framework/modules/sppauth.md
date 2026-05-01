# Core Module: SppAuth (Modernized)

SppAuth is a professional-grade, Guard-based authentication system for the SPP framework. It is designed to handle multiple security strategies (Sessions, Tokens, JWT) through a unified API.

---

## 1. Basic Philosophy
SppAuth follows the **"Pluggable Guard"** philosophy. Instead of a single hardcoded session-based system, it acts as a manager for multiple security "Guards." This allows the same framework to secure traditional web interfaces and stateless REST APIs simultaneously.

---

## 2. Architecture: Guards & Providers

### The Guard Manager (`\SPP\Auth`)
The central entry point. It manages the lifecycle of different authentication drivers.

### Guards (`GuardInterface`)
Guards define how users are authenticated for a given request.
*   **WebGuard**: Uses standard PHP sessions to persist identity.
*   **TokenGuard**: (Optional) Uses Bearer tokens or API keys for stateless requests.

### User Providers (`UserProviderInterface`)
Providers define where the user data comes from (e.g., Database, LDAP).

---

## 3. API & Usage

### Standard Web Authentication
```php
use \SPP\Auth;

// Check if a user is logged in
if (Auth::check()) {
    $user = Auth::user();
    echo "Welcome, " . $user->name;
}

// Perform a login
Auth::login($credentials);

// Perform a logout
Auth::logout();
```

### Multi-Guard Usage
You can specify a guard explicitly to handle different authentication contexts:
```php
// Check API authentication specifically
if (Auth::guard('api')->check()) {
    $apiUser = Auth::guard('api')->user();
}
```

---

## 4. Role-Based Access Control (RBAC)

SppAuth implements a sophisticated RBAC system that integrates with **SppGroup** for permission inheritance.

### The Permission Waterfall
Permissions flow through the system in a hierarchical chain:
1.  **Permission**: The atomic right (e.g., `edit_posts`).
2.  **Role**: A collection of permissions (e.g., `Editor`).
3.  **Group**: A collection of roles (assigned to a department or team).
4.  **User**: Inherits all permissions from all roles in all groups they belong to.

### Authorization API
Use the `can()` method to perform granular security checks:
```php
if (Auth::can('delete_system_logs')) {
    // Dangerous action allowed
}
```

---

## 5. Lifecycle Events
SppAuth emits framework-wide events that allow other modules to respond to security actions:
*   `event_spp_auth_login`: Fired when a user successfully authenticates.
*   `event_spp_auth_logout`: Fired when a user session is terminated.

---
[Back to Modules Index](index.md)
