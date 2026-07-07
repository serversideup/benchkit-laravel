## About This Project: BenchKit for Laravel

BenchKit for Laravel is an ephemeral benchmarking application by Server Side Up. Users spin it up, run a one-time test of real-world performance against a host + Laravel configuration, share the results, then destroy it. It is a standalone application, not a package. A primary use case is comparing `serversideup/php` image variations (e.g. `fpm-nginx` vs `frankenphp`), selected via the `PHP_VARIATION` Docker build arg.

### Design Constraints (important)

- The app is decentralized and does NOT run 24/7 — every instance is temporary and self-hosted. Never design features that assume a persistent server or a reachable URL (no "share a link to results", no hosted dashboards, no callbacks/webhooks).
- Result sharing works by generating an image of the results (via `html-to-image`) that users post themselves, e.g. on X. This is the intended v1 sharing mechanism.
- The app must run both in Docker containers AND non-Docker environments like Laravel Cloud. Do not depend on Docker-specific facilities at runtime.
- External benchmark binaries must be distributed as Composer packages exposing the executable via the `bin` key (available at `vendor/bin/`), following `serversideup/yabs` and `serversideup/cfspeedtest`. Never install binaries via Dockerfile, apt, or curl at runtime — that would break non-Docker environments.

### Domain Vocabulary

- A "benchmark" is a runnable test stage, NOT a database entity. There is no `Benchmark` model or table.
- Three stages run in a client-side queue (defined in `resources/js/Composables/useBenchmarkQueue.js`):
  1. `yabs` — hardware benchmark (CPU, disk/fio, Geekbench, iperf) via serversideup/yabs
  2. `cfspeedtest` — network speed test to Cloudflare via serversideup/cfspeedtest
  3. `php` — Laravel CRUD and PHP performance benchmarks via phpbench
- No authentication and no domain persistence: benchmark results are written to files in `results/` and root-level logs, never to the database.
