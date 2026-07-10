# SPP Integrations Module (Data Mesh)

Welcome to the **SPP Integrations Module**! If you are completely new to SPP or integration concepts, you are in the right place. This module allows your SPP application to natively "talk" to other completely different applications (like WordPress, Moodle, or Magento) as if they were a single unified system.

## Foundational Concepts

### What problem does this solve?
Imagine you have a main corporate website built on SPP, a blog built on WordPress, and a training center built on Moodle. If a user registers on your SPP site, they normally wouldn't exist in WordPress or Moodle. They would have to register three separate times! 

The SPP Integrations Module solves this. It acts as a **Data Mesh**—a central nervous system. When someone registers on one app, SPP instantly duplicates that user across all other apps automatically in the background.

### Zero-Touch Architecture
The most powerful feature of this module is **Zero-Touch**. You do *not* need to install complex "SPP Sync" plugins into WordPress or Moodle. Instead, SPP achieves integration in three ways:
1.  **Native Bootstrapping (Local Path)**: SPP temporarily boots up the background engine of WordPress, injects the user directly, and shuts it down.
2.  **HTTP API (Cloud Apps)**: SPP uses standard REST APIs (like for Discourse or Magento).
3.  **Database Triggers**: SPP hooks directly into the database to watch for changes.

## Lifecycle & Architecture

How does data actually move around? Here is the lifecycle of an integration event:

1.  **The Trigger**: A user updates their profile. This can be intercepted by the `IntegrationGateway` (if the app runs on your server) or sent as a webhook (if the app is in the cloud).
2.  **The Mesh Orchestrator**: The data hits the `IntegrationFactory`.
3.  **The Saga Job**: SPP packages the data into an `IntegrationSyncJob` and puts it in a background queue (`DagJobOrchestrator`). This ensures that if Moodle is currently crashing, SPP will politely wait and try again later instead of breaking the user's screen.
4.  **CQRS Event Sourcing**: A snapshot of the data is permanently saved in the `EventStore`. This creates an immutable history, allowing you to "time-travel" back to fix mistakes!
5.  **The Destination**: The background job wakes up, looks at the driver (e.g., `MagentoDriver`), and pushes the user data in natively.

## The AI Ingress Hub
Sometimes cloud apps send messy, proprietary JSON data that SPP doesn't understand. The `IntegrationWebhookController` uses SPP's native AI to "read" the data, figure out who the user is, format it correctly, and pass it to the Mesh Orchestrator without you writing any custom code!

## Impact of Modifications
*If you are upgrading from an older version of SPP*: Note that synchronous driver calls (`$driver->syncUser()`) in web loops are now deprecated. Always use `IntegrationFactory::broadcastUserSync()`, which safely routes through the new DAG Queue Orchestrator and EventStore.
