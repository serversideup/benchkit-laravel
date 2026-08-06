#!/usr/bin/env node
// Shared shape rules for a stored community run (docs/data/runs/**/*.json),
// used by both the builder and the validator so a hand-edited PR can't drift
// from what the bot would have written.
//
// A stored document is index fields + the run:
//
//   { run_id, github, submitted_at, verified, provider, php_variation, ...,
//     run: { <the full trimmed run document> } }
//
// The flat fields are what a *list* of runs needs — everything the gallery
// renders on a card, filters on, or sorts by. Splitting them out is what lets
// the site publish one small index of every run alongside one file per run,
// so listing thousands of results never means loading thousands of full
// benchmark documents. They are all derived from `run`; the validator
// recomputes them and rejects a mismatch, so the summary can't lie about the
// detail it summarizes.

const RUNS_DIR = 'docs/data/runs';

// People fill optional fields with placeholders rather than leaving them
// blank, and every distinct spelling becomes its own filter chip in the
// gallery. Treat them as "not answered", which is what they mean.
const PLACEHOLDERS = new Set(['na', 'n/a', 'n\\a', 'none', 'null', 'nil', 'nope', 'unknown', 'tbd', '-', '--', '?', 'x']);

// Canonical names for hosts we see often, keyed by the name stripped to
// lowercase letters and digits. Anything unrecognized passes through exactly
// as typed — a guess at title casing does more harm than leaving it alone.
const PROVIDER_ALIASES = {
    amazon: 'AWS',
    amazonwebservices: 'AWS',
    aws: 'AWS',
    ec2: 'AWS',
    akamai: 'Akamai',
    azure: 'Azure',
    microsoftazure: 'Azure',
    digitalocean: 'DigitalOcean',
    do: 'DigitalOcean',
    fly: 'Fly.io',
    flyio: 'Fly.io',
    gcp: 'Google Cloud',
    google: 'Google Cloud',
    googlecloud: 'Google Cloud',
    googlecloudplatform: 'Google Cloud',
    hetzner: 'Hetzner',
    hetzneronline: 'Hetzner',
    hostinger: 'Hostinger',
    laravelcloud: 'Laravel Cloud',
    linode: 'Linode',
    oci: 'Oracle Cloud',
    oracle: 'Oracle Cloud',
    oraclecloud: 'Oracle Cloud',
    ovh: 'OVH',
    ovhcloud: 'OVH',
    ovhsas: 'OVH',
    railway: 'Railway',
    render: 'Render',
    scaleway: 'Scaleway',
    home: 'Self-Hosted',
    homelab: 'Self-Hosted',
    self: 'Self-Hosted',
    selfhosted: 'Self-Hosted',
    vultr: 'Vultr',
};

export const CURRENCIES = [
    'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'SEK', 'NOK', 'DKK',
    'PLN', 'INR', 'SGD', 'JPY', 'BRL', 'NZD', 'ZAR', 'MXN',
];

// ---- privacy guard ----
//
// The app submits an allow-list, so nothing sensitive should reach here. This
// is the second lock: a scan of every string in the document for things that
// identify the person or the machine, run on every PR. It exists so that
// adding a field later can't quietly start publishing someone's home IP —
// the guard fails the PR before a human has to notice.
//
// A run is submitted from a machine the submitter controls, often their own
// hardware at home. Treat that accordingly.

const ALLOWED_URL_HOSTS = ['browser.geekbench.com'];

