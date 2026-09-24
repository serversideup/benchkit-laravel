<p align="center">
		<img src="https://raw.githubusercontent.com/serversideup/benchkit-laravel/main/.github/img/header.png" width="1280" alt="BenchKit Header"></a>
</p>
<p align="center">
	<a href="https://github.com/serversideup/benchkit-laravel/actions/workflows/action_publish-images-production.yml"><img alt="Build Status" src="https://img.shields.io/github/actions/workflow/status/serversideup/benchkit-laravel/.github%2Fworkflows%2Faction_publish-images-production.yml"></a>
	<a href="https://github.com/serversideup/benchkit-laravel/blob/main/LICENSE" target="_blank"><img src="https://badgen.net/github/license/serversideup/benchkit-laravel" alt="License"></a>
	<a href="https://github.com/sponsors/serversideup"><img src="https://badgen.net/badge/icon/Support%20Us?label=GitHub%20Sponsors&color=orange" alt="Support us"></a>
  <br />
  <a href="https://hub.docker.com/r/serversideup/benchkit-laravel/"><img alt="Docker Hub Pulls" src="https://img.shields.io/docker/pulls/serversideup/benchkit-laravel"></a>
  <a href="https://serversideup.net/discord"><img alt="Discord" src="https://img.shields.io/discord/910287105714954251?color=blueviolet"></a>
</p>

## Introduction

BenchKit is a free and open source Laravel application that shows you how performance changes across hosts, hardware, and PHP configurations. You run it on the server you want to measure, and it reports what that machine does with a Laravel workload.

It's a standalone app rather than a package you install. Spin it up, run a benchmark, share the result, then throw it away. Your own codebase is never involved.

### What a run measures

