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
BenchKit for Laravel is an open source and containerized Laravel application to help you understand how your host and Laravel configurations are actually performing. We put together this application that runs realistic benchmark tests through Laravel so you can understand the actual performance of your setup. Running BenchKit for Laravel also helps you build faster and more reliable applications.

## View community results
This application runs completely decentralized and community results are encourage to be shared on X (Twitter) with the hashtag of `#BenchKit and #Laravel`. View the community results below:

- [View community results on X (Twitter) →](https://x.com/search?q=%23benchkit%20%23laravel&src=typed_query&f=live)
- [View community results on Bluesky →](https://bsky.app/search?q=laravel+benchkit)

## Powered by Spin Pro
Spin is an [open source tool built by Server Side Up](https://serversideup.net/open-source/spin/) to help you run Docker from development → production. Spin is language agnostic, so you can use it with any language but we also provide official templates. This project was powered by Spin Pro, which offers additional features for Laravel power users.

<p align="center">
		<a href="https://getspin.pro/?ref=benchkit-laravel"><img src="https://raw.githubusercontent.com/serversideup/benchkit-laravel/main/.github/img/spin-pro.png" width="720" alt="Powered by Spin Pro"></a>
</p>

## Usage

> [!WARNING]  
> 👷‍♂️ **This project is actively under development.**
>
> Please refrain from opening issues or PRs until we have a few things in order. 😃

Usage instructions will be added soon.

## 🚀 Need help optimizing your Laravel app for maximum performance?

**Deliver exceptional Laravel applications without the hiring headache.**

The creators of BenchKit ([Dan](https://x.com/danpastori) and [Jay](https://x.com/jaydrogers)) are available for hire to help you build faster, more scalable Laravel applications. With 30+ years of combined experience, we offer a unique development-first approach that maximizes productivity and minimizes overhead.

### Why work with us?

**🎯 Performance & Scalability Experts**
- Complete-stack Laravel development (frontend, backend, database optimization)
- Advanced CI/CD pipeline development with GitHub Actions
- Self-hosted application deployment and server management
- Database design that scales to millions of records

**💰 Predictable Pricing, Exceptional Value**
- Fixed monthly subscription (40%+ savings vs traditional hiring)
- No hourly billing surprises or scope creep
- Start seeing results in 48 hours, not months
- Cancel anytime with 7-day money-back guarantee

**⚡ Maximum Productivity**
- 90%+ time dedicated to actual development (not meetings)
- No daily standups or Slack interruptions
- Asynchronous communication through GitHub issues
- Results in days, not weeks

**🛠️ Complete Laravel Ecosystem**
- Laravel, Vue.js, Inertia.js, Nuxt.js
- Docker, CI/CD, and DevOps automation
- Mobile apps with CapacitorJS
- UI/UX design with technical implementation expertise

### Ready to accelerate your Laravel development?

Whether you're launching your first product or scaling an established platform, we'll help you ship faster while maintaining the highest code quality standards.

**[💬 Discuss Your Project →](https://serversideup.net/hire-us)**

## FAQs
Here's common questions to help you understand how this application works.
<details>
  <summary>How does this application work?</summary>

  > This application is a dedicated Laravel application, built as "container first" so you can easily benchmark your VPS or hosting provider. We use [Yet Another Bench Script](https://github.com/masonr/yet-another-bench-script) to test your hardware, [cfspeed](https://github.com/makotom/cfspeed) to test your network to CloudFlare, and then we run a series of CRUD tests to benchmark how your application performs.

</details>
<details>
  <summary>Do I install this as a Laravel package?</summary>
  
  > Nope! This is a standalone application that is intended to be run, then easily destroyed once you're done with it.
</details>
<details>
  <summary>Can I run this application without Docker?</summary>

  > Yes! Although we do provide a Docker image, you can also clone this repository and run the application on your own PaaS (like Laravel Cloud or DigitalOcean Apps). There are dependencies that need to be installed (like [Yet Another Bench Script](https://github.com/masonr/yet-another-bench-script) and [cfspeed](https://github.com/makotom/cfspeed)), but we will be sure to add more documentation once we get the Docker version up and running first.
</details>
<details>
  <summary>How can I share my results with the community?</summary>

  > When you run the benchmark, you'll have an option to share your results on X (Twitter) with the hashtag of [#BenchKit and #Laravel](https://x.com/search?q=%23benchkit%20%23laravel&src=typed_query&f=live).
</details>

## Resources
- **[Discord](https://serversideup.net/discord)** for friendly support from the community and the team.
- **[GitHub](https://github.com/serversideup/benchkit-laravel)** for source code, bug reports, and project management.
- **[Get Professional Help](https://serversideup.net/professional-support)** - Get video + screen-sharing help directly from the core contributors.

## Contributing
As an open-source project, we strive for transparency and collaboration in our development process. We greatly appreciate any contributions members of our community can provide. Whether you're fixing bugs, proposing features, improving documentation, or spreading awareness - your involvement strengthens the project. Please review our [contribution guidelines](./CONTRIBUTING.md) and [code of conduct](./.github/code_of_conduct.md) to understand how we work together respectfully.

- **Bug Report**: If you're experiencing an issue while using these images, please [create an issue](https://github.com/serversideup/benchkit-laravel/issues/new/choose).
- **Feature Request**: Make this project better by [submitting a feature request](https://github.com/serversideup/benchkit-laravel/discussions/1).
- **Documentation**: Improve our documentation by [submitting a documentation change](./docs/README.md).
- **Community Support**: Help others on [GitHub Discussions](https://github.com/serversideup/benchkit-laravel/discussions) or [Discord](https://serversideup.net/discord).
- **Security Report**: Report critical security issues via [our responsible disclosure policy](https://www.notion.so/Responsible-Disclosure-Policy-421a6a3be1714d388ebbadba7eebbdc8).

Need help getting started? Join our Discord community and we'll help you out!

<a href="https://serversideup.net/discord"><img src="https://serversideup.net/wp-content/themes/serversideup/images/open-source/join-discord.svg" title="Join Discord"></a>

## Our Sponsors
All of our software is free an open to the world. None of this can be brought to you without the financial backing of our sponsors.

<p align="center"><a href="https://github.com/sponsors/serversideup"><img src="https://521public.s3.amazonaws.com/serversideup/sponsors/sponsor-box.png" alt="Sponsors"></a></p>

### Black Level Sponsors
<a href="https://sevalla.com"><img src="https://serversideup.net/wp-content/uploads/2024/10/sponsor-image.png" alt="Sevalla" width="546px"></a>

#### Bronze Sponsors
<!-- bronze -->No bronze sponsors yet. <a href="https://github.com/sponsors/serversideup">Become a sponsor →</a><!-- bronze -->

#### Special Infrastructure Sponsors
This project takes an incredible amount of computing power to build and maintain over 8,000 different docker image tags. We're extremely grateful for the following sponsors who help bring the power to ship more PHP.

<a href="https://depot.dev/"><img src="https://serversideup.net/sponsors/depot.png" alt="Depot" width="250px"></a>&nbsp;&nbsp;<a href="https://hub.docker.com/u/serversideup"><img src="https://serversideup.net/sponsors/docker.png" alt="Docker" width="250px"></a>

#### Individual Supporters
<!-- supporters --><a href="https://github.com/GeekDougle"><img src="https://github.com/GeekDougle.png" width="40px" alt="GeekDougle" /></a>&nbsp;&nbsp;<a href="https://github.com/MaltMethodDev"><img src="https://github.com/MaltMethodDev.png" width="40px" alt="MaltMethodDev" /></a>&nbsp;&nbsp;<a href="https://github.com/bananabrann"><img src="https://github.com/bananabrann.png" width="40px" alt="bananabrann" /></a>&nbsp;&nbsp;<!-- supporters -->

## About Us
We're [Dan](https://x.com/danpastori) and [Jay](https://x.com/jaydrogers) - a two person team with a passion for open source products. We created [Server Side Up](https://serversideup.net) to help share what we learn.

<div align="center">

| <div align="center">Dan Pastori</div>                  | <div align="center">Jay Rogers</div>                                 |
| ----------------------------- | ------------------------------------------ |
| <div align="center"><a href="https://x.com/danpastori"><img src="https://serversideup.net/wp-content/uploads/2023/08/dan.jpg" title="Dan Pastori" width="150px"></a><br /><a href="https://x.com/danpastori"><img src="https://serversideup.net/wp-content/themes/serversideup/images/open-source/twitter.svg" title="Twitter" width="24px"></a><a href="https://github.com/danpastori"><img src="https://serversideup.net/wp-content/themes/serversideup/images/open-source/github.svg" title="GitHub" width="24px"></a></div>                        | <div align="center"><a href="https://x.com/jaydrogers"><img src="https://serversideup.net/wp-content/uploads/2023/08/jay.jpg" title="Jay Rogers" width="150px"></a><br /><a href="https://x.com/jaydrogers"><img src="https://serversideup.net/wp-content/themes/serversideup/images/open-source/twitter.svg" title="Twitter" width="24px"></a><a href="https://github.com/jaydrogers"><img src="https://serversideup.net/wp-content/themes/serversideup/images/open-source/github.svg" title="GitHub" width="24px"></a></div>                                       |

</div>

### Find us at:

* **📖 [Blog](https://serversideup.net)** - Get the latest guides and free courses on all things web/mobile development.
* **🙋 [Community](https://community.serversideup.net)** - Get friendly help from our community members.
* **🤵‍♂️ [Get Professional Help](https://serversideup.net/professional-support)** - Get video + screen-sharing support from the core contributors.
* **💻 [GitHub](https://github.com/serversideup)** - Check out our other open source projects.
* **📫 [Newsletter](https://serversideup.net/subscribe)** - Skip the algorithms and get quality content right to your inbox.
* **🐥 [Twitter](https://x.com/serversideup)** - You can also follow [Dan](https://x.com/danpastori) and [Jay](https://x.com/jaydrogers).
* **❤️ [Sponsor Us](https://github.com/sponsors/serversideup)** - Please consider sponsoring us so we can create more helpful resources.

## Our products
If you appreciate this project, be sure to check out our other projects.

### 📚 Books
- **[The Ultimate Guide to Building APIs & SPAs](https://serversideup.net/ultimate-guide-to-building-apis-and-spas-with-laravel-and-nuxt3/)**: Build web & mobile apps from the same codebase.
- **[Building Multi-Platform Browser Extensions](https://serversideup.net/building-multi-platform-browser-extensions/)**: Ship extensions to all browsers from the same codebase.

### 🛠️ Software-as-a-Service
- **[Bugflow](https://bugflow.io/)**: Get visual bug reports directly in GitHub, GitLab, and more.
- **[SelfHost Pro](https://selfhostpro.com/)**: Connect Stripe or Lemonsqueezy to a private docker registry for self-hosted apps.

### 🌍 Open Source
- **[serversideup/php](https://github.com/serversideup/docker-php)**: Production-ready PHP Docker images optimized for Laravel.
- **[AmplitudeJS](https://521dimensions.com/open-source/amplitudejs)**: Open-source HTML5 & JavaScript Web Audio Library.
- **[Spin](https://serversideup.net/open-source/spin/)**: Laravel Sail alternative for running Docker from development → production.