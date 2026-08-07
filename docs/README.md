# BenchKit documentation site

The marketing + documentation site for [BenchKit](https://github.com/serversideup/benchkit-laravel), built with [Nuxt](https://nuxt.com), [Nuxt Content](https://content.nuxt.com), and [Nuxt UI](https://ui.nuxt.com). It is a **standalone static site** and is **not** shipped inside the BenchKit Docker image (it's excluded via the repo `.dockerignore`).

## Local development

Requires Node 24 and Yarn (see `.nvmrc` / `packageManager`).

`yarn build` prerenders an OG image per page, which peaks around 1.5 GB and can exhaust Node's
default heap on a small machine (`FATAL ERROR: ... heap out of memory`). If you hit that, give
the build more headroom for that run rather than committing a limit — a ceiling above the
machine's physical RAM makes it swap and is slower than the failure it replaces:

```bash
NODE_OPTIONS=--max-old-space-size=3072 yarn build
```

```bash
cd docs
yarn install
yarn dev
```

The site runs at `http://localhost:3000`. Copy `.env.example` to `.env` if you want to override the base URL, site name, or enable analytics.

## Build the static site

```bash
yarn generate   # outputs static files to .output/public
```

## Editing content

All pages live in [`content/`](./content):

- `content/index.md` — the marketing landing page (authored with Nuxt UI MDC components).
- `content/docs/**` — the documentation, organized into numeric-prefixed folders with a `.navigation.yml` per section.

### Screenshots still to add

Tracked here rather than as comments in `content/`, because `server/routes/raw/` publishes the
raw markdown of every docs page and a `SCREENSHOT_NEEDED` placeholder would be served with it.

| Page | Shot |
| --- | --- |
| `docs/getting-started/quick-start` | Home screen: Start Benchmark, Quick/Full presets |
| `docs/configuration/default-configurations` | Settings drawer: presets and the load-test inputs |
| `docs/configuration/customizing-the-image` | Compare view diffing two runs |
| `docs/benchmarks/web-server-load-test` | Load-test panel: four routes with req/s and percentile bars |
| `docs/benchmarks/throughput-vs-latency` | "Test from your own machine" panel with the corrected latency command |

The landing page hero uses `public/images/benchkit-header.png`.

## Deployment

The site is deployed to **Cloudflare Pages** and served at `serversideup.net/open-source/benchkit`. The sub-path is driven by `NUXT_APP_BASE_URL`; there is no worker or `wrangler.toml` in this repo (Cloudflare Pages is configured in the dashboard).
