# BenchKit documentation site

The marketing + documentation site for [BenchKit](https://github.com/serversideup/benchkit-laravel), built with [Nuxt](https://nuxt.com), [Nuxt Content](https://content.nuxt.com), and [Nuxt UI](https://ui.nuxt.com). It is a **standalone static site** and is **not** shipped inside the BenchKit Docker image (it's excluded via the repo `.dockerignore`).

## Local development

Requires Node 22 and Yarn (see `.nvmrc` / `packageManager`).

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

Search for `SCREENSHOT_NEEDED` across `content/` to find where product screenshots should be dropped in.

## Deployment

The site is deployed to **Cloudflare Pages** and served at `serversideup.net/open-source/benchkit`. The sub-path is driven by `NUXT_APP_BASE_URL`; there is no worker or `wrangler.toml` in this repo (Cloudflare Pages is configured in the dashboard).
