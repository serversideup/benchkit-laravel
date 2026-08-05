---
seo:
  title: BenchKit — Understand true Laravel performance
  description: A self-hostable benchmarking playground that measures real-world Laravel performance across hardware and PHP images — hardware, network, web-server load, and PHP CRUD.
---

::u-page-hero{class="dark:bg-black"}
---
orientation: vertical
---
#title
Understand your [true]{.text-primary-500} Laravel performance.

#description
BenchKit is a self-hostable benchmarking playground for Laravel. Spin it up on any server, run a real workload, and see how your hardware and PHP setup actually perform — then compare FrankenPHP against PHP-FPM, sweep your tuning, and share the results.

#links
  :::u-button
  ---
  to: /docs/getting-started
  size: xl
  trailing-icon: i-lucide-arrow-right
  color: primary
  ---
  Get started
  :::

  :::u-button
  ---
  icon: i-simple-icons-github
  color: neutral
  variant: outline
  size: xl
  to: https://github.com/serversideup/benchkit-laravel
  target: _blank
  ---
  Star on GitHub
  :::

#default
<!-- SCREENSHOT_NEEDED: home-hero — the BenchKit app home screen (Start Benchmark + presets). Replace /images/benchkit-header.png below. -->
![BenchKit — understand true Laravel performance](/images/benchkit-header.png){.rounded-xl.border.border-white/10.mx-auto}
::

::u-page-section{class="dark:bg-black"}
#title
Benchmarks that reflect [the real world]{.text-primary-500}, not hello-world.

#description
Most PHP benchmarks measure the one thing that doesn't matter in production. BenchKit measures what does.

#features
  :::u-page-card
  ---
  icon: i-lucide-gauge
  orientation: vertical
  ---
  #title
  Web server load test

  #description
  Drives your app with oha across static, JSON, DB-read, and a simulated I/O route — reporting requests/sec and latency percentiles.
  :::

  :::u-page-card
  ---
  icon: i-lucide-cpu
  orientation: vertical
  ---
  #title
  Hardware & network

  #description
  Geekbench, disk I/O (fio), and a Cloudflare network test — so you know the box, not just the framework.
  :::

  :::u-page-card
  ---
  icon: i-lucide-database
  orientation: vertical
  ---
  #title
  PHP database (CRUD)

  #description
  phpbench-driven Create/Read/Update/Delete timing against SQLite by default — or your own MySQL/Postgres.
  :::

  :::u-page-card
  ---
  icon: i-lucide-zap
  orientation: vertical
  ---
  #title
  FrankenPHP vs PHP-FPM

  #description
  Run the same suite on each image and compare side by side. Watch worker mode's lead shrink as I/O grows — the real lesson.
  :::

  :::u-page-card
  ---
  icon: i-lucide-git-compare
  orientation: vertical
  ---
  #title
  Compare & share

  #description
  Diff any two runs, and share results to the community with one click.
  :::

  :::u-page-card
  ---
  icon: i-lucide-server
  orientation: vertical
  ---
  #title
  Self-hostable & honest

  #description
  Runs on your own hardware in a container. Every run discloses the exact config that produced it.
  :::
::

::u-page-section{class="dark:bg-black"}
#title
See what the [community]{.text-primary-500} is running.

#description
Real BenchKit results are shared every day on X with #BenchKit. Browse the live feed to see how different VPS providers, hardware, and PHP images stack up.

#links
  :::u-button
  ---
  to: https://x.com/search?q=%23BenchKit&f=live
  target: _blank
  size: xl
  color: primary
  trailing-icon: i-simple-icons-x
  ---
  Browse #BenchKit on X
  :::
::

::u-page-section{class="dark:bg-black"}
#title
Measure your own workload. [Trust the numbers.]{.text-primary-500}

#description
BenchKit is built to be accurate and honest about what it does — and doesn't — measure. Read the methodology before you draw conclusions.

#links
  :::u-button
  ---
  to: /docs/benchmarks
  size: xl
  variant: outline
  color: neutral
  trailing-icon: i-lucide-arrow-right
  ---
  Read the methodology
  :::
::
