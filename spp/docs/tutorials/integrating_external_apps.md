# Tutorial: Integrating External Apps in 5 Minutes

Welcome! If you have never integrated an external application before, don't worry. SPP's Data Mesh makes it incredibly easy. In this tutorial, we will learn how to seamlessly install WordPress and pull its data into our SPP site.

## Step 1: Scaffold the Application
First, we need to create a physical folder for WordPress to live in and tell the SPP Router to let WordPress run natively.

Open your terminal and run the new SPP Install Command:
```bash
php spp.php integration:install wordpress /blog
```

**What just happened?**
1. SPP created a directory at `public/blog`.
2. It updated `etc/routes.yml` to bypass SPP routing for `/blog/*`.
3. It registered the `WordPressDriver` with the absolute path for Native Bootstrapping.

## Step 2: Install the App
Now, you can download the latest WordPress `.zip`, extract it into `public/blog`, and run the famous 5-minute WordPress web installer. 

Because SPP is acting as a router bypass, WordPress will think it's running alone on a dedicated server!

## Step 3: Trigger a Sync
Let's see the Data Mesh in action! 
1. Create a brand new user account inside your main SPP application.
2. In the background, SPP will instantly package this user data, attach a W3C Trace Telemetry ID to it, and send it to the DAG Job Queue.
3. The queue worker will silently execute the `WordPressDriver->syncUser()` method, pushing the user natively into the WordPress database.

Check your WordPress admin panel—the user is instantly there!

## Step 4: Data Federation (The Magic Trick)
Now, what if you want to display the latest 3 WordPress blog posts on your SPP homepage?

You do **not** need to write complicated API queries. Just use the SPP Federation Controller!

Inside your SPP HTML view, you can use HTMX to automatically pull the data:
```html
<div class="blog-feed" hx-get="/api/integration/federated-block?app=wordpress&endpoint=posts&template=card" hx-trigger="load">
   Loading latest posts...
</div>
```

**What happens here?**
SPP fetches the raw posts from WordPress, wraps them nicely in your `partials/integration_card.html` template, and returns pure HTML to the browser!

## Advanced: The AI Webhook
If you decide to use a cloud app (like Coursera) instead of a local app like WordPress, point Coursera's webhooks to:
`https://yoursite.com/api/integration/webhook/ingress`

SPP's built-in AI will automatically read the Coursera JSON payload, figure out what it means, and sync it to the rest of your mesh!
