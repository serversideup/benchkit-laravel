import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

// Settings persist in localStorage because the app is ephemeral and the
// database is not guaranteed to survive between runs. Bump SETTINGS_VERSION
// only when a *retained* key changes meaning — removals are already safe,
// because loadSavedSettings only restores keys present in `defaults`, so a
// stored payload from an older shape loads into the new one with nothing
// carried over. Bumping for a removal would cost everyone their other
// preferences for a migration that has already happened by construction.
const STORAGE_KEY = 'benchkit-settings';
const SETTINGS_VERSION = 3;

// There is no duration or connection count to set. The load sizes itself
// from the machine it is measuring — the levels come from its cores and its
// worker count — which is what keeps one setting honest on a one-core box and
// a thirty-two-core one. http_io_ms is the only load parameter left, and it
// changes what the /bench/io route measures rather than how hard the load
// pushes; a non-standard value is disclosed with the results.
const defaults = {
    hardware: true,
    disk: true,
    geekbench: true,
    geekbench_version: 6,
    iperf: false,
    network: true,
    network_test_type: 'ipv4',
    http: true,
    http_io_ms: 100,
    // Where the load comes from: 'external' (a paired second machine drives
    // it — the honest absolute number, and the default for every preset) or
    // 'self' (this server drives its own load, zero setup — opted into via
    // the settings drawer or the pairing dialog's escape hatch). Not part of
    // the presets, so switching it never flips the preset buttons to
    // "custom".
    http_generator: 'external',
    php_database: true,
    php_mode: 'full',
};

const numericKeys = ['geekbench_version', 'http_io_ms'];

// http_generator stays out of both presets: load source is an orthogonal
// choice, and folding it in would flip the preset buttons to "custom" the
// moment someone pairs an external generator.
const { http_generator: _, ...presetDefaults } = defaults;

const presets = {
    // Quick and Full run an identical web server test. A quick-mode
    // throughput number that could not be compared with a full-mode one would
    // be worse than not having it, since both land in the same gallery under
    // the same heading.
    quick: {
        hardware: false,
        network: true,
        http: true,
        http_io_ms: defaults.http_io_ms,
        php_database: true,
        php_mode: 'quick',
    },
    full: {
        ...presetDefaults,
    },
};

const loadSavedSettings = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));

        if (!saved || saved.version !== SETTINGS_VERSION) {
            return {};
        }

        const known = {};

        Object.keys(defaults).forEach((key) => {
            if (key in saved) {
                // Selects and number inputs yield strings; keep stored values numeric
                known[key] = numericKeys.includes(key) ? Number(saved[key]) || defaults[key] : saved[key];
            }
        });

        return known;
    } catch {
        return {};
    }
};

// Seeding useForm with the saved values makes form.reset() mean
// "revert to last saved" rather than "revert to factory defaults"
const form = useForm({ ...defaults, ...loadSavedSettings() });

const saveSettings = () => {
    form.defaults();
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ version: SETTINGS_VERSION, ...form.data() }));
};

// Fill the form with a preset's values without saving — used inside the
// settings drawer where Save/Cancel decide whether the draft is kept
const fillPreset = (name) => {
    Object.entries(presets[name]).forEach(([key, value]) => {
        form[key] = value;
    });
};

const applyPreset = (name) => {
    fillPreset(name);
    saveSettings();
};

const activePreset = computed(() => {
    const match = Object.keys(presets).find((name) =>
        Object.entries(presets[name]).every(([key, value]) => String(form[key]) === String(value))
    );

    return match ?? 'custom';
});

// Rough per-test durations in minutes, for the run estimate on the home
// screen. The http stage is computed from its settings: four routes, each
// warmed (~3s) then load tested for the configured duration.
//
// php_full and php_quick are measured, not guessed: the full phpbench suite is
// 82 subjects and took ~26 minutes on a developer machine, the quick filter
// took seconds. Both round up, because these run on whatever box the user is
// benchmarking and that is usually slower than the one they were timed on.
const durations = {
    hardware_base: 1,
    disk: 3,
    geekbench: 12,
    iperf: 1,
    network: 0.5,
    php_quick: 1,
    php_full: 28,
};

// Four routes, each warmed once, measured at up to six concurrency levels,
// then timed at a steady rate. The exact level count depends on the host, so
// this is the upper end rather than a promise.
//
// Kept in step with config/benchmark.php by hand. If these drift, the start
// screen lies about how long a run takes, which is the one estimate a person
// actually plans around.
const HTTP_WARMUP_SECONDS = 3;
const HTTP_LEVEL_SECONDS = 6;
const HTTP_MAX_LEVELS = 6;
const HTTP_LATENCY_SECONDS = 10;

const httpMinutes = () =>
    (4 * (HTTP_WARMUP_SECONDS + HTTP_MAX_LEVELS * HTTP_LEVEL_SECONDS + HTTP_LATENCY_SECONDS)) / 60;

// Takes a plain settings object so the preset buttons can be labelled from the
// same arithmetic as the live estimate, instead of a hardcoded string that
// drifts the moment a duration changes.
const minutesFor = (settings) => {
    let minutes = 0;

    if (settings.hardware) {
        minutes += durations.hardware_base;
        minutes += settings.disk ? durations.disk : 0;
        minutes += settings.geekbench ? durations.geekbench : 0;
        minutes += settings.iperf ? durations.iperf : 0;
    }

    minutes += settings.network ? durations.network : 0;
    minutes += settings.http ? httpMinutes(settings) : 0;

    if (settings.php_database) {
        minutes += settings.php_mode === 'quick' ? durations.php_quick : durations.php_full;
    }

    return minutes;
};

const labelFor = (minutes) => {
    if (minutes < 1) {
        return '<1 min';
    }

    // Past half an hour the minute is noise; round to five so the number reads
    // as the approximation it is.
    return minutes >= 30
        ? `~${Math.round(minutes / 5) * 5} min`
        : `~${Math.round(minutes)} min`;
};

const estimatedMinutes = computed(() => minutesFor(form));

const estimateLabel = computed(() => labelFor(estimatedMinutes.value));

const presetEstimateLabel = (name) => labelFor(minutesFor({ ...defaults, ...presets[name] }));

const runSummary = computed(() => {
    const tests = [];

    if (form.hardware) {
        tests.push('Hardware');
    }

    if (form.network) {
        tests.push('Network');
    }

    if (form.http) {
        tests.push(form.http_generator === 'external' ? 'Web Server Load (external)' : 'Web Server Load');
    }

    if (form.php_database) {
        tests.push(form.php_mode === 'quick' ? 'PHP CRUD (quick)' : 'PHP (full suite)');
    }

    return tests;
});

export const useSettings = () => {
    return {
        form,
        saveSettings,
        fillPreset,
        applyPreset,
        activePreset,
        estimateLabel,
        presetEstimateLabel,
        runSummary,
    };
};
