# NAME

`spp live:trigger` - Push a live event to clients

# SYNOPSIS

`php spp.php live:trigger --channel=<channel> --event=<event> [--payload=<json>]`

# PURPOSE

The `live:trigger` command allows backend operators to artificially inject a real-time event into the SPPLive broadcasting system. This is primarily used for testing WebSocket listeners or triggering frontend client refreshes manually.

# OPTIONS AVAILABLE

- `--channel=<channel>`: The namespace or channel identifier to broadcast to.
- `--event=<event>`: The event string identifier.
- `--payload=<json>`: (Optional) A JSON-encoded string representing the data payload accompanying the event.

# UNDER THE HOOD ACTIVITY

`LiveTriggerCommand` extracts parameters matching `--channel=`, `--event=`, and `--payload=`. It strictly requires both channel and event definitions.
It checks if the SPPLive framework is active by confirming `class_exists('\\SPPMod\\SPPLive\\SPPLive')`. It also verifies if the `broadcast` static method is implemented on the class. 
If the prerequisites are met, it decodes the JSON payload (or defaults to an empty array) and triggers `\SPPMod\SPPLive\SPPLive::broadcast($channel, $event, $data)`. This method handles pushing the data packet into the associated messaging broker (e.g., Redis pub/sub) or directly to active socket connections.

# EXAMPLES

**Trigger a simple system notification event:**
```bash
php spp.php live:trigger --channel=system --event=NotificationEvent --payload='{"message": "Maintenance starting in 5 minutes"}'
```
