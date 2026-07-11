# Novice Guide to Real-Time Streaming on Shared Hosting

Welcome to SPP! If you are building an application with real-time UI updates (like a chat app, live dashboard, or collaborative document editor), you are likely using **Turbo Streams** or **Server-Sent Events (SSE)**.

Normally, these streaming connections require the PHP server to keep a connection open forever (`while(true) { ... }`). 
However, if you are deploying your SPP application to a strict **Shared Hosting Environment**, keeping connections open forever will cause your site to crash with a **503 Service Unavailable** error!

Why? Because Shared Hosting limits the number of PHP-FPM "Workers" you can have (often to just 5 or 10). If 10 users open your chat app, all 10 workers are locked open forever, and your entire website goes offline for everyone else.

To solve this, SPP has an incredible built-in feature: **The Streaming Polyfill Engine**.

## Foundational Concepts

### What is the Streaming Polyfill Engine?
The SPP `ServerDetector` constantly monitors your hosting environment. If it detects that you are running on a restrictive Shared Host (or if you manually enforce it), it will **intercept all infinite Turbo Stream connections and automatically degrade them into short-polling connections**.

Instead of holding the worker hostage forever, SPP will instantly throw a `StreamingDegradationException`, sending one chunk of real-time data to the browser and immediately closing the connection. 

### Why is this magical?
The browser, noticing the stream was abruptly closed, will automatically reconnect a few seconds later. SPP handles the request, sends another chunk of data, and closes it again. 
This invisible "short-polling" behavior frees up your PHP-FPM workers instantly! You get real-time UI updates without ever exhausting your server's concurrency limits.

## Lifecycle & Architecture

1. **The Request Arrives:** A user's browser opens an SSE/Turbo Stream connection to an SPP `StreamDispatcher`.
2. **Detection:** The `ServerDetector` analyzes the environment. If `security.streaming.force_polling` is enabled, it flags the request for degradation.
3. **Execution & Ejection:** The `StreamDispatcher` enters its infinite `while(true)` loop. It generates the first piece of HTML and sends it.
4. **The Degradation Exception:** Immediately after sending the first chunk, the `ServerDetector` triggers a `StreamingDegradationException`.
5. **Clean Exit:** The exception forcefully breaks the infinite loop. The `StreamDispatcher` catches the exception, gracefully closes the output buffers, and ends the PHP script cleanly. The PHP-FPM worker is instantly freed.
6. **Browser Reconnect:** The browser, natively designed to handle dropped SSE connections, automatically reconnects, creating a seamless real-time illusion for the user!

## Step-by-Step Tutorial

How do you enable this magical protection for your shared hosting deployment?

### Step 1: Open Your Environment Configuration
Locate your SPP environment configuration file (usually in `.env` or `etc/config.php` depending on your setup).

### Step 2: Enable Forced Polling
Add the following configuration directive to instruct the `ServerDetector` to forcibly degrade all infinite streams:

```php
// In etc/config.php
return [
    'security' => [
        'streaming' => [
            'force_polling' => true,
        ],
    ],
];
```

### Step 3: Write Your Code Normally!
You don't have to change a single line of your actual application code! Write your `StreamDispatcher` assuming you have an infinite connection. SPP will handle the degradation completely behind the scenes.

```php
// Your code remains elegant and infinite:
while (true) {
    $data = $this->fetchLatestChatMessages();
    if ($data) {
        $this->streamHtml($data);
    }
    sleep(1); 
    // ^ SPP will intercept this and trigger the Degradation Exception!
}
```

By understanding and utilizing SPP's Streaming Degradation, you get the best of both worlds: elegant, real-time code, and 100% compliance with strict, low-cost shared hosting environments!
