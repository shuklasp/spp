## make:form

**Purpose**: Scaffold a zero-code declarative YAML form definition (default) or programmatic fluent PHP form class for SPP applications.

### Synopsis
```bash
php spp.php make:form <name> [--app=appname] [--yaml] [--php]
```

### Extended Usage
The `make:form` command provisions form definitions in SPP. By default or with `--yaml`, it generates a **zero-code declarative YAML form** in `etc/apps/<appname>/forms/<name>.yml` that requires zero PHP code to build, bind, or validate. Passing `--php` generates a programmatic builder class adhering to the fluent builder API.

```bash
# Generate zero-code declarative YAML form (default) in default app context
php spp.php make:form contact

# Generate zero-code declarative YAML form for the 'school' app
php spp.php make:form StudentRegistration --app=school

# Generate a programmatic fluent PHP builder class
php spp.php make:form Contact --php

# Generate both YAML definition and PHP builder class
php spp.php make:form Feedback --yaml --php
```

### Declarative Zero-Code Features Generated
The generated YAML form showcases:
- **Standard Inputs**: `text`, `email`, `password`, `number`, `tel`, `phone`, `url`, `hidden`, `textarea`, `select`, `multiselect`, `checkbox`, `radio`, `toggle`, `date`, `file`, `range`, `color`, `mask`.
- **Specialized Enterprise Controls**: `signature`, `otp`, `tags`, `rating`, `repeater`, `editor`/`wysiwyg`, `code`, `autocomplete`, `grid`, `cropper`, `treeselect`, `duallist`.
- **Zero-Code Validation**: Inline properties (`required: true`, `min: 3`, `max: 100`, `unique: "users,email"`), pipe strings (`validations: "required|email"`), and rule lists (`rules: ['required', 'numeric']`).
- **Reactive UI Modifiers**: `col` (responsive grid spans), `depends_on` (conditional visibility), `computed` (real-time math formulas), `help`, `placeholder`.
- **Bot Defense & Lifecycles**: `honeypot: true` zero-JS spam bot trapping and `workflow_actions` dynamic state machine bars.

### Options Available
- `<name>`: The name of the form to generate (e.g., `contact`, `student_registration`).
- `--app=<appname>`: (Optional) Target application context. Defaults to active context or `default`.
- `--yaml`: (Optional) Explicitly generate a declarative YAML definition (`etc/apps/<app>/forms/<name>.yml`). This is the default.
- `--php`: (Optional) Generate a programmatic PHP form class (`src/<app>/forms/class.<FormName>.php`).

### Under the Hood Activity
- Resolves application directory structure (`etc/apps/<app>/forms/` for YAML, `src/<app>/forms/` for PHP).
- Generates fully annotated, turnkey declarative YAML definitions or PHP builder classes.
- Registers generated file metadata in `__spp_file_props.yml`.
