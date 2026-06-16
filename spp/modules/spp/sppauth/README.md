# SPPAuth Module

SPPAuth is the core Identity and Access Management (IAM) module for the Satya Portal Pack (SPP) framework. It has been extensively upgraded to support a **Zero-Trust Identity Provider Architecture**.

## Features

### 1. Multi-Factor Authentication (MFA)
- Supports Time-based One-Time Passwords (TOTP).
- Authenticator app compatibility (Google Authenticator, Authy, etc.).
- Transparent enforcement inside `SPPAuth::login()`.
- Generates QR Codes for easy onboarding via Google Charts API.

### 2. Passwordless Magic Links
- Generate one-time authentication tokens.
- Automatic consumption by the frontend `admin.js`.
- Configurable expiration times.
- `php spp.php auth:magic-link` CLI integration.

### 3. Attribute-Based Access Control (ABAC)
- Fine-grained access control using JSON-based condition logic.
- Dynamically parses `context` and `user` state.
- Integrated directly into the `WebGuard::can()` authorization pipeline.
- Fully managed via the `sppadmin` Identity UI.

### 4. OAuth 2.0 Identity Provider
- Provides third-party applications with standard Authorization Code flow.
- Fully functional HTML consent screen (`/authorize`).
- Secure Token exchange endpoint (`/token`).
- `php spp.php oauth:client:create` CLI command to provision clients.
- SPP Admin interface for full OAuth Client lifecycle management.

### 5. SCIM 2.0 Automated Provisioning
- Allows synchronization with external enterprise Identity Providers (Azure AD, Okta).
- Standard `/Users` endpoint mapping payload to the `SPPUser` object.
- Handles creation (POST) and updates/deactivation (PATCH/PUT).
- Integrated with `TokenGuard` for bearer token validation.
- `php spp.php scim:test:user` CLI command for local testing and debugging.

## Database Schema
SPPAuth relies on several core database tables deployed through SPP migrations:
- `users`, `roles`, `rights`, `entity_roles`, `roleright`
- `abac_policies`
- `magic_links`
- `oauth_clients`, `oauth_auth_codes`, `oauth_tokens`

## Administration
All SPPAuth settings and identities are centrally managed inside the **SPP Admin** panel under the "Access Control (IAM)" section.