// A four-part version string ("1.2.3.4") also matches the IP pattern. That's
// left alone deliberately: it fails the PR with a message a maintainer can
// read, which is the safe direction to be wrong in.
const LEAK_PATTERNS = [
    ['an IP address', /\b(?:\d{1,3}\.){3}\d{1,3}\b/],
    ['an IPv6 address', /(?:[0-9a-f]{1,4}:){3,}[0-9a-f]{0,4}/i],
    ['a filesystem path', /(?:^|[\s"'(=])(?:\/(?:home|root|var|usr|etc|opt|srv|mnt|media|tmp|Users)\/|[A-Za-z]:\\)/],
    ['an email address', /[\w.+-]+@[\w-]+\.[a-z]{2,}/i],
    ['a private hostname', /\b[\w-]+\.(?:local|internal|lan|home|localdomain)\b/i],
];

/**
 * Every string in the document that looks like it identifies a person or a
 * machine. Walks values rather than the serialized JSON so numbers can't be
 * concatenated into something that resembles an address.
 *
 * @returns {string[]} one message per finding, empty when clean
 */
export const findPrivacyLeaks = (value) => {
    const found = [];

    const walk = (node, at) => {
        if (typeof node === 'string') {
            for (const [what, pattern] of LEAK_PATTERNS) {
                if (pattern.test(node)) {
                    found.push(`${at} looks like it contains ${what} (${JSON.stringify(node.slice(0, 60))}) — this file is public`);
                }
            }

            const host = node.match(/https?:\/\/([^/\s"']+)/i)?.[1];

            if (host && !ALLOWED_URL_HOSTS.includes(host.toLowerCase())) {
                found.push(`${at} links to ${host}; only ${ALLOWED_URL_HOSTS.join(', ')} is allowed in a public run`);
            }
        } else if (Array.isArray(node)) {
            node.forEach((item, index) => walk(item, `${at}[${index}]`));
        } else if (node && typeof node === 'object') {
            for (const [key, child] of Object.entries(node)) {
                walk(child, at ? `${at}.${key}` : key);
            }
        }
    };

    walk(value, '');

    return found;
};

/** Trim, and treat a placeholder as the blank the submitter meant. */
export const cleanText = (value) => {
    if (typeof value !== 'string') return null;
    const trimmed = value.trim();
    return trimmed && !PLACEHOLDERS.has(trimmed.toLowerCase()) ? trimmed : null;
};

export const canonicalProvider = (value) => {
    const name = cleanText(value);
    if (!name) return null;

    const key = name.toLowerCase().replace(/[^a-z0-9]/g, '');

    // RIPE gives us names like "DIGITALOCEAN-ASN" when we guess the host from
    // the network, so try the name without that suffix too.
    return PROVIDER_ALIASES[key] ?? PROVIDER_ALIASES[key.replace(/asn$/, '')] ?? name;
};

/** Runs are sharded by month so the directory stays readable at scale. */
export const runsPathFor = (id) => `${RUNS_DIR}/${id.slice(0, 4)}-${id.slice(4, 6)}/${id}.json`;

const num = (value) => (typeof value === 'number' && Number.isFinite(value) ? value : null);
const route = (run, key) => run?.benchmarks?.http?.routes?.[key] ?? null;

/**
 * The queryable columns for one run: everything the gallery list renders,
 * filters on, or sorts by, so a listing never has to open a run in full. Pure
 * — same run in, same fields out — so the validator can recompute and compare.
 */
export const indexFields = (run) => {
    const cost = run?.meta?.cost ?? null;

    return {
        run_id: run?.id ?? null,
        label: run?.meta?.label ?? null,
        // A run with no host named is someone's own hardware, and saying so
        // beats an empty filter chip.
        provider: canonicalProvider(run?.meta?.provider) ?? 'Self-Hosted',
        php_variation: run?.environment?.php?.php_variation ?? null,
        php_version: run?.environment?.php?.php_version ?? null,
        cpu_cores: Number.parseInt(String(run?.environment?.server?.cpu_cores ?? ''), 10) || null,
        json_rps: num(route(run, 'json')?.requests_per_second),
        json_p95_ms: num(route(run, 'json')?.p95_ms),
        static_rps: num(route(run, 'static')?.requests_per_second),
        static_p95_ms: num(route(run, 'static')?.p95_ms),
        db_read_rps: num(route(run, 'db_read')?.requests_per_second),
        db_read_p95_ms: num(route(run, 'db_read')?.p95_ms),
        php_read_ms: num(run?.benchmarks?.php?.headline?.read?.milliseconds),
        // Stored as billed, never converted — a rate belongs at display time.
        cost_amount: num(cost?.amount),
        cost_currency: typeof cost?.currency === 'string' ? cost.currency : null,
    };
};

/**
 * Assemble the file the gallery reads. Placeholder answers are dropped and the
 * host name is canonicalized in place, so the run and the filter chips agree
 * — the whole edit is visible in the PR diff a maintainer reviews.
 */
export const buildDocument = (run, { github, submittedAt, verified = false }) => {
    const meta = { ...(run.meta ?? {}) };
    meta.provider = canonicalProvider(meta.provider);
    meta.plan = cleanText(meta.plan);
    meta.datacenter = cleanText(meta.datacenter);

    const normalized = { ...run, meta };

    return {
        ...indexFields(normalized),
        github,
        submitted_at: submittedAt,
        verified,
        run: normalized,
    };
};
