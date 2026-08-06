# Community benchmark runs

Each `*.json` file in this directory is **one submitted BenchKit run**, rendered on the
[community results gallery](https://serversideup.net/open-source/benchkit/results).

You don't write these by hand. In the BenchKit app, run a benchmark and click
**Submit Results** — it opens a pre-filled pull request that adds one file here.

## File shape

```jsonc
{
  "submission": {
    "github": "your-github-username", // self-reported for credit; the reviewer checks it against the PR author. Optional.
    "submitted_at": "2026-08-05",
    "verified": false                 // true only for maintainer-run reference anchors
  },
  "run": {
    // A trimmed BenchKit run document (app/Actions/Results/AssembleResultsDocument.php).
    // Logs, ini dumps, phpbench subjects, and settings are stripped before submission.
    "schema_version": 1,
    "id": "20260805-152622-l9ft",
    "created_at": "2026-08-05T15:26:22+00:00",
    "meta": { "label": "...", "provider": null, "plan": null, "datacenter": null, "cost": null },
    "environment": { "server": { ... }, "php": { ... }, "laravel": { ... } },
    "benchmarks": { "http": { ... }, "php": { ... }, "cfspeedtest": { ... } }
  }
}
```

The full schema is enforced by `docs/content.config.ts` (the `runs` collection) and by the
`validate-run-submission` GitHub Action. A PR that doesn't match the schema fails CI.

## Naming

Name the file after the run id: `<run.id>.json` (e.g. `20260805-152622-l9ft.json`).

## Trust & honesty

These numbers are **community-submitted and unverified** — BenchKit runs on hardware the
submitter controls, so a single run can't be independently proven. The gallery leans on
volume and visible distribution, labels everything unverified, and marks maintainer-run
entries separately. Please don't submit fabricated or manipulated results.
