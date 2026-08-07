import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

// Settings persist in localStorage because the app is ephemeral and the
// database is not guaranteed to survive between runs. Bump SETTINGS_VERSION
// whenever the shape changes — old payloads are discarded, not migrated.
const STORAGE_KEY = 'benchkit-settings';
const SETTINGS_VERSION = 3;

// http_duration/http_connections/http_io_ms are the "standard BenchKit
// load" — sharing them is what keeps results comparable across hosts. Full
// pins the standard (30s); Quick trades a shorter window (10s) for speed;
// any other values read as a custom run and are disclosed with the results.
const defaults = {
    hardware: true,
    disk: true,
    geekbench: true,
    geekbench_version: 6,
    iperf: false,
    network: true,
    network_test_type: 'ipv4',
    http: true,
    http_duration: 30,
    http_connections: 50,
    http_io_ms: 100,
    php_database: true,
    php_mode: 'full',
};

const numericKeys = ['geekbench_version', 'http_duration', 'http_connections', 'http_io_ms'];

const presets = {
    quick: {
        hardware: false,
        network: true,
        http: true,
        http_duration: 10,
        http_connections: defaults.http_connections,
        http_io_ms: defaults.http_io_ms,
        php_database: true,
        php_mode: 'quick',
    },
    full: {
        ...defaults,
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

const httpMinutes = (settings) => (4 * ((Number(settings.http_duration) || defaults.http_duration) + 3)) / 60;

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
        tests.push('Web Server Load');
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
