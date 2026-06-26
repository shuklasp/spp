# NAME
`queue:work` - Starts a worker loop to process background jobs from the queue

# SYNOPSIS
`php spp.php queue:work`

# PURPOSE
The `queue:work` command instantiates a long-running, blocking daemon process that continuously polls the application's queue for pending background jobs. As jobs become available, the worker executes their logic and safely removes them from the queue upon successful completion. It is a critical component for processing delayed tasks such as sending emails, batch data crunching, or asynchronous API calls.

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
When executed, the command enters an infinite `while (true)` loop, transforming the PHP script into a persistent daemon worker. At the start of each iteration, it calls `\SPP\Core\Queue::pop()`. This static method interacts with the underlying storage (usually the database `spp_jobs` table) to retrieve the next available, unprocessed job whose `available_at` timestamp is less than or equal to the current time.

If `Queue::pop()` returns a valid job array, the command extracts the `id` string and the instantiated `job` object (which is typically unserialized from the payload by the Queue core). It echoes a timestamped log to standard output indicating that the specific Job ID and job class type are being processed.

The actual execution occurs inside a `try-catch (\Throwable $e)` block to prevent a single faulty job from crashing the entire worker daemon. Inside the `try`, the command invokes the `$job->handle()` method, triggering the business logic defined by the developer. If `handle()` executes without throwing an exception, the command immediately calls `\SPP\Core\Queue::complete($id)` to permanently delete or mark the job as finished in the storage layer, preventing duplicate processing.

If an exception is thrown during `handle()`, the `catch` block traps it and logs a timestamped failure message containing the error details to the terminal. Currently, failed jobs remain in the system based on the core Queue implementation (often left in the table or moved to a failed jobs table, though this loop simply logs and moves on).

If `Queue::pop()` returns `null` (meaning the queue is currently empty), the loop executes a `sleep(2)` command. This 2-second pause is crucial for resource management, preventing the `while(true)` loop from consuming 100% CPU usage while idly polling the empty database.

# EXAMPLES
Start the queue worker daemon:
```bash
php spp.php queue:work
```
