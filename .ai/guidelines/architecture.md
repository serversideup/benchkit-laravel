## Architecture

### Backend

- Benchmark controllers in `app/Http/Controllers/Benchmarks/` extend `StageController` and are structurally identical: a Form Request validates input, a command builder in `app/Support/` (`YabsCommand`, `CfSpeedTestCommand`, `HttpBenchCommand`, `PhpBenchCommand`) assembles the shell command, and `App\Support\StreamedProcess` runs it as a subprocess (Symfony Process wrapped in `script -q /dev/null -c ...`) streaming output to the browser over Server-Sent Events. When changing one stage controller, check whether the change belongs in all four.
- Result parsing lives in `app/Actions/Results/` — one class per stage extending the `BenchmarkResults` base (results path + JSON read guard), plus `AssembleResultsDocument` which merges specs and all stage outputs into the unified `/results` document.
- Run snapshots (`app/Actions/Runs/`) freeze a completed run into a JSON document on the `runs` disk (`storage/app/runs`); there is still no database persistence for results.
- Environment specs come from `app/Actions/Specs/` (`ServerSpecs`, `PhpSpecs`, `LaravelSpecs`), invoked directly via `(new ClassName)->execute()`. They shell out to `/proc`, `sysctl`, and `artisan about --json`, and are designed to run inside the Linux container.
- Classes in `app/Benchmarks/Php/` are run by **phpbench** (`phpbench.json`, bootstrap `phpbench-bootstrap.php`), NOT PHPUnit. They use `#[Bench\...]` attributes and create/drop their own tables (`benchmark_products`, `benchmark_users`) at runtime — these tables intentionally have no migrations.
- Database migrations are the stock Laravel skeleton only (users/cache/jobs). The dev database is SQLite at `.infrastructure/volume_data/sqlite/database.sqlite`.
- `laravel/mcp` is installed but unused — there is no `app/Mcp` or `routes/ai.php`. Adding MCP features is greenfield. (The `.mcp.json` in the repo belongs to Laravel Boost's own MCP server, unrelated.)

### Frontend

- The home Inertia page, `resources/js/Pages/Index.vue`, toggles `Pages/Partials/{Home,Running}.vue` based on benchmark state; saved runs get their own pages under `Pages/Runs/` (`Index`, `Show`, `Compare`). Layouts are assigned via `defineOptions({ layout: AppLayout })`.
- Shared state lives in composables using module-scope singleton reactive state: `useBenchmarkQueue` (queue state machine), `useStream` (SSE fetch reader + `@vueuse/core` event bus), and `useSettings` (a module-level Inertia `useForm` holding benchmark options). Parametrized composables (`useRunSummary`, `useRunComparison`, `useHostDetails`, …) are pure and hold no module state.
- Stage metadata (keys and display labels) is centralized in `resources/js/stages.js`; fetch CSRF helpers in `resources/js/http.js`.
