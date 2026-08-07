---
seo:
  title: BenchKit — Measure real Laravel performance on your own server
  description: A self-hostable benchmark for Laravel. Run a realistic workload on any box, see what your hardware and PHP configuration actually do, and compare it against what the community is running.
---

::u-page-hero{class="dark:bg-black"}
---
orientation: vertical
---
#title
What is your server [actually]{.text-primary-500} doing?

#description
BenchKit is a self-hostable benchmark for Laravel. Run it on the box you care about and it measures hardware, network, web-server throughput, and database work, then shows you the exact configuration that produced every number. Use it to pick a host, or to find out what your tuning is really worth.

#links
  :::u-button
  ---
  to: /docs/getting-started
  size: xl
  trailing-icon: i-lucide-arrow-right
  color: primary
  ---
  Run your first benchmark
  :::

  :::u-button
  ---
  to: /results
  size: xl
  color: neutral
  variant: outline
  icon: i-lucide-gauge
  ---
  Browse community results
  :::

#default
![The BenchKit results screen](/images/benchkit-header.png){.rounded-xl.border.border-white/10.mx-auto}
::

::u-page-section{class="dark:bg-black"}
#title
Two reasons people [run it]{.text-primary-500}.

#description
The same benchmark answers a shopping question and a tuning question.

#features
  :::u-page-card
  ---
  icon: i-lucide-wallet
  orientation: vertical
  ---
  #title
  Work out what a box is worth

  #description
  Run the same suite on a $6 VPS and a $48 one and find out whether you get eight times the Laravel. Record what you pay and compare throughput per dollar against every other submitted run.
  :::

  :::u-page-card
  ---
  icon: i-lucide-flask-conical
  orientation: vertical
  ---
  #title
  Find out what your tuning does

  #description
  Turn OPcache off. Change the JIT buffer. Switch from PHP-FPM to worker mode. Re-run and diff. Most tuning advice is repeated rather than measured, and this is how you check it on your own hardware.
  :::
::

::u-page-section{class="dark:bg-black"}
#title
Four stages, and [an honest account]{.text-primary-500} of each.

#description
Every stage names the tool behind it, and the docs say plainly where each number stops being meaningful.

#features
  :::u-page-card
  ---
  icon: i-lucide-gauge
  orientation: vertical
  ---
  #title
  Web server load test

  #description
  `oha` drives four routes: static, JSON, a database read, and one dominated by a simulated outbound call. Reports throughput and latency percentiles per route.
  :::

  :::u-page-card
  ---
  icon: i-lucide-cpu
  orientation: vertical
  ---
  #title
  Hardware and network

  #description
  Geekbench and fio through YABS, plus a Cloudflare speed test. Tells you about the box itself, which is usually where the difference actually lives.
  :::

  :::u-page-card
  ---
  icon: i-lucide-database
  orientation: vertical
  ---
  #title
  Database operations

  #description
  phpbench times Create, Read, Update, and Delete against SQLite by default, or point it at your own MySQL or Postgres.
  :::

  :::u-page-card
  ---
  icon: i-lucide-git-compare
  orientation: vertical
  ---
  #title
  Compare two runs

  #description
  Diff any two runs side by side. Swap the image variation, change one setting, run it again, and read the delta.
  :::

  :::u-page-card
  ---
  icon: i-lucide-file-search
  orientation: vertical
  ---
  #title
  Every number is traceable

  #description
  Each result records the PHP version and SAPI, serving mode, OPcache and JIT settings, FPM pool size, database engine, and the exact load applied.
  :::

  :::u-page-card
  ---
  icon: i-lucide-server
  orientation: vertical
  ---
  #title
  Yours, and temporary

  #description
  Runs as a container on your own hardware. Nothing phones home. Destroy it when you're done.
  :::
::

::u-page-section{class="dark:bg-black"}
#title
See what [other people's]{.text-primary-500} servers do.

#description
Submitted runs land in a public gallery: the hardware, the configuration, the cost, and the numbers. Filter it down to your image variation and provider before you draw any conclusion, because a shared vCPU and a dedicated box are not the same test.

#links
  :::u-button
  ---
  to: /results
  size: xl
  color: primary
  trailing-icon: i-lucide-arrow-right
  ---
  Browse community results
  :::

  :::u-button
  ---
  to: /docs/community-results
  size: xl
  color: neutral
  variant: outline
  ---
  How submitting works
  :::
::

::u-page-section{class="dark:bg-black"}
#title
Read the [limitations]{.text-primary-500} before you quote a number.

#description
The load generator shares a box with the app. SQLite is the default database. One connection count is one point on a curve, and closed-loop tail latency is optimistic. None of that is hidden, because a benchmark you can't argue with isn't worth much.

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
