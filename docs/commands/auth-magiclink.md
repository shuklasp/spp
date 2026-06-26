# NAME
**auth:magiclink** - Generate a one-time passwordless Magic Link for a user

# SYNOPSIS
`php spp.php auth:magiclink <email>`

# PURPOSE
Generates a secure, passwordless authentication link (Magic Link) directly from the console to allow a specific user to securely authenticate into the system without requiring their password.

# OPTIONS AVAILABLE
- `<email>` : **Required.** The registered email address of the target user for whom the link will be generated.

# UNDER THE HOOD ACTIVITY
The command extracts the email address from the arguments and uses the `SPPDB` query builder to find the corresponding user ID in the `users` table. If the user is found, it instantiates an `SPPUser` object. It then calls `\SPPMod\SPPAuth\MagicLink::createToken($userId, 15)`, which generates a cryptographic token associated with the user, valid for 15 minutes. The CLI constructs a final login URL by pulling `app.url` from `\SPP\Config` and appending the encoded `magic_token`. This URL is printed to the console alongside the raw token for manual access or distribution.

# EXAMPLES
Generate a magic link for an administrator:
`php spp.php auth:magiclink admin@example.com`
