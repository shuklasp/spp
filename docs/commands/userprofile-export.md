# NAME
**userprofile:export** - Export user profile data for compliance/GDPR

# SYNOPSIS
`php spp.php userprofile:export --user=<user_id>`

# PURPOSE
Facilitates compliance with data privacy regulations (like GDPR) by exporting all extended metadata and profile information associated with a specific user account.

# OPTIONS AVAILABLE
- `--user=<user_id>` : **Required.** The ID of the user whose profile data will be exported.

# UNDER THE HOOD ACTIVITY
The command reads the `--user=` parameter from the CLI arguments. Currently, the implementation is a foundational stub designed for the `SPPUserProfile` module. When invoked, it simply prints a message confirming the export sequence has commenced for the given user ID, followed by an "Export complete (Stub)." message. Future updates will hook into the database to serialize user relationships, audit logs, and extended profile attributes into a downloadable format (e.g., JSON or CSV).

# EXAMPLES
Export profile data for user 42:
`php spp.php userprofile:export --user=42`
