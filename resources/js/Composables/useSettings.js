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
const durations = {
    hardware_base: 1,
    disk: 3,
    geekbench: 12,
    iperf: 1,
    network: 0.5,
    php_quick: 1,
    php_full: 30,
};

const httpMinutes = () => (4 * ((Number(form.http_duration) || defaults.http_duration) + 3)) / 60;

const estimatedMinutes = computed(() => {
    let minutes = 0;

    if (form.hardware) {
        minutes += durations.hardware_base;
        minutes += form.disk ? durations.disk : 0;
        minutes += form.geekbench ? durations.geekbench : 0;
        minutes += form.iperf ? durations.iperf : 0;
    }

    minutes += form.network ? durations.network : 0;
    minutes += form.http ? httpMinutes() : 0;

    if (form.php_database) {
        minutes += form.php_mode === 'quick' ? durations.php_quick : durations.php_full;
    }

    return minutes;
});

const estimateLabel = computed(() => {
    if (estimatedMinutes.value >= 30) {
        return '~30+ min';
    }

    return `~${Math.max(1, Math.round(estimatedMinutes.value))} min`;
});

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
        runSummary,
    };
};
