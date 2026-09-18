import { describe, expect, it } from 'vitest';
import { breakingAnswer, brokenRoutes, memoryDatabase, undersizedPool, workerFootprintMb } from '@shared/run/conditions.mjs';

const perRequest = (cores, ram) => ({
    server: { cpu_cores: String(cores), ram },
    php: { runtime: { mode: 'process-per-request' } },
});

// The suggestion is handed to the user as a PHP_FPM_PM_MAX_CHILDREN value, so
// its arithmetic is the one thing here worth pinning.
describe('undersizedPool', () => {
    it('sizes the suggestion from memory: three quarters of RAM over one worker footprint', () => {
        const result = undersizedPool(perRequest(8, '4096 MB'), { workers: 4 });

        expect(result).toMatchObject({ cores: 8, workers: 4, idleCores: 4, memoryBound: true });
        expect(result.suggested).toBe(Math.floor((4096 * 0.75) / workerFootprintMb()));
    });

    it('never suggests fewer workers than cores, and falls back to cores without RAM', () => {
        expect(undersizedPool(perRequest(8, '256 MB'), { workers: 4 }).suggested).toBe(8);
        expect(undersizedPool(perRequest(8, ''), { workers: 4 })).toMatchObject({ suggested: 8, memoryBound: false });
    });

    it('is silent when the pool already covers the cores, or the runtime multiplexes', () => {
        expect(undersizedPool(perRequest(4, '4096 MB'), { workers: 4 })).toBeNull();
        expect(undersizedPool({ ...perRequest(8, '4096 MB'), php: { runtime: { mode: 'worker' } } }, { workers: 4 })).toBeNull();
    });
});

describe('memoryDatabase', () => {
    it('recognises memory wearing a disk name', () => {
        expect(memoryDatabase({ database: { filesystem: 'tmpfs' } })).toBe(true);
        expect(memoryDatabase({ database: { filesystem: 'memory' } })).toBe(true);
        expect(memoryDatabase({ database: { filesystem: 'ext4' } })).toBe(false);
        expect(memoryDatabase({ database: { filesystem: null } })).toBe(false);
    });
});

describe('brokenRoutes', () => {
    const http = {
        routes: {
            db_read: { breaking_point: 199, breaking: { concurrency: 199, status_codes: { 200: 12000, 503: 8000 }, errors: {}, causes: ['ports_exhausted'] } },
            io: { breaking_point: null, breaking: null },
            static: { breaking_point: 5000 },
        },
    };

    it('reads the level from the kept answer, and the bare number on runs recorded before it', () => {
        expect(brokenRoutes(http).map(({ key, at }) => ({ key, at }))).toEqual([
            { key: 'db_read', at: 199 },
            { key: 'static', at: 5000 },
        ]);
    });

    it('names the failure that accounts for most of the level, with its share', () => {
        const [dbRead, plain] = brokenRoutes(http);

        expect(dbRead.answer).toEqual({ kind: 'status', code: 503, count: 8000, share: 0.4 });
        expect(dbRead.causes).toEqual(['ports_exhausted']);
        expect(plain.answer).toBeNull();
        expect(plain.causes).toEqual([]);
    });
});

describe('breakingAnswer', () => {
    it('prefers whichever failed more, status or transport', () => {
        expect(breakingAnswer({ status_codes: { 200: 100, 502: 50 }, errors: { 'connection reset': 850 } }))
            .toEqual({ kind: 'transport', error: 'connection reset', count: 850, share: 0.85 });
    });

    it('carries the generator\'s own reason when nothing was measured', () => {
        expect(breakingAnswer({ failure: 'oha exited with status 1', status_codes: {}, errors: {} }))
            .toEqual({ kind: 'unmeasured', failure: 'oha exited with status 1', share: 1 });
    });

    it('is null without counts to read', () => {
        expect(breakingAnswer(null)).toBeNull();
        expect(breakingAnswer({ status_codes: { 200: 10 }, errors: {} })).toBeNull();
    });
});
