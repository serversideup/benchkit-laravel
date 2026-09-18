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

// The route a run leads with when one number has to stand for the web server
// stage: the history list, the run page, the share card, the post, and the
// gallery all read this order. JSON exercises the whole framework request
// path with nothing external to vary, so two JSON figures from two hosts are
// about the hosts. DB read is the more realistic page but the least
// comparable: the database is a confound the hardware has nothing to do with.
// HttpBenchmarkResults::HERO_ROUTES and the gallery's primaryMetric in
// docs/app/types/run.ts state the same order; CrossLanguageDriftTest pins them.
export const HERO_ROUTES = ['json', 'static', 'db_read'];
