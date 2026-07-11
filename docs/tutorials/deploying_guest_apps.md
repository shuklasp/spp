# Tutorial: Deploying a Guest App into the SPP WebOS

This step-by-step tutorial is written for absolute beginners. We will take an existing, legacy PHP application (like a 5-year-old WordPress blog) and seamlessly import it into the SPP Web Operating System.

By the end of this tutorial, your legacy application will automatically sync its database with SPP, store its files on the Cloud VFS, and participate in the AI Agent Swarm—**without rewriting a single line of WordPress code!**

## Step 1: The Physical Relocation
First, we need to move the physical files of the legacy application into the SPP workspace.
Because this is a **Guest App** (it has its own `index.php`), we place it in the `/public` folder.

1. Locate your SPP root directory.
2. Drag and drop your existing `wordpress` folder into `public/`. 
   *Your path should now be: `/public/wordpress`*

## Step 2: OS Registration (The CLI Installer)
Now, we must tell the SPP Kernel that a new executable has been added to the OS.
Open your terminal and run the SPP Integration Installer command:

```bash
php spp.php integration:install wordpress /wordpress --isolation=physical
```

**What did this do?**
*   It bypassed the SPP Master Router for the `/wordpress` URL.
*   It added the `wordpress:wordpress` identifier to the WebOS IAM Registry.

## Step 3: The Virtual Database Cutover (The Magic Step)
Now we wire the WordPress database to flow through the SPP Hypervisor (`VirtualPDO`).

1. Export your existing WordPress MySQL database (using phpMyAdmin or `mysqldump`).
2. Import that database into the SPP Database Server (name it `spp_guest_wordpress`).
3. Open your `wp-config.php` file.
4. Locate the `DB_HOST` constant and change it to the WebOS dummy connection string:
   ```php
   define('DB_HOST', 'spp://kernel');
   ```

**What did this do?**
The second WordPress tries to execute a SQL query, the SPP `VirtualPDO` intercepts it! It fast-paths all `SELECT` queries for zero-lag reading, and silently routes all `UPDATE/INSERT` queries to the SPP XDB Master Index and CDC Pipeline!

## Step 4: Securing Secrets (The Vault)
Never leave API keys in plain text! Let's utilize the SPP Zero-Touch Key Vault.

1. Delete any Stripe API keys or PayPal secrets from your `wp-config.php`.
2. Instead, instruct WordPress to load an `.env` file from the Virtual File System:
   ```php
   // Inside wp-config.php
   $env = file_get_contents('spp://secrets/.env');
   // ... parse the string and load it ...
   ```

**What did this do?**
There is no physical `.env` file! The `spp://` VFS intercepts the `file_get_contents` request, asks the SPP Vault for your encrypted API keys, decrypts them in RAM, and feeds them back to WordPress as a dynamic string. Absolute military-grade security!

## Step 5: CPU & RAM Quotas (Fault Isolation)
Finally, we want to ensure that if a buggy WordPress plugin goes haywire, it doesn't crash the rest of our SPP server.

Run the App Quota command:
```bash
php spp.php app:quota wordpress:wordpress --ram=128M --cpu=10s
```

**What did this do?**
The SPP `ResourceManager` will now strictly monitor this instance. If WordPress attempts to consume more than 128MB of RAM or execute for longer than 10 seconds, SPP will forcefully kill the thread and throw a Kernel Panic, protecting your server.

**Congratulations!** You have successfully migrated a monolithic legacy application into an Enterprise Web Operating System.
