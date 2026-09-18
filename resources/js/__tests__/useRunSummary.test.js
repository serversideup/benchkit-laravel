import { describe, expect, it } from 'vitest';
import { formatThroughput, runDisplay } from '@/Composables/useRunSummary';

// fio bandwidth reaches the app in KB/s, as YABS writes it. A formatter that
// read it as MB/s printed a 49 MB/s disk as 48.8 GB/s.
describe('formatThroughput', () => {
    it('reads KB/s and prints MB/s', () => {
        expect(formatThroughput(50000)).toBe('49 MB/s');
        expect(formatThroughput(1024)).toBe('1 MB/s');
    });

    it('steps up to GB/s and TB/s on the same 1024 scale', () => {
        expect(formatThroughput(1024 * 1024 * 2)).toBe('2.0 GB/s');
        expect(formatThroughput(1024 ** 3 * 3)).toBe('3.0 TB/s');
    });

    it('shows a dash for nothing measured', () => {
        expect(formatThroughput(null)).toBe('—');
    });
});

const run = (routes) => ({
    stages_completed: ['http'],
    environment: {},
    benchmarks: { http: { routes } },
});

const route = (rps) => ({ throughput: { requests_per_second: rps } });

describe('runDisplay http.rps', () => {
    it('leads with JSON, the route the gallery ranks by', () => {
        const display = runDisplay(run({ static: route(3000), json: route(2000), db_read: route(1000) }));

        expect(display.http.rps).toBe(2000);
    });

    it('falls back to static, then DB read', () => {
        expect(runDisplay(run({ static: route(3000), db_read: route(1000) })).http.rps).toBe(3000);
        expect(runDisplay(run({ db_read: route(1000) })).http.rps).toBe(1000);
        expect(runDisplay(run({ io: route(9) })).http.rps).toBeNull();
    });
});
