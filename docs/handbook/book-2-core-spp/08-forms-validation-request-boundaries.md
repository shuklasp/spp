# Book 2 Chapter 8 — Forms, Validation, and Request Boundaries

## 1. Why a form is more than HTML

A form is an input boundary between an external client and the application.

The browser can send:

```text
missing fields
extra fields
wrong types
unexpected values
malicious values
```

A framework form layer can organize parsing, validation, errors, and rendering.

## 2. Validation layers

Keep three ideas separate:

```text
Input validation
      ↓
Business validation
      ↓
Persistence constraints
```

A form can tell the user that a required field is empty. A business service may still need to reject an invalid state. The database can provide a final persistence constraint where appropriate.

## 3. SPP presentation boundary

The current SPP framework provides forms and validation facilities that integrate with the application/view layer.

Use the source-backed form APIs rather than assuming a Laravel/Symfony form API is equivalent.

## 4. Hands-on lab

Build a Task Desk task form containing:

- title;
- description;
- priority;
- due date.

Add validation for missing and malformed input.

Then add a business rule in `TaskService` and show why it should not be implemented only in the form.

## 5. Failure lab

Bypass the form and submit invalid data directly to the server-side operation.

The application must remain correct because the server boundary cannot rely on the browser behaving correctly.

## 6. Kernel Hacker

Trace input from request boundary to form object, validation result, service call, and rendered errors.

## Checkpoint

> **A form organizes user input; it does not replace server-side business validation or persistence constraints.**
