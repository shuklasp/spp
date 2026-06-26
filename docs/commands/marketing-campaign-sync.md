# NAME
**marketing:campaign:sync** - Synchronize marketing campaigns/templates with external CRMs

# SYNOPSIS
`php spp.php marketing:campaign:sync`

# PURPOSE
This command triggers the synchronization of local marketing campaigns and templates with integrated external Customer Relationship Management (CRM) systems.

# OPTIONS AVAILABLE
This command currently takes no options.

# UNDER THE HOOD ACTIVITY
Upon execution, the command verifies the availability of the Marketing module by checking if the `\SPPMod\Marketing\Marketing` class is loaded in the environment. If the class exists, it mimics a synchronization process by outputting a stub success message. If the module is not found, the command terminates with a message indicating that the Marketing module is inactive. In a fully implemented state, this command would interface with CRM APIs to pull or push template data, audiences, and campaign statuses.

# EXAMPLES
Run the synchronization process:
`php spp.php marketing:campaign:sync`
