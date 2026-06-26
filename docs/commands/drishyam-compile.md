# drishyam:compile

## NAME
drishyam:compile - Pre-compile Drishyam templates for production

## SYNOPSIS
`php spp.php drishyam:compile [--app=<app_name>]`

## PURPOSE
Actively bootstraps the Drishyam rendering engine and forces a systemic pre-warming of all templates to disk cache, minimizing production latency.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Bound application context (default: 'default').

## UNDER THE HOOD ACTIVITY
It enters the bounded app context via `Scheduler::withContext()`. It validates that the `\SPPMod\Drishyam\Drishyam` engine is available and active. It retrieves the engine's singleton instance through `getInstance()`, forces a structural bootstrap routine via `boot()`, and instructs the engine to traverse and eagerly cache all reachable templates via the `preWarm()` method.

## EXAMPLES
```bash
php spp.php drishyam:compile --app=storefront
```
