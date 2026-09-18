import { describe, expect, it } from 'vitest';
import { runCaveats } from '@/Composables/useRunCaveats';

const breaking = (caveats) => caveats.find((caveat) => caveat.key === 'breaking-point');

const brokenAt = (key, breaking, environment = {}, http = {}) => runCaveats({
    environment,
    http: {
        ...http,
        routes: {
            [key]: {
                curve: [{ concurrency: 1 }, { concurrency: 50 }, { concurrency: 199 }],
                breaking_point: breaking?.concurrency ?? 199,
                breaking,
            },
        },
    },
});

// The sentence is built from what the route answered, because "stopped at 199
// connections" names a symptom and each answer is a different repair.
describe('the breaking-point caveat', () => {
    it('says what a DB read 503 storm is and where the cause was logged', () => {
        const caveat = breaking(brokenAt('db_read', { concurrency: 199, status_codes: { 200: 12000, 503: 8000 }, errors: {} }));

        expect(caveat.title).toBe('DB read broke at 199 connections');
        expect(caveat.detail).toBe('It served every request at 50 connections. At 199, 40% of requests came back 503, which is the answer this route gives when its database query fails.');
        expect(caveat.fix).toContain('storage/logs/laravel.log');
    });

    it('blames the app container, not the database, when it ran out of ports', () => {
        const caveat = breaking(brokenAt('db_read', { concurrency: 199, status_codes: { 200: 3800, 503: 16200 }, errors: {}, causes: ['ports_exhausted'] }));

        expect(caveat.detail).toBe('It served every request at 50 connections. At 199, 81% of requests came back 503 because this server ran out of ports to open database connections with. Every request on this route opens and closes one, and a closed connection holds its port for a minute.');
        expect(caveat.fix).toContain('net.ipv4.tcp_tw_reuse');
        expect(caveat.fix).not.toContain('laravel.log');
    });

    it('sizes the database limit from the worker count when the database refused', () => {
        const caveat = breaking(brokenAt('db_read', { concurrency: 199, status_codes: { 503: 100 }, errors: {}, causes: ['connections_exhausted'] }, {}, { workers: 1810 }));

        expect(caveat.detail).toContain('because the database refused connections past its own limit');
        expect(caveat.fix).toBe("Raise the database's connection limit above the worker count (1,810), then run again.");
    });

    it('explains the first cause seen when a level failed in more than one way', () => {
        const caveat = breaking(brokenAt('db_read', { concurrency: 199, status_codes: { 503: 100 }, errors: {}, causes: ['unreachable', 'ports_exhausted'] }));

        expect(caveat.detail).toContain('could not be reached at all');
    });

    it('names the front end when it gave up on PHP', () => {
        const caveat = breaking(brokenAt('io', { concurrency: 199, status_codes: { 502: 900, 200: 100 }, errors: {} }, { php: { runtime: { front_end: 'nginx' } } }));

        expect(caveat.detail).toContain('90% of requests came back 502, which is nginx giving up on PHP');
        expect(caveat.fix).toContain("nginx's error log");
    });

    it('quotes a transport error that came back before any status', () => {
        const caveat = breaking(brokenAt('static', { concurrency: 199, status_codes: { 200: 20 }, errors: { 'connection refused': 80 } }));

        expect(caveat.detail).toContain('80% of requests failed before any answer came back: "connection refused"');
    });

    it('passes the generator\'s reason through when the level was never measured', () => {
        const caveat = breaking(brokenAt('io', { concurrency: 199, failure: 'oha exited with status 1', status_codes: {}, errors: {} }));

        expect(caveat.detail).toBe('It served every request at 50 connections. The generator could not measure 199: oha exited with status 1.');
    });

    it('falls back to the number alone on a run recorded before the answers were kept', () => {
        const caveat = breaking(brokenAt('db_read', null));

        expect(caveat.title).toBe('DB read broke at 199 connections');
        expect(caveat.detail).toContain('did not keep what the route answered');
    });
});
