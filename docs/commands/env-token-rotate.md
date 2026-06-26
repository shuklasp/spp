# env:token:rotate

## NAME
env:token:rotate - Rotate the system deployment token

## SYNOPSIS
`php spp.php env:token:rotate [--app=<app_name>]`

## PURPOSE
Generates and enforces a brand new cryptographically secure hexadecimal payload into the system security store, deprecating the former deployment token.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Context boundary setting.

## UNDER THE HOOD ACTIVITY
Within the isolated context constraint, it invokes PHP's `random_bytes(16)` and wraps it in `bin2hex()` to securely forge a 32-character key. It establishes a dedicated database socket directly to the `'sys'` table space under the `'security'` cluster via `\SPPMod\SPPXDB\SPP_XDB`. It manipulates the XDB engine to formally mark the `['value']` field as natively encrypted via `setEncryptedFields()`. It issues an XPath style query (`//row[key = 'deployment_token']`) to locate an existing token payload. Relying on an upsert-style decision block, it forces an `update()` if the node is confirmed, or triggers an `insert()` of a fresh row, outputting the rotated key string.

## EXAMPLES
```bash
php spp.php env:token:rotate
```
