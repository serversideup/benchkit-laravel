# Community benchmark runs

Each `*.json` file under this directory is **one submitted BenchKit run**, rendered on the
[community results gallery](https://serversideup.net/open-source/benchkit/results).

You don't write these by hand. In the BenchKit app, run a benchmark and click
**Submit result** — it opens a pre-filled issue carrying a compressed submission token, and
a bot unpacks it, seals it, and files the pull request that adds one file here.

## How these become a website

This directory is the source of truth. At build time
[`docs/modules/results-api.ts`](../../modules/results-api.ts) publishes it as a static API:

| | contains | grows with |
|---|---|---|
| `/api/results/index.json` | every run, summary fields only (~400 B each) | number of runs |
| `/api/results/<run id>.json` | one run, in full (~3 KB) | nothing |

The gallery loads the index; a result page loads one record. Neither gets heavier because
the other did.

These files are deliberately **not** a Nuxt Content collection. Content compiles a
collection into SQLite and publishes a dump of the whole table to the browser — and
nothing here is actually a query: the gallery filters and sorts a plain array, and a
result page looks a run up by id. Content still owns `docs/content`, where its markdown
pipeline earns its keep.

The published JSON is a stable public dataset. If you'd rather plot these yourself than
use the gallery, fetch them directly.

## File shape

A stored run is **summary fields plus the run itself**.

```jsonc
{
  // ---- summary fields ----
  // Everything the gallery lists, filters, or sorts on, hoisted so that showing a list
  // of runs never means loading every run in full.
  //
  // All of them are DERIVED from `run` by shared/submission/run-document.mjs, and the PR
  // validator recomputes and compares them. Don't hand-edit: a mismatch fails CI, which
  // is what stops a summary from disagreeing with the run it summarizes.
  "run_id": "20260805-152622-l9ft",
  "label": "fpm-nginx (Quick)",
  "provider": "DigitalOcean",       // canonicalized: "digitalocean", "DO", "DIGITALOCEAN-ASN" all land here
  "php_variation": "fpm-nginx",
  "php_version": "8.4.1",
  "cpu_cores": 2,
  "json_rps": 1234.5, "json_p95_ms": 3,
  "static_rps": null, "static_p95_ms": null,
  "db_read_rps": null, "db_read_p95_ms": null,
  "php_read_ms": null,
  "cost_amount": 20, "cost_currency": "EUR",

  // ---- submitter ----
  "github": "your-github-username", // the authenticated issue author, stamped by the bot
  "submitted_at": "2026-08-05",
  "verified": false,                // true only for maintainer-run reference anchors
  "issue": 412,                     // where it was submitted, so a reader can go and ask
                                    // about it. Stamped by the bot from the event payload;
                                    // absent on runs added by hand. Deliberately outside
                                    // `run` — the seal covers everything in there, and the
                                    // number doesn't exist until after the app has finished.

  // ---- the run itself ----
  "run": {
    // A trimmed BenchKit run document (app/Actions/Results/AssembleResultsDocument.php).
    // Logs, ini dumps, phpbench subjects, and settings are stripped before submission.
    // The validator rejects superseded versions rather than warning about them: a
    // bump always means a measurement changed, so those runs cannot be read on the
    // same axis as current ones. Each one's reason is in SUPERSEDED_SCHEMAS in
    // shared/submission/validate.mjs — v3 and earlier let phpbench warmup consume
    // the fixture, so delete reported roughly half its real cost.
    "schema_version": 4,
    "id": "20260805-152622-l9ft",
    "created_at": "2026-08-05T15:26:22+00:00",
    "meta": {
      "label": "...",
      "provider": null,
      "plan": null,
      "datacenter": null,
      // Structured, always monthly, in the currency the submitter is billed.
      // Never a string: "$24/mo" and "20 EUR" can't be compared, and price per
      // request is the whole reason cost is recorded.
      "cost": { "amount": 20, "currency": "EUR", "period": "monthly" }
    },
    "environment": { "server": { ... }, "php": { ... }, "laravel": { ... } },
    "benchmarks": { "http": { ... }, "php": { ... }, "cfspeedtest": { ... } },

    // SHA-256 over a canonical, key-sorted serialization of everything in `run`
    // except `meta` and this block, stamped by the bot when it accepts the
    // submission. See "Integrity" below.
    "integrity": { "algorithm": "sha256", "digest": "fc25536d…" }
  }
}
```

The shape is enforced by the `validate-run-submission` GitHub Action, which is the gate
that matters: a run that fails it never merges. It checks far more than a type schema
could — value ranges, control characters and HTML in free text, the filename matching the
run id, the summary fields matching the run, and the integrity seal matching the
measurements. The build re-checks the shape and the seal, so a malformed or altered file
fails the build rather than shipping.

## Integrity

Every stored run carries `run.integrity`: a SHA-256 over a canonical, key-sorted
serialization of everything in `run` **except** `meta` and the seal itself. The bot
computes it when it accepts a submission; the PR validator and the site build both
recompute it and refuse a mismatch.

`meta` sits outside the seal on purpose. The label, host, plan, datacenter, and cost are
typed by a person, and a maintainer should be free to fix `Digial Ocean` → `DigitalOcean`
in review without breaking anything. Everything else — the whole environment and every
benchmark number — is sealed by default, so a field added to a run later is covered without
anyone remembering to extend a list.

What this buys, plainly: **tamper-evidence, not authenticity.** BenchKit is open source and
self-hosted, so someone determined enough can run the encoder and mint whatever they like —
no client-side checksum can prevent that. What the seal does eliminate is the entire class
of low-effort problems: a truncated submission, a mangled paste, a number nudged in the
issue body, an edit slipped into the pull request, or a commit straight to `main` after
merge. Reformatting the file, reordering keys, or changing indentation does not break it.
Editing a measurement does, permanently, on every future CI run.

The `verified` badge and human review are what speak to whether a number was honestly
measured. The seal only says it is the number that was submitted.

## Naming

Runs are sharded by month so the directory stays readable at thousands of entries:

```
docs/data/runs/<YYYY>-<MM>/<run.id>.json
docs/data/runs/2026-08/20260805-152622-l9ft.json
```

Both the month and the filename come from the run id, which starts with `YYYYMMDD`. A run
id is minted once per benchmark, so a file that already exists means the run was already
submitted — the bot rejects the duplicate rather than overwriting it.

## What is and isn't published

A run comes off a machine the submitter controls — often their own hardware, at home. The
app submits an **allow-list**, so a field only becomes public because someone chose to
publish it.

**Published**, because it explains the numbers: CPU model and core count, RAM, OS, PHP and
Laravel versions, the image variation, SAPI, Octane, the performance-relevant `php.ini`
values (JIT, OPcache sizing, realpath cache), FPM worker settings, which stages ran, the
benchmark results, and the host/plan/cost the submitter typed in.

**Never published**: console logs (they contain the submitter's public IP), `APP_URL` and
any internal hostname, the raw `yabs` output (it carries an `ip_info` block with IP, ISP,
and city), the network test's ASN and Cloudflare colo (on home hardware those are a
residential ISP and a nearest city — the speeds are the part worth comparing), and
`opcache.preload` — a filesystem path that would expose a directory layout and often a
project or company name. Whether preloading is on is published as
`opcache.preload_enabled` instead; where the file lives is not. `build_version` is only
published when it's a plain tag, so a self-built image tagged
`ghcr.io/your-company/benchkit` doesn't carry the company name into a public file.

A second, independent check backs this up: the `validate-run-submission` action scans every
string in the document for IP addresses, filesystem paths, email addresses, private
hostnames, and links to anywhere other than Geekbench — and fails the PR if it finds one.
That guard covers fields nobody has thought about yet, so adding data later can't quietly
start publishing something it shouldn't.

If you spot something in here that shouldn't be public, please open an issue.

## Currency

Costs are stored exactly as the submitter is billed and are **never converted** — not on
the way in, and not at render either. Any rate we shipped would be wrong by an unknown
amount and get more wrong every day nobody updated it, for a figure people screenshot.

The gallery compares value *within* a single currency instead, which needs no rate and
cannot go stale (`valuePerCostUnit` in [`docs/app/types/run.ts`](../../app/types/run.ts),
scoped by a currency selector). Ratios across currencies aren't comparable, so nothing
tries to make them look like they are.

## Trust & honesty

These numbers are **community-submitted and unverified** — BenchKit runs on hardware the
submitter controls, so a single run can't be independently proven. The gallery leans on
volume and visible distribution, labels everything unverified, and marks maintainer-run
entries separately. Please don't submit fabricated or manipulated results.
