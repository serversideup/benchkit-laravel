<template>
    <div class="w-full">
        <div class="w-full flex flex-col items-center py-10 sm:py-16 px-4">

        <!-- Settings that make a run measure something other than what the user
             wants measured. Shown before the run rather than after it: a full
             suite takes half an hour, and finding out at the end that the
             numbers describe a development configuration wastes all of it. -->
        <div v-for="blocker in blockers" :key="blocker.key"
            class="w-full max-w-[700px] mb-8 rounded-lg border border-[#F97066]/40 bg-[#F97066]/5 p-4">
            <p class="text-sm font-medium text-[#F97066]">{{ blocker.title }}</p>
            <p class="mt-1.5 text-xs text-[#94979C] leading-relaxed">{{ blocker.detail }}</p>
            <p class="mt-2 text-xs text-[#94979C] leading-relaxed">
                Fix: <span class="font-mono text-[#CECFD2]">{{ blocker.fix }}</span>, then restart the container.
            </p>
        </div>

        <div class="w-full flex flex-col items-center justify-center">
            <button @click="startBenchkit()" :disabled="runSummary.length === 0" class="text-xl font-semibold px-12 py-5 inline-flex items-center border-2 border-[rgba(255,255,255,0.12)] rounded-xl text-white bg-[#E62E05] shadow-lg shadow-[#E62E05]/25 transition-all duration-300"
                :class="runSummary.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#F13D12] hover:shadow-[#E62E05]/40 cursor-pointer'">
                <img src="/images/ui/lightning.svg" alt="Lightning" class="h-6 mr-3">
                Start Benchmark
            </button>

            <div class="flex flex-col sm:flex-row w-full max-w-xs sm:max-w-full sm:w-auto gap-1 rounded-lg border border-[#373A41] bg-[#0C0E12] p-1 font-mono text-sm mt-5">
                <button @click="applyPreset('quick')" class="px-4 py-1.5 rounded-md text-center cursor-pointer transition-colors duration-200"
                    :class="activePreset === 'quick' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">
                    Quick <span class="text-[#61656C]">&middot; {{ presetEstimateLabel('quick') }}</span>
                </button>
                <button @click="applyPreset('full')" class="px-4 py-1.5 rounded-md text-center cursor-pointer transition-colors duration-200"
                    :class="activePreset === 'full' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">
                    Full <span class="text-[#61656C]">&middot; {{ presetEstimateLabel('full') }}</span>
                </button>
                <button @click="openDrawer()" class="px-4 py-1.5 rounded-md text-center cursor-pointer transition-colors duration-200"
                    :class="activePreset === 'custom' ? 'bg-[#22262F] text-white' : 'text-[#94979C] hover:text-[#CECFD2]'">
                    Custom <span v-if="activePreset === 'custom'" class="text-[#61656C]">&middot; {{ estimateLabel }}</span>
                </button>
            </div>

            <p v-if="startError" class="mt-3 text-xs text-[#F97066] font-mono max-w-md text-center">
                {{ startError }}
            </p>
            <p v-else-if="runSummary.length" class="mt-3 text-xs text-[#94979C] font-mono">
                Running tests for: {{ runSummary.join(' · ') }}
            </p>
            <p v-else class="mt-3 text-xs text-[#94979C] font-mono">
                No tests selected &mdash; choose a preset or customize.
            </p>

        </div>

        <div v-if="recentRuns.length" class="mx-auto w-full max-w-[700px] flex flex-col mt-12">
            <div class="flex items-center justify-between">
                <h2 class="text-sm text-[#61656C] font-mono uppercase tracking-wider">Recent runs</h2>
                <Link href="/runs" class="text-xs text-[#94979C] font-mono hover:text-[#CECFD2] underline underline-offset-4 decoration-[#373A41] hover:decoration-[#94979C]">
                    View history &rarr;
                </Link>
            </div>
            <div class="mt-3">
                <RunHistoryList :runs="recentRuns" />
            </div>
        </div>

        <div class="mx-auto w-full max-w-[700px] flex flex-col items-center justify-center mt-12">
            <div class="flex items-center justify-between w-full rounded-t-lg bg-[rgba(255,255,255,0.50)] py-2 px-3">
                <div>
                    <img src="/images/ui/window-controls.svg" alt="Window Controls"/>
                </div>
            </div>
            <div class="w-full py-2 px-4 bg-[#13161B] rounded-b-lg flex flex-col">
                <img src="/images/ui/your-environment.svg" alt="Your Environment" class="py-4 w-80 max-w-full"/>

                <Server />
                <Php />
                <Laravel />
            </div>
        </div>

        <GeneratorPairingModal :open="showPairingModal" @close="showPairingModal = false" @start="startPaired" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Server from '@/Pages/Partials/Server.vue';