| Stage | Tool | What it measures |
| --- | --- | --- |
| Hardware | [YABS](https://github.com/masonr/yet-another-bench-script) | CPU and disk, through Geekbench and fio |
| Network | [cfspeedtest](https://github.com/code-inflation/cfspeedtest) | Latency and bandwidth to Cloudflare |
| Web server load | [oha](https://github.com/hatoo/oha) | Max throughput and response time, per route |
| PHP and database | [phpbench](https://phpbench.readthedocs.io/) | Timing for individual PHP and database operations |

Every run measures the same way — probe, sweep to the top, then hold a steady rate below it — which is what makes it meaningful to put your numbers next to somebody else's.

[How each stage works →](https://serversideup.net/open-source/benchkit/docs/benchmarks)

## Quick start

You need a server and Docker. Nothing else.

```bash
# Install Docker (skip if you already have it)
curl -fsSL https://get.docker.com | bash

# Start BenchKit
docker run -p 80:8080 \
  -v benchkit-runs:/var/www/html/storage/app/runs \
  serversideup/benchkit-laravel:latest
```

Open `http://<your-server-ip>/` in your browser and press **Start Benchmark**.

A few things worth knowing:

- **The load test runs from a second machine by default.** Start Benchmark hands you a one-line command to paste there. No second machine? Pick **Test from this server instead** in the same dialog.
- **The run belongs to the server**, not to your browser. Close the tab, reload the page, or follow along from your phone without disturbing it.
- **The volume holds your run history.** Without it, your results disappear with the container.
- **Pick Quick or Full** in the settings drawer before you start. BenchKit estimates how long your choice will take.

[Full quick start guide →](https://serversideup.net/open-source/benchkit/docs/getting-started/quick-start)

## Pick your web server

The same application ships on three web servers, so any difference between two runs comes from the server rather than the code. The tag you pull decides which one you get.

| Variation | How PHP is served | Tag |
| --- | --- | --- |
| **FPM-NGINX** | nginx hands PHP requests to PHP-FPM | `latest` |
| **FrankenPHP** | One binary serves PHP directly | `frankenphp` |
| **FPM-Apache** | Apache hands PHP requests to PHP-FPM | `fpm-apache` |

Run each one on the same machine, then open the runs side by side in **Compare**. FrankenPHP also runs in Octane worker mode from the same image, which is the cleanest comparison the tool can make.

[Choosing a variation →](https://serversideup.net/open-source/benchkit/docs/image-variations)

## Share your results

The [community results gallery](https://serversideup.net/open-source/benchkit/results) collects real runs across different hosts, hardware, and PHP configurations. Adding yours is how the next person finds a machine like the one they're considering.

Press **Submit result** when a run finishes. BenchKit shows you the exact document first, then opens a pre-filled GitHub issue. A bot validates it and opens a pull request. Submitting is optional, and BenchKit never sends your results anywhere on its own.

You can also share a run as an image on X with [#BenchKit](https://x.com/search?q=%23BenchKit&f=live).

[What gets published, and what never does →](https://serversideup.net/open-source/benchkit/docs/community-results)

## Documentation

📚 **Full documentation lives at [serversideup.net/open-source/benchkit](https://serversideup.net/open-source/benchkit).**

- **[Running from source](https://serversideup.net/open-source/benchkit/docs/getting-started/running-from-source)** — for Laravel Cloud, Railway, or any host that takes a repository instead of a container.
- **[Adding a real database](https://serversideup.net/open-source/benchkit/docs/configuration/adding-databases)** — point BenchKit at MySQL, MariaDB, or Postgres instead of the default SQLite.
- **[Customizing the image](https://serversideup.net/open-source/benchkit/docs/configuration/customizing-the-image)** — change OPcache, JIT, and the FPM pool with environment variables.
- **[Reading your results](https://serversideup.net/open-source/benchkit/docs/benchmarks/reading-your-results)** — what a number tells you, and the limits that apply to it.
- **[Testing from another machine](https://serversideup.net/open-source/benchkit/docs/benchmarks/testing-from-another-machine)** — point oha at the load test endpoints from another machine.
- **[FAQ](https://serversideup.net/open-source/benchkit/docs/faq)** — the questions we get most often.

> The documentation source lives in the [`docs/`](./docs) directory — a standalone Nuxt site deployed to Cloudflare Pages.

## Powered by Spin Pro
Spin is an [open source tool built by Server Side Up](https://serversideup.net/open-source/spin/) to help you run Docker from development → production. Spin is language agnostic, so you can use it with any language but we also provide official templates. This project was powered by Spin Pro, which offers additional features for Laravel power users.

<p align="center">
		<a href="https://getspin.pro/?ref=benchkit-laravel"><img src="https://raw.githubusercontent.com/serversideup/benchkit-laravel/main/.github/img/spin-pro.png" width="720" alt="Powered by Spin Pro"></a>
</p>

## Resources
- **[Discord](https://serversideup.net/discord)** for friendly support from the community and the team.
- **[GitHub](https://github.com/serversideup/benchkit-laravel)** for source code, bug reports, and project management.
- **[Get Professional Help](https://serversideup.net/professional-support)** - Get video + screen-sharing help directly from the core contributors.

## Contributing
As an open-source project, we strive for transparency and collaboration in our development process. We greatly appreciate any contributions members of our community can provide. Whether you're fixing bugs, proposing features, improving documentation, or spreading awareness - your involvement strengthens the project. Please review our [contribution guidelines](./CONTRIBUTING.md) and [code of conduct](./.github/code_of_conduct.md) to understand how we work together respectfully.

- **Bug Report**: If you're experiencing an issue while using these images, please [create an issue](https://github.com/serversideup/benchkit-laravel/issues/new/choose).
- **Feature Request**: Make this project better by [submitting a feature request](https://github.com/serversideup/benchkit-laravel/discussions/2).
- **Documentation**: Improve our documentation by editing the [`docs/content`](./docs/content) directory.
- **Community Support**: Help others on [GitHub Discussions](https://github.com/serversideup/benchkit-laravel/discussions) or [Discord](https://serversideup.net/discord).
- **Security Report**: Report critical security issues via [our responsible disclosure policy](https://www.notion.so/Responsible-Disclosure-Policy-421a6a3be1714d388ebbadba7eebbdc8).

Need help getting started? Join our Discord community and we'll help you out!

<a href="https://serversideup.net/discord"><img src="https://serversideup.net/wp-content/themes/serversideup/images/open-source/join-discord.svg" title="Join Discord"></a>

<!-- serversideup-sponsors -->
## Our Sponsors
All of our software is free and open to the world. None of this can be brought to you without the financial backing of our sponsors.

<p align="center"><a href="https://github.com/sponsors/serversideup"><img src="https://521public.s3.amazonaws.com/serversideup/sponsors/sponsor-box.png" alt="Become a sponsor"></a></p>

### Platinum Sponsors
<a href="https://sevalla.com"><img src="https://serversideup.net/sponsors/sevalla.png" alt="Sevalla" width="500px"></a>

### Silver Sponsors
<a href="https://giga-infosystems.com"><img src="https://serversideup.net/sponsors/giga-infosystems.png" alt="GiGa infosystems" width="200px"></a>

### Infrastructure Sponsors
These companies give us free access to the tools and infrastructure we use to build, test, and ship our open source projects. Their support helps our entire community.

<a href="https://depot.dev"><img src="https://serversideup.net/sponsors/depot.png" alt="Depot" width="250px"></a>&nbsp;&nbsp;<a href="https://hub.docker.com/u/serversideup"><img src="https://serversideup.net/sponsors/docker.png" alt="Docker" width="250px"></a>
<!-- serversideup-sponsors -->

<!-- serversideup-about -->
## About Us
We're [Dan](https://x.com/danpastori) and [Jay](https://x.com/jaydrogers) - a two-person team with a passion for open source products. We created [Server Side Up](https://serversideup.net) to help share what we learn.

<div align="center">

| <div align="center">Dan Pastori</div> | <div align="center">Jay Rogers</div> |
| --- | --- |
| <div align="center"><a href="https://x.com/danpastori"><img src="https://serversideup.net/wp-content/uploads/2023/08/dan.jpg" title="Dan Pastori" width="150px"></a><br /><a href="https://x.com/danpastori"><img src="https://serversideup.net/logos/x.svg" title="X" width="24px"></a><a href="https://github.com/danpastori"><img src="https://serversideup.net/logos/github.svg" title="GitHub" width="24px"></a></div> | <div align="center"><a href="https://x.com/jaydrogers"><img src="https://serversideup.net/wp-content/uploads/2023/08/jay.jpg" title="Jay Rogers" width="150px"></a><br /><a href="https://x.com/jaydrogers"><img src="https://serversideup.net/logos/x.svg" title="X" width="24px"></a><a href="https://github.com/jaydrogers"><img src="https://serversideup.net/logos/github.svg" title="GitHub" width="24px"></a></div> |

</div>

### Find us at:

* **📖 [Blog](https://serversideup.net)** - Get the latest guides and free courses on all things web/mobile development.
* **🙋 [Community](https://community.serversideup.net)** - Get friendly help from our community members.
* **🤵‍♂️ [Get Professional Help](https://serversideup.net/professional-support)** - Get video + screen-sharing support from the core contributors.
* **💻 [GitHub](https://github.com/serversideup)** - Check out our other open source projects.
* **📫 [Newsletter](https://serversideup.net/subscribe)** - Skip the algorithms and get quality content right to your inbox.
* **🐥 [X (Twitter)](https://x.com/serversideup)** - You can also follow [Dan](https://x.com/danpastori) and [Jay](https://x.com/jaydrogers).
* **❤️ [Sponsor Us](https://github.com/sponsors/serversideup)** - Please consider sponsoring us so we can create more helpful resources.

## Our Products
If you appreciate this project, be sure to check out our other projects.

### 🛠️ Premium
- **[Self-Host Pro](https://selfhostpro.com)**: Sell self-hosted software in minutes.
- **[Bugflow](https://bugflow.io)**: Get product feedback directly in GitHub, GitLab, and more.
- **[Spin Pro](https://getspin.pro)**: Production-ready Docker templates for shipping quickly.

### 🌍 Open Source
- **[serversideup/php](https://serversideup.net/open-source/docker-php/)**: Supercharged PHP Docker images, based off the official PHP images. <!-- repo:serversideup/docker-php -->
- **[Spin](https://serversideup.net/open-source/spin/)**: Docker Simplified. Deploy Anywhere. Zero Downtime. Any OS. <!-- repo:serversideup/spin -->
- **[Financial Freedom](https://serversideup.net/open-source/financial-freedom/)**: Open source alternative to Mint, YNAB, and more. <!-- repo:serversideup/financial-freedom -->
- **[AmplitudeJS](https://serversideup.net/open-source/amplitudejs/)**: Customize the design of any element of the HTML5 Audio Player. <!-- repo:521dimensions/amplitudejs -->
- **[webext-bridge](https://serversideup.net/open-source/webext-bridge/)**: Messaging in Web Extensions made easy. Batteries included. <!-- repo:serversideup/webext-bridge -->
- **[serversideup/ansible](https://github.com/serversideup/docker-ansible)**: Run Ansible anywhere with a lightweight and powerful Docker image. <!-- repo:serversideup/docker-ansible -->

### 📚 Books
- **[Building Browser Extensions](https://serversideup.net/products/building-multi-platform-browser-extensions/)**: Build browser extensions for Firefox, Chrome, and more.
- **[Ultimate Guide To Building APIs & SPAs](https://serversideup.net/products/ultimate-guide-to-building-apis-and-spas-with-laravel-and-nuxt3/)**: Build web and mobile apps from the same codebase.
<!-- serversideup-about -->
