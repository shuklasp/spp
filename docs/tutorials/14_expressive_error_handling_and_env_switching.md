# Tutorial 14: Expressive Ignition Error Handling & Instant Environment Switching

Welcome to **Tutorial 14** of the SPP Framework! If you are a complete novice to SPP—even if you have never written a line of PHP or used a framework before—this guide will give you a complete, in-depth ("in and out") understanding of how SPP catches errors, presents beautiful developer error dashboards, and allows you to toggle between Development and Production modes instantly from your terminal.

---

## 1. Foundational Concepts

### What is an Exception or 500 Error?
When you write code, things sometimes go wrong. You might misspell a variable name, attempt to open a database table that doesn't exist, or make a syntax mistake in a template file. When PHP encounters an issue it cannot resolve, it throws an **Exception** or a **Fatal Error**, resulting in an HTTP 500 (Internal Server Error) status code.

### The Two Operational Paradigms (Dev vs. Prod)
How an error is displayed depends entirely on who is looking at the screen:

1. **Developer Mode (`dev` / `SPP_DEBUG = true`)**:
   - As the creator of the application, you need to know *exactly* what went wrong, what file it happened in, and on what exact line number.
   - You need complete transparency: stack traces, variable inspection, and actionable hints to fix the problem fast.

2. **Production Mode (`prod` / `SPP_DEBUG = false`)**:
   - When your application is live on the public internet, regular users and potential attackers are viewing your site.
   - Exposing a stack trace or database query to the public is a severe security vulnerability.
   - In Production mode, the framework suppresses all internal details and displays a clean, elegant, user-friendly "Something went wrong" page while logging the real error to a secure file (`var/log/app.log`).

---

## 2. The Ignition-Style Expressive Error Dashboard

To make debugging an absolute joy, SPP provides a world-class, premium **Ignition-style developer error dashboard** (`spp/core/error_template.php`).

### Stunning Aesthetics & Architecture
When an error occurs in Development mode, SPP does not show a plain white screen with text. Instead, it renders a beautiful dark-mode interface featuring:
- **Hero Exception Banner**: Immediately highlights the exact Exception Class, Error Message, File Path, and Line Number in vibrant colors.
- **Interactive Tabbed Navigation**:
  - 🔥 **Stack Trace & Snippets**: Displays the lineage of function calls that led to the error. Clicking on any stack frame instantly updates the active code snippet box, highlighting the exact line of code where execution paused!
  - 💡 **Actionable AI Solution**: An embedded expert system that parses your error message and provides a recommended, copy-pasteable terminal command or solution (e.g. clearing the cache, running migrations, or checking syntax).
  - 🌍 **Request & Routing Context**: Displays all HTTP request parameters, active app context (`Scheduler::getContext()`), query strings (`$_GET`), and form bodies (`$_POST`).
  - 🛡️ **Environment & CLI Switch**: Details your PHP runtime version, SPP framework version, and explains how to toggle environment modes.

### Zero Inline HTML Literals Rule
In accordance with SPP's strict architectural guidelines, the core error handler (`class.spperror.php`) contains zero inline HTML literal strings. It cleanly delegates rendering to the external standalone `error_template.php` file.

---

## 3. Step-by-Step Tutorial: Switching Environments via CLI

To switch between these two error handling modes, you don't need to hunt down configuration files manually. SPP provides a dedicated, high-speed CLI command: `env:mode`.

### Step 1: Open Your Terminal
Navigate to the root directory of your SPP workspace (e.g. `c:/projects/apache/school1`).

### Step 2: Switch to Developer Mode
When you are building features and want to see the expressive Ignition error dashboard, run:
```bash
php spp/spp.php env:mode dev
```
**What happens under the hood?**
The command locates `spp/etc/global-settings.yml` and updates the `debug:` directive to `true`. From this moment on, any fatal error across any app will render the premium Ignition dashboard.

### Step 3: Switch to Production Mode
Before deploying your application or when you want to verify what your end-users will see if an error occurs, run:
```bash
php spp/spp.php env:mode prod
```
**What happens under the hood?**
The command updates `debug:` to `false` in `global-settings.yml`. The Ignition dashboard is immediately deactivated, and secure, clean 500 error pages take over.

---

## 4. Summary & Best Practices

- **Always develop in `dev` mode**: Leverage the interactive stack traces and AI solutions to build robust applications rapidly.
- **Never deploy in `dev` mode**: Always run `php spp/spp.php env:mode prod` before making your site public to ensure your system architecture remains completely secure and hidden from outside observers.
