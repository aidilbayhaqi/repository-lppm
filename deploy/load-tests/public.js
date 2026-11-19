import http from 'k6/http';
import { check, sleep } from 'k6';

// Run only against your own staging environment with representative synthetic data.
export const options = {
    stages: [{ duration: '30s', target: 10 }, { duration: '2m', target: 25 }, { duration: '30s', target: 0 }],
    thresholds: { http_req_failed: ['rate<0.01'], 'http_req_duration{route:search}': ['p(95)<1000'] },
};
const base = __ENV.BASE_URL;
if (!base || !base.startsWith('https://')) throw new Error('Set BASE_URL to your staging HTTPS origin.');
export default function () {
    const response = http.get(`${base}/search?q=${encodeURIComponent(__ENV.SEARCH_TERM || 'pendidikan')}`, { tags: { route: 'search' } });
    check(response, { 'search returns 200': (r) => r.status === 200 });
    // Respect the production per-IP application limiter; use approved staging limits for capacity runs.
    sleep(20);
}
