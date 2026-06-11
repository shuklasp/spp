# SPP API Authentication Guide

The SPP Framework provides a robust, dual-mode authentication system for all `/api/v1/*` endpoints. This ensures your data is secure while providing flexible integration options for both frontend applications and server-to-server integrations.

All API routes require authentication unless explicitly configured otherwise.

## 1. Authentication Modes

### Temporary JWT Tokens
Best suited for Single Page Applications (SPAs) and Mobile Applications. Tokens are short-lived and tied to a specific user's session.

*   **Endpoint**: `POST /api/v1/auth/token`
*   **Payload**: `{"username": "your_username", "password": "your_password"}`
*   **Response**: Returns a secure JWT and an `expires_in` timestamp.

### Permanent API Keys
Best suited for backend systems, cron jobs, and third-party API integrations where an interactive login is not possible.

*   **Generation (CLI)**: Use the built-in CLI command to generate a token safely:
    ```bash
    php spp.php api:key-generate "Service Name"
    ```
*   **Generation (UI)**: Use the `sppadmin` Workbench. Navigate to **API Keys** to generate and manage keys via the web interface.

## 2. Authenticating Requests

Once you have obtained either a JWT Token or a Permanent API Key, include it in your HTTP requests using one of the following methods:

**Method 1: Authorization Header (Recommended)**
Include the token in the standard HTTP `Authorization` header as a Bearer token.
```http
Authorization: Bearer <your_token_or_api_key>
```

**Method 2: Query String Parameter**
Append the `api_key` parameter to your URL. This is useful for simple GET requests but less secure as URLs are often logged.
```http
GET /api/v1/user?api_key=<your_token_or_api_key>
```

## 3. Configuration

Authentication settings are defined in the API module's configuration file: `spp/modules/spp/sppapi/module.yml`.

```yaml
module:
  id: sppapi
  name: SPP API Module
  config_variables:
      require_key: true          # Set to false to disable authentication globally
      enable_jwt: true           # Enables JWT issuance and validation
      jwt_secret: "YOUR_SECRET"  # The secret key used to sign JWTs (Change this in production)
      jwt_expires_in: 3600       # Token lifespan in seconds (Default: 1 hour)
```

> **Important**: Always change the `jwt_secret` to a strong, random string before deploying to production.
