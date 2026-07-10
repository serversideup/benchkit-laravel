// Benchmark stage metadata shared by every surface that lists stages.
// `label` is the short name (tab title, run history tooltips); `heading`
// is the long form used where a stage headlines a section (compare page,
// saved logs).
export const STAGES = [
    { key: 'yabs', label: 'Hardware', heading: 'Hardware' },
    { key: 'cfspeedtest', label: 'Network', heading: 'Network speed test' },
    { key: 'http', label: 'Web server load', heading: 'Web server load test' },
    { key: 'php', label: 'PHP', heading: 'Laravel database performance' },
];

export const STAGE_LABELS = Object.fromEntries(STAGES.map(({ key, label }) => [key, label]));
export const STAGE_HEADINGS = Object.fromEntries(STAGES.map(({ key, heading }) => [key, heading]));
