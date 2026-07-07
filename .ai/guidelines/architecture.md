## Architecture

### Backend

- Benchmark controllers in `app/Http/Controllers/Benchmarks/` launch each stage as a subprocess (Symfony Process wrapped in `script -q /dev/null -c ...`) and stream its output to the browser over Server-Sent Events. `YabsController`, `CloudflareSpeedTestController`, and `PhpBenchmarkController` share a near-duplicated SSE pattern — when changing one, check whether the change belongs in all three.
- Environment specs come from `app/Actions/Specs/` (`ServerSpecs`, `PhpSpecs`, `LaravelSpecs`), invoked directly via `(new ClassName)->execute()`. They shell out to `/proc`, `sysctl`, and `artisan about --json`, and are designed to run inside the Linux container.
- Classes in `app/Benchmarks/Php/` are run by **phpbench** (`phpbench.json`, bootstrap `phpbench-bootstrap.php`), NOT PHPUnit. They use `#[Bench\...]` attributes and create/drop their own tables (`benchmark_products`, `benchmark_users`) at runtime — these tables intentionally have no migrations.
- Database migrations are the stock Laravel skeleton only (users/cache/jobs). The dev database is SQLite at `.infrastructure/volume_data/sqlite/database.sqlite`.
- `laravel/mcp` is installed but unused — there is no `app/Mcp` or `routes/ai.php`. Adding MCP features is greenfield. (The `.mcp.json` in the repo belongs to Laravel Boost's own MCP server, unrelated.)

### Frontend

- A single Inertia page, `resources/js/Pages/Index.vue`, toggles `Pages/Partials/{Home,Running,Completed}.vue` based on benchmark state. Layouts are assigned via `defineOptions({ layout: AppLayout })`.
- Shared state lives in composables using module-scope singleton reactive state: `useBenchmarkQueue` (queue state machine), `useStream` (EventSource + `@vueuse/core` event bus), and `useSettings` (a module-level Inertia `useForm` holding benchmark options).
