# NAME
**marketing:campaign:dispatch** - Dispatch a marketing campaign manually

# SYNOPSIS
`php spp.php marketing:campaign:dispatch --id=<campaign_id>`

# PURPOSE
This command allows system administrators or automated schedulers to manually trigger the dispatch process for a specific marketing campaign.

# OPTIONS AVAILABLE
- `--id=<campaign_id>` : **Required.** The unique identifier of the marketing campaign to be dispatched.

# UNDER THE HOOD ACTIVITY
When executed, the command parses the passed arguments to extract the `--id=` flag. It then verifies the existence of the `\SPPMod\Marketing\Marketing` class to ensure the Marketing module is active within the current SPP application context. Currently, the implementation is a stub. If the module is loaded, it outputs a success message indicating the stub dispatch process has completed. Otherwise, it reports that the marketing module is inactive. Future implementations are expected to initiate queued jobs, interface with external mailers, or update campaign database records.

# EXAMPLES
Dispatch a campaign with ID 1042:
`php spp.php marketing:campaign:dispatch --id=1042`
