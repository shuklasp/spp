/**
 * SPP Offline Submission Library
 * Queues failed submissions and replays them when connection returns.
 */
document.addEventListener('DOMContentLoaded', () => {
    const QUEUE_KEY = 'spp_submission_queue';
    
    // Check for online status and replay
    window.addEventListener('online', () => {
        const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
        if (queue.length > 0) {
            console.log(`SPP: Connection restored. Replaying ${queue.length} submissions...`);
            queue.forEach((item, idx) => {
                fetch(item.url, {
                    method: item.method,
                    body: item.body,
                    headers: item.headers
                }).then(() => {
                    queue.splice(idx, 1);
                    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
                    alert('An offline submission was successfully synced!');
                });
            });
        }
    });

    // Intercept forms with data-offline
    const offlineForms = document.querySelectorAll('form[data-offline]');
    offlineForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!navigator.onLine) {
                e.preventDefault();
                const formData = new FormData(form);
                const body = {};
                formData.forEach((v, k) => body[k] = v);
                
                const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
                queue.push({
                    url: form.action,
                    method: form.method,
                    body: JSON.stringify(body),
                    headers: { 'Content-Type': 'application/json' },
                    timestamp: Date.now()
                });
                localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
                alert('You are offline. Your submission has been queued and will be sent automatically when you are back online.');
            }
        });
    });
});