import Php from '@/Pages/Partials/Php.vue';
import Laravel from '@/Pages/Partials/Laravel.vue';
import GeneratorPairingModal from '@/Components/GeneratorPairingModal.vue';
import RunHistoryList from '@/Components/Runs/RunHistoryList.vue';
import { useBenchmarkQueue } from '@/Composables/useBenchmarkQueue';
import { useGeneratorPairing } from '@/Composables/useGeneratorPairing';
import { useSettings } from '@/Composables/useSettings';
import { useSettingsDrawer } from '@/Composables/useSettingsDrawer';

const recentRuns = computed(() => usePage().props.recentRuns ?? []);

/**
 * Configuration that would make this run measure a development setup rather
 * than a deployable one. These read from the web process's own specs, so they
 * describe the PHP that will actually serve the load test.
 *
 * Each says what will be wrong with the numbers and what to change, because
 * naming the setting is the part a first-time user can't act on.
 */
const blockers = computed(() => {
    const page = usePage().props;
    const found = [];

    // ini_get('opcache.enable') reports '1'/'0' as strings, and is absent
    // entirely when the extension isn't loaded — which is also "no OPcache".
    const opcache = page.php?.op_cache;

    if (opcache == null || String(opcache) !== '1') {
        found.push({
            key: 'opcache',
            title: 'OPcache is off — this run would measure a server nobody deploys',
            detail: 'PHP would recompile your whole application from source on every single request. Expect throughput several times lower than this box really does, and a result you can\'t compare with anything in the gallery.',
            fix: 'PHP_OPCACHE_ENABLE=1',
        });
    }

    // Config and route caches are files on disk, so the command line and the
    // web process agree about them — unlike OPcache, which each SAPI holds
    // separately. The official image builds them at boot, so a run without
    // them is almost always one started from source or from a dev compose.
    const uncached = ['config', 'routes', 'events'].filter((key) => page.laravel?.cache?.[key] === false);

    if (uncached.length > 0) {
        found.push({
            key: 'unoptimized',
            title: 'This app has not been prepared for production',
            detail: 'Laravel would re-read its configuration and routes on every single request, which a deployed app does once at boot. Expect throughput well below what this box really does, and a result that is not comparable with the gallery.',
            fix: 'php artisan optimize',
        });
    }

    if (page.laravel?.environment?.debug_mode === true) {
        found.push({
            key: 'debug',
            title: 'Debug mode is on — this run would measure a development setup',
            detail: 'Laravel would build a stack trace on every request, which it never does in production. The numbers would come out well below what this host can really do, and wouldn\'t be comparable with other results.',
            fix: 'APP_DEBUG=false',
        });
    }

    // The shipped worker count is a fixed 20 on every machine, because it is
    // baked into the image and nothing computes it at boot. On anything larger
    // than that the run can only use part of the hardware, and this is the
    // moment to say so — afterwards the only remedy is running it again.
    //
    // The suggestion is derived from memory rather than from cores. A worker
    // is a process, so the pool is bounded by RAM, and real deployments size it
    // that way: a request spends much of its life waiting rather than
    // computing, so a core-count pool leaves a machine idle under any load with
    // I/O in it. Measured on this project's own image, a worker resides in
    // roughly 40 MB.
    const cores = Number.parseInt(String(page.server?.cpu_cores ?? ''), 10) || null;
    const workers = page.php?.runtime?.workers;
    const ramMb = Number.parseFloat(String(page.server?.ram ?? '')) || null;

    // Leave a quarter of memory for the OS, the database, and BenchKit itself,
    // and never suggest fewer workers than the machine has cores.
    const suggested = ramMb && cores
        ? Math.max(cores, Math.floor((ramMb * 0.75) / 40))
        : cores;

    if (cores && workers && page.php?.runtime?.mode === 'process-per-request' && workers < cores) {
        found.push({
            key: 'undersized-pool',
            title: `This machine has ${cores} cores but PHP is set up to use ${workers} workers`,
            detail: `PHP handles one request per worker, so this run can keep at most ${workers} of them busy and the result understates the hardware. A machine this size can carry about ${suggested}. Each worker holds roughly 40 MB, so raising it past what your RAM allows trades a slow server for one the kernel kills.`,
            fix: `PHP_FPM_PM_MAX_CHILDREN=${suggested}`,
        });
    }

    return found;
});

/**
 * An external run needs its generator before anything else can usefully
 * happen — the HTTP stage runs first in that mode — so Start opens the
 * pairing dialog until one is connected. A generator already connected, or
 * a self-test, starts immediately.
 */
const startBenchkit = () => {
    if (form.http && form.http_generator === 'external' && !connected.value) {
        showPairingModal.value = true;

        return;
    }

    startQueue();
}

const startPaired = () => {
    showPairingModal.value = false;
    startQueue();
}

const {
    startQueue,
    startError,
} = useBenchmarkQueue();

const {
    form,
    applyPreset,
    activePreset,
    estimateLabel,
    presetEstimateLabel,
    runSummary,
} = useSettings();

const { open: openDrawer } = useSettingsDrawer();

const { connected } = useGeneratorPairing();

const showPairingModal = ref(false);
</script>