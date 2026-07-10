## integration:install

**Purpose**: Automates the scaffolding and routing of an external application within the SPP environment for Zero-Touch integration.

### Synopsis
```bash
php spp.php integration:install <app_name> <route_path>
```

### Extended Usage
The `integration:install` command is the foundational tool for establishing a local Data Mesh. When you want to run a monolithic application (like WordPress or Magento) on the same server as your SPP framework, this command provisions the necessary infrastructure.

It automatically performs three critical steps:
1.  **Filesystem Scaffolding**: It creates a physical directory in the `public` folder corresponding to the requested route.
2.  **Route Bypassing**: It modifies `etc/routes.yml` to instruct the SPP router to ignore this path, allowing the guest application's native `index.php` to handle requests.
3.  **Driver Instantiation**: It prepares the internal `IntegrationFactory` driver with the absolute local path to enable "Native Bootstrapping" for Zero-Touch CDC.

### Options Available
*   `app_name` (string, required): The alias of the registered driver (e.g., `wordpress`, `moodle`, `magento`).
*   `route_path` (string, required): The web-accessible path where the app will live (e.g., `/blog`, `/lms`).

### Under the Hood Activity
*   **Filesystem Writes**: Creates directories via `mkdir()` in the `/public` root. Appends bypass configuration strings to `etc/routes.yml`.
*   **DB Interactions**: None.
*   **Outbound HTTP Calls**: None.
