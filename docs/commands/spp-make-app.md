# NAME
spp make:app - Scaffolds a self-contained skeleton app natively

# SYNOPSIS
`php spp.php make:app <appName> [--mode=spa|standard] [--ai-blueprint="Spec"]`

# PURPOSE
Generates an entirely isolated, localized SPP application workspace featuring zero-JS bindings, dedicated component routes, dynamic asset mapping, and fallback authentication logic instantly.

# OPTIONS AVAILABLE
- `<appName>` : Application identifier.
- `--mode=<mode>` : Frontend navigation routing mode (`spa` or `standard`).
- `--ai-blueprint="<spec>"` : Simulated AI layout specification injection string.

# UNDER THE HOOD ACTIVITY
1. **Directory Tree Orchestration:** Builds the `SPP_APP_DIR/src/{appName}` hierarchy including controllers (`serv`), `pages`, `components`, `assets`, `events`, and `themes`.
2. **Asset Provisioning:** Clones the framework logo or dynamically paints a 200x60 JPEG fallback representation via PHP's `GD` engine.
3. **Configuration Synthesis:** Outputs an embedded `etc/config.yml` resolving isolated layout and asset rules, paired with an `etc/routes.yml` hooking the login/logout controllers to paths.
4. **Code Generation:** Authorizes a full `AuthController.php` which implements a localized array-lookup guard skipping typical ORM layers entirely. Generates `task_create.php` representing an API action responding with styled HTML payloads. Generates specialized `login.blade.php` and an instructional `about.php`.
5. **Event Shells:** Wires an event listener skeleton `UserRegisteredHandler.php` inside the application scope intercepting custom hooks natively.
6. **Frontend Synthesis:** Creates a sophisticated `index.php` landing page infused with inline premium CSS, real-time two-way data-binding (`data-spp-bind`), structural partial integrations (`<spp-component>`), and zero-JS interactive forms (`data-spp-post`).

# EXAMPLES
Provision a dashboard workspace:
`php spp.php make:app dashboard --mode=standard`
