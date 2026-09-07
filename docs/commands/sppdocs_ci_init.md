## sppdocs:ci:init

**Purpose**: Scaffolds CI/CD automation pipelines and local Git commit hooks to synchronize repository pushes, auto-resolve issues, and log time tracking automatically in SPPDocs.

### Synopsis

```bash
php spp.php sppdocs:ci:init [--project=<project-id>] [--platform=<github|gitlab|local>] [--url=<webhook-url>] [--secret=<secret-token>] [--output=<custom-path>]
```

### Extended Usage

`sppdocs:ci:init` configures turnkey continuous integration pipelines for repositories connected to an SPPDocs documentation and project management workspace.

By deploying the generated workflow into your codebase, every `git push` or pull request automatically sends webhook notifications to the SPPDocs instance. The built-in automation engine parses commit messages for:
- `Fixes #12` / `Closes #12`: Automatically marks issue #12 as closed and appends a commit reference comment.
- `Time: 2.5h` / `log #12 45m`: Automatically records developer time logs against the target issue.
- `Refs #12`: Appends commit cross-references to the issue timeline.

#### Examples

```bash
# Generate GitHub Actions workflow for demo-app
php spp.php sppdocs:ci:init --project=demo-app --platform=github

# Generate GitLab CI configuration
php spp.php sppdocs:ci:init --project=demo-app --platform=gitlab

# Install local post-commit hook for offline development
php spp.php sppdocs:ci:init --project=demo-app --platform=local
```

### Options Available

- `--project=<project-id>`: Target SPPDocs project identifier (default: `demo-app`).
- `--platform=<platform>`: Target CI/CD ecosystem (`github`, `gitlab`, or `local`; default: `github`).
- `--url=<webhook-url>`: Fully-qualified HTTP(S) endpoint of the SPPDocs git webhook receiver (default: `http://localhost/school1/public/api/git-webhook`).
- `--secret=<secret-token>`: HMAC SHA-256 signature secret for authenticating webhook requests (auto-generates cryptographically secure token by default).
- `--output=<custom-path>`: Optional explicit file path to write the configuration to.

### Under the Hood Activity

1. Invokes `GitAutomationService` to compile the platform-specific CI configuration or shell script.
2. Creates parent directories (`.github/workflows` or `.git/hooks`) if they do not exist.
3. Writes the workflow YAML or shell script and marks local hooks executable (`0755`).
4. Strictly enforces CLI SAPI guarding (`isCLIOnly()`), preventing remote web execution.
