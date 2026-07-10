## Project Conventions & Gotchas

- The frontend is plain JavaScript (no TypeScript), Vue 3 `<script setup>` Composition API only.
- The JavaScript package manager is **yarn** (`yarn.lock`), not npm.
- The UI uses a fixed dark palette with hardcoded hex colors (e.g. `bg-[#13161B]`, brand red `#E62E05`). There is no light mode and no `dark:` variants — do not add them.
- The Tailwind v4 theme lives in `resources/css/app.css` (`@theme` directive, JetBrains Mono font). There is no `tailwind.config.js`.
- The dev URL is `https://benchkit.dev.test` (Traefik with local certificates); Vite HMR is served at `https://vite.dev.test`.
- Files in `results/`, root-level `*.log` files, `geekbench_claim.url`, and timestamped fio directories are stale benchmark run artifacts, not source code — ignore them when exploring the codebase.
- `tests/` (PHPUnit, self-contained with SQLite `:memory:`) and `app/Benchmarks/` (phpbench) are unrelated — do not mix their conventions. Shared test helpers live in `tests/Concerns/` (`UsesFakeResultsPath`, `SeedsRunSnapshots`).
