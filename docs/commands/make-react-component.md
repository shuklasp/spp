# NAME
`make:react-component` - Scaffold a new React component (ESM/No-build)

# SYNOPSIS
`php spp.php make:react-component <ComponentName> [--Name=<ComponentName>] [--app=context]`

# PURPOSE
The `make:react-component` command scaffolds an unbundled, raw EcmaScript Module (ESM) version of a React Component. Unlike standard React development requiring Webpack or Vite, this command provisions a `.js` file that relies on modern browser imports (`https://esm.sh/react`), allowing SPP architectures to drop-in React interfaces instantly without compilation pipelines.

# OPTIONS AVAILABLE
- `<ComponentName>` or `--Name=<ComponentName>` (string, required): The target Javascript component name (e.g. `ProfileCard`).
- `--app=<context>` (string, optional): The application context affecting directory resolution.

# UNDER THE HOOD ACTIVITY
The command resolves the application context and isolates the component target directory via `getTargetDir('comp', ...)`. It creates `{ClassName}.js`.
The CLI hardcodes an explicit JS script into the generated file containing `import React from 'https://esm.sh/react';` and defines a functional default exported React component utilizing native `React.createElement()` arrays mapped to a functional hook `useState()`. This explicitly avoids JSX transpilation, ensuring it can be natively interpreted by the browser engine interacting natively with SPP views.

# EXAMPLES
**1. Scaffold a drop-in React chart:**
```bash
php spp.php make:react-component DataChart --app=dashboard
```
