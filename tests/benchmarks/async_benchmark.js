import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '10s', target: 50 }, // Ramp up to 50 virtual users
        { duration: '30s', target: 50 }, // Stay at 50 virtual users for 30s
        { duration: '10s', target: 0 },  // Ramp down to 0
    ],
    thresholds: {
        http_req_duration: ['p(95)<100'], // 95% of requests must complete below 100ms
        http_req_failed: ['rate<0.01'],   // Error rate must be less than 1%
    },
};

export default function () {
    const url = __ENV.SPP_ASYNC_URL || 'http://127.0.0.1:8080/';
    
    const res = http.get(url, {
        headers: { 'X-SPP-Benchmark': 'k6' },
    });

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time OK': (r) => r.timings.duration < 200,
    });

    sleep(0.1);
}
