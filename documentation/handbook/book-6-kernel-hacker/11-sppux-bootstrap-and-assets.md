# Book 6 Chapter 11 — SPPUX Bootstrap and Asset Resolution Internals

Trace the current SPPUX facade:

```text
application configuration
→ runtime/path resolution
→ application-aware URI resolution
→ asset/runtime loading
→ mount/bootstrap
→ browser runtime
```

## Lab

Pick one SPPUX asset and trace it from configuration to the URL that reaches the browser.

## Rule

Do not assume a static asset path is globally valid in a multi-application environment. Verify how the current implementation derives its URI.