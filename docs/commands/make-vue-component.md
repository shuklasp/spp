# NAME
`make:vue-component` - Scaffold a new Vue 3 component (ESM/No-build)

# SYNOPSIS
`php spp.php make:vue-component <ComponentName> [--Name=<ComponentName>] [--app=context]`

# PURPOSE
The `make:vue-component` command provisions an unbundled EcmaScript Module (ESM) Vue 3 component explicitly constructed to operate independently of a build process. It leverages raw browser ESM imports to provide Vue's reactive capabilities natively within SPP views.

# OPTIONS AVAILABLE
- `<ComponentName>` or `--Name=<ComponentName>` (string, required): The target Vue component name.
- `--app=<context>` (string, optional): Resolves the target directory relative to the application context.

# UNDER THE HOOD ACTIVITY
The command sanitizes the requested name, sets the target path at `comp/{ClassName}.js`, and forcefully creates the necessary directory tree.
The CLI populates the generated `.js` artifact with a script importing `ref` dynamically from `https://esm.sh/vue`. It exports a default Vue configuration object utilizing the Composition API `setup()` function returning a reactive `count` state. Rather than requiring a `.vue` SFC (Single File Component) compiler, it explicitly maps the component layout into a raw string `template` parameter.

# EXAMPLES
**1. Scaffold an ESM Vue component:**
```bash
php spp.php make:vue-component UserDashboard --app=admin
```
