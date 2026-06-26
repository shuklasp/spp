# NAME
`make:twig` - Scaffold a new Twig template (Drishyam Paradigm)

# SYNOPSIS
`php spp.php make:twig <ViewName> [--name=<ViewName>]`

# PURPOSE
The `make:twig` command scaffolds a `.twig` View template file explicitly formatted to leverage the Drishyam rendering paradigm, seamlessly bridging enterprise Twig syntax with the SPP Framework context ecosystem.

# OPTIONS AVAILABLE
- `<ViewName>` or `--name=<ViewName>` (string, required): The core filename for the view. If it lacks a `.twig` extension, it will be automatically appended.

# UNDER THE HOOD ACTIVITY
When executed, it derives the application target context and resolves the `resources/views/{context}/` directory. 
It writes a hardcoded HEREDOC string heavily styled with custom CSS (`drishyam-container`, `.twig-hero` gradient) utilizing traditional Twig syntax rules like `{% extends "layouts/app.twig" %}`, `{% block title %}`, and `{% block content %}`. It replaces structural tokens (`{{VIEW_NAME}}`, `{{CONTEXT}}`) dynamically prior to forcefully writing the file to disk via `file_put_contents`.

# EXAMPLES
**1. Scaffold a Twig profile view:**
```bash
php spp.php make:twig UserProfile
```
