import { describe, expect, it } from 'vitest';
import { memoryDatabase, undersizedPool, workerFootprintMb } from '@shared/run/conditions.mjs';

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
