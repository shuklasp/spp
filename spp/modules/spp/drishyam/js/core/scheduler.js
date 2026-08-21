/**
 * SPP-UX Scheduler — Batched Asynchronous Update Queue
 * 
 * Coalesces multiple state changes into a single DOM update pass,
 * preventing redundant re-renders when setState() is called multiple times
 * synchronously. Inspired by React's batched update model.
 * 
 * @module core/scheduler
 * @version 13.0.0
 */

/** @type {Set<Function|{update: Function}>} Pending jobs */
const _queue = new Set();

/** @type {boolean} Guard against recursive flushes */
let _isFlushing = false;

/** @type {number} Nesting depth for batch() calls */
let _batchDepth = 0;

/** @type {Promise<void>|null} Cached microtask flush promise */
let _flushPromise = null;

/**
 * Enqueue a job for deferred execution.
 * 
 * If not inside a batch, schedules a microtask to flush the queue.
 * If inside a batch, the job is held until endBatch() triggers the flush.
 * 
 * @param {Function|{update: Function}} job - An effect function or component instance
 * @example
 * enqueue(myComponent);        // component.update() called on next microtask
 * enqueue(() => doSomething()); // function called on next microtask
 */
export function enqueue(job) {
    _queue.add(job);
    if (_batchDepth === 0 && !_flushPromise) {
        _flushPromise = Promise.resolve().then(flush);
    }
}

/**
 * Process all pending jobs in the queue.
 * 
 * Jobs that are functions are called directly.
 * Jobs with an `update()` method have that method called.
 * Errors in individual jobs are caught and logged, allowing
 * remaining jobs to execute.
 */
export function flush() {
    if (_isFlushing) return;
    _isFlushing = true;
    _flushPromise = null;

    // Snapshot the queue — jobs enqueued during flush get a new cycle
    const jobs = Array.from(_queue);
    _queue.clear();

    for (const job of jobs) {
        try {
            if (typeof job === 'function') {
                job();
            } else if (job && typeof job.update === 'function') {
                job.update();
            }
        } catch (err) {
            console.error('[SPPUX Scheduler] Job execution error:', err);
        }
    }

    _isFlushing = false;

    // If new jobs were enqueued during flush, process them
    if (_queue.size > 0) {
        flush();
    }
}

/**
 * Synchronously flush all pending updates, ignoring batch depth.
 * 
 * Use sparingly — primarily for backward compatibility with code
 * that expects setState() to update the DOM synchronously.
 */
export function forceFlush() {
    const savedBatch = _batchDepth;
    _batchDepth = 0;
    flush();
    _batchDepth = savedBatch;
}

/**
 * Begin a batch context. Holds all enqueued updates until endBatch().
 * Supports nesting — only the outermost endBatch() triggers flush.
 */
export function startBatch() {
    _batchDepth++;
}

/**
 * End a batch context. When the outermost batch ends, flushes all
 * accumulated updates in one pass.
 */
export function endBatch() {
    _batchDepth = Math.max(0, _batchDepth - 1);
    if (_batchDepth === 0 && _queue.size > 0) {
        flush();
    }
}

/**
 * Check if the scheduler is currently in the middle of a flush.
 * @returns {boolean}
 */
export function isFlushing() {
    return _isFlushing;
}
