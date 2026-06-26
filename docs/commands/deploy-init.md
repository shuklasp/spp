# NAME

deploy:init - Initialize SPPDeploy configuration for a project

# SYNOPSIS

`php spp.php deploy:init`

# PURPOSE

The `deploy:init` command scaffolds the foundational configuration files required by the SPP Framework's deployment system. It creates a deployment ignore file (`.sppignore`) to prevent sensitive or unnecessary files from being uploaded, and an interactive YAML configuration file (`.sppdeploy.yml`) that defines deployment environments and authentication tokens.

# OPTIONS AVAILABLE

This command takes no arguments. However, it relies on interactive standard input (`STDIN`) to prompt the user for the name of the primary deployment environment.

# UNDER THE HOOD ACTIVITY

When executed, the command determines the project root by resolving the directory containing the SPP base directory (`dirname(SPP_BASE_DIR)`).

First, it checks for the existence of an `.sppignore` file in the root directory. If one is not found, it generates a default `.sppignore` containing common exclusion patterns, such as `/.git`, framework cache directories (`/spp/var/cache`), session data, log files, backups, the deployment configuration itself, and the `.maintenance` flag file. This ensures that a subsequent deployment does not accidentally sync massive directories or sensitive local configurations to the remote server.

Next, it checks for an existing `.sppdeploy.yml` configuration file. If missing, the command opens a stream to `php://stdin` and interactively prompts the user to enter a name for their primary environment (defaulting to `production` if left blank). The system then generates a cryptographically secure 64-character hexadecimal deployment token by calling `bin2hex(random_bytes(32))`. 

A scaffolded `.sppdeploy.yml` file is then written to disk, containing the specified environment name, a placeholder URL, and the generated token. The YAML file also includes commented-out examples of advanced deployment features such as webhook notifications, post-deployment commands, and data anonymization rules. The console finally outputs the generated token, instructing the developer to securely store it as the `SPP_DEPLOY_TOKEN` environment variable on the remote server to enable authenticated deployments.

# EXAMPLES

**Initialize the deployment configuration interactively:**
```bash
php spp.php deploy:init
```
*(The command will prompt you for the primary environment name and subsequently generate the required `.sppignore` and `.sppdeploy.yml` files).*
