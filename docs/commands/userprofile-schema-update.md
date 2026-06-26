# NAME
**userprofile:schema:update** - Sync extended user profile metadata schemas

# SYNOPSIS
`php spp.php userprofile:schema:update`

# PURPOSE
Synchronizes local database schemas or data structures required to store extended user profile metadata, ensuring the storage layer matches the application's expected field definitions.

# OPTIONS AVAILABLE
This command takes no arguments or options.

# UNDER THE HOOD ACTIVITY
Upon execution, the command verifies the presence of the `\SPPMod\SPPUserProfile\SPPUserProfile` class. If the class is found, it confirms that the User Profile module is active and outputs a mock success message indicating the schema has been synchronized. If the module is inactive, it returns an error string. In a complete implementation, this command would parse an internal configuration (e.g., a YAML or JSON schema file) and alter the underlying database tables to append or remove metadata columns dynamically.

# EXAMPLES
Sync the profile schema:
`php spp.php userprofile:schema:update`
