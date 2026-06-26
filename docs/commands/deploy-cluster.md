# NAME
**deploy:cluster** - Deploy to a multi-server cluster sequentially

# SYNOPSIS
`php spp.php deploy:cluster <cluster_name> [--force] [-y] [other_push_flags]`

# PURPOSE
Automates the deployment process across an entire cluster of multiple remote nodes defined in a central configuration file. This guarantees that all instances in a load-balanced pool receive the updated application artifact.

# OPTIONS AVAILABLE
- `<cluster_name>` : **Required.** The alias of the cluster group defined in `.sppdeploy.yml`.
- `--force` or `-y` : **Optional.** Bypasses the interactive manual confirmation prompt.
- `[other_push_flags]` : **Optional.** Any additional flags (like `--no-db`) are transparently passed down to the underlying `deploy:push` command for each node.

# UNDER THE HOOD ACTIVITY
The command reads and parses the YAML configuration file located at `SPP_BASE_DIR/.sppdeploy.yml`. It validates that the `<cluster_name>` exists and maps to an array of remote URIs (nodes). Unless `--force` or `-y` is present, it stalls execution and requires the user to input 'Y' on standard input (`php://stdin`) before proceeding. Once confirmed, it instantiates the `DeployPushCommand` logic in memory. It iterates through the array of node URIs, formatting an arguments array (passing `--force` automatically alongside any custom user flags), and executes the push command serially for each node. If the deployment throws an Exception on any single node, the loop breaks instantly, halting the remainder of the cluster rollout to prevent inconsistent state distribution. Finally, it prints a summary of successfully updated nodes vs the total expected pool.

# EXAMPLES
Deploy to the production cluster, skipping prompts:
`php spp.php deploy:cluster production --force`

Deploy to the web-workers cluster without database updates:
`php spp.php deploy:cluster web-workers --no-db`
