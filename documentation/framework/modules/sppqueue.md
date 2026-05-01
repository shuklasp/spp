# Core Module: SppQueue

The SppQueue module provides a cross-application, distributed task management system. It allows applications to offload heavy workloads to background workers using the framework's **Polyglot Registry** as the transport layer.

---

## 1. Basic Philosophy
Modern web applications must remain responsive. Heavy tasks like sending thousands of emails, generating large PDFs, or processing AI models should never happen during the main web request. 

SppQueue allows you to **Dispatch** a job now and have a worker **Handle** it later.

---

## 2. Distributed Architecture
Unlike traditional queues that require Redis or RabbitMQ, SppQueue uses the **Shared Registry** (`__shared=>queue`). 

1.  **PHP App**: Pushes a job JSON into the shared filesystem.
2.  **Worker (Go/Node/Python)**: Sees the new file/entry and processes it.
3.  **Synchronicity**: The `Registry` ensures atomic updates to the queue state.

---

## 3. API & Usage

### Dispatching a Job
```php
use \SPPMod\SPPQueue\SppQueue;

SppQueue::push(\App\Jobs\ProcessReport::class, [
    'report_id' => 123,
    'format' => 'pdf'
]);
```

### The Job Class
```php
namespace App\Jobs;

class ProcessReport {
    public function handle(array $data) {
        $id = $data['report_id'];
        // Logic to generate report...
    }
}
```

---

## 4. Worker Execution
A worker can be started via a simple PHP script or a cron job:
```php
while (true) {
    \SPPMod\SPPQueue\SppQueue::work();
    sleep(1);
}
```

---
[Back to Modules Index](index.md)
