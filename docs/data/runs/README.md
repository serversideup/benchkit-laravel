# Community benchmark runs

Each `*.json` file under this directory is **one submitted BenchKit run**, rendered on the
[community results gallery](https://serversideup.net/open-source/benchkit/results).

You don't write these by hand. In the BenchKit app, run a benchmark and click
**Submit Results** — it opens a pre-filled issue, and a bot files the pull request that
adds one file here.

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
  // All of them are DERIVED from `run` by .github/scripts/run-document.mjs, and the PR
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

  // ---- the run itself ----
  "run": {
    // A trimmed BenchKit run document (app/Actions/Results/AssembleResultsDocument.php).
    // Logs, ini dumps, phpbench subjects, and settings are stripped before submission.
    "schema_version": 1,
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
    "benchmarks": { "http": { ... }, "php": { ... }, "cfspeedtest": { ... } }
  }
}
```

The shape is enforced by the `validate-run-submission` GitHub Action, which is the gate
that matters: a run that fails it never merges. It checks far more than a type schema
could — value ranges, control characters and HTML in free text, the filename matching the
run id, and the summary fields matching the run. The build does a shallow sanity check on
top, so a malformed file fails the build rather than rendering a blank card.

## Naming

Runs are sharded by month so the directory stays readable at thousands of entries:

```
docs/data/runs/<YYYY>-<MM>/<run.id>.json
docs/data/runs/2026-08/20260805-152622-l9ft.json
```

Both the month and the filename come from the run id, which starts with `YYYYMMDD`. A run
id is minted once per benchmark, so a file that already exists means the run was already
submitted — the bot rejects the duplicate rather than overwriting it.

## Currency

Costs are stored exactly as the submitter is billed and are **never converted on the way
in** — a USD figure baked into the file would be wrong the moment rates moved, with no
record of which rate was used. The gallery converts at render time using the
hand-maintained table in `docs/app/utils/fx.ts`, and labels anything derived from it as
approximate.

## Trust & honesty

These numbers are **community-submitted and unverified** — BenchKit runs on hardware the
submitter controls, so a single run can't be independently proven. The gallery leans on
volume and visible distribution, labels everything unverified, and marks maintainer-run
entries separately. Please don't submit fabricated or manipulated results.
