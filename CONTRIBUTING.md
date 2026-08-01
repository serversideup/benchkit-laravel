# Contributing Guide
This software is heavily driven by the community. If you'd like to make this product better, we encourage you to submit a pull request. Regardless of your level of participation in this project, we enforce that everyone follows our [Community Guidelines](https://serversideup.net/guidelines/) and [How to Ask for Help Guide](https://serversideup.net/ask-for-help/).

> [!IMPORTANT]  
> Before you start, we'd like to highlight a few things to make your contribution experience as smooth as possible.

## 🐛 Reporting Issues
If you found a bug in our code, please open an issue on GitHub.

[Create an issue →](https://github.com/serversideup/benchkit-laravel/issues/new/choose)

## 💡 Feature Requests
Feature requests must follow our template and have a clear description of the feature.

[Learn how to request a feature →](https://github.com/serversideup/benchkit-laravel/discussions/2)

## 👨‍💻 Running your own development environment
We use our own open source tool called [Spin](https://serversideup.net/open-source/spin/) to run your development environment. We're able to replicate everything from development to production because our entire process is based on Docker.

### Make sure you have Docker installed
You can verify Docker is working by running:

```bash
docker --version && docker ps
```

#### If you need help installing Docker
We put together helpful guides on how to install Docker on [Spin's documentation](https://serversideup.net/open-source/spin/docs). Or if you'd like, you can reference the [official Docker docs](https://docs.docker.com/get-docker/) on how to install Docker too.

## Install Spin (optional)
Spin is included as a composer dependency, so it is possible to use it without installing it.

Example composer command to use Spin:

```bash
./vendor/bin/spin up
```
But if you want to save yourself the hassle of running the command every time, you can install Spin globally.

[Read the docs on how to install Spin →](https://serversideup.net/open-source/spin/docs/)

> [!WARNING]  
> From this point forward, we're assuming you have Spin installed globally. So if you choose to not install it, you'll need to run the commands with `./vendor/bin/spin` instead of just `spin`.

## Clone the repository
Before you get started, you'll need to clone the repository to your local machine.

## Ensure you have your `/etc/hosts` file updated
Run the following command to ensure you have your `/etc/hosts` file updated.

```bash
sudo nano /etc/hosts
```

Add the following lines to your `/etc/hosts` file.

```
127.0.0.1 benchkit.dev.test
127.0.0.1 vite.dev.test
```

Save the file and you can test that `ping benchkit.dev.test` is returning `127.0.0.1` if everything is working.

## If you want a trusted SSL locally (optional)
If you want to have a trusted SSL locally, you'll need to install our Development CA certificate.

[Learn how to install the Development CA certificate →](https://serversideup.net/ca/)

## Copy the .env.example file
Copy the .env.example file to .env.

```bash
cp .env.example .env
```

## Install dependencies
Run the following command to install the dependencies.

```bash
## PHP
spin run -e AUTORUN_ENABLED=false php composer install

## Node
spin run -e AUTORUN_ENABLED=false node yarn install
```

## Generate the application key
Run the following command to generate the application key.

```bash
spin run -e AUTORUN_ENABLED=false php artisan key:generate
```

## Run migrations
Run the following command to run the migrations.

```bash
spin run php artisan migrate
```

## Run the development environment
Run the following command to start the development environment.

```bash
spin up --build
```

You should now be able to access the development environment at [https://benchkit.dev.test](https://benchkit.dev.test).

> [!TIP]
> A benchmark run belongs to the server, not to the browser tab that started it. Closing the tab or reloading does not stop the run — use the Cancel button, which works from any tab. Only one run can happen at a time (they compete for the same hardware), and a run whose process dies is detected automatically. If the app is somehow still stuck on a run, clear it with `spin exec php php artisan benchmark:clear-run`.

## Changing the PHP server
This project is very unique in where there are plenty of use cases where we want to test different PHP servers. Thankfully we're using Docker and [serversideup/php](https://serversideup.net/open-source/php/), so this actually makes it very easy to change.

By default, the image starts with the `fpm-nginx` variation. If you want to change it, you just need to set any of these build arguments:

1. `PHP_VARIATION`
2. `PHP_VERSION`
3. `BASE_OS`

Here's an example of how we would run FrankenPHP:

```bash
PHP_VARIATION=frankenphp spin up --build
```

You can even set the PHP version with `PHP_VERSION=8.4` or the base operating system with `BASE_OS=trixie`. The input must match what's available on [serversideup/php](https://hub.docker.com/r/serversideup/php/tags).

Example of changing versions:
```bash
PHP_VERSION=8.4 spin up --build
```

## How the published Docker images are built
The `action_publish-images-*.yml` workflows call the reusable `service_docker-build-and-publish.yml`, which builds one image per PHP version × variation × OS. The version matrix is generated at build time by `scripts/configure-php-versions.sh` — read that script for the exact rules. In short, it pulls the available versions from [serversideup/docker-php](https://github.com/serversideup/docker-php) and drops anything below the `require.php` floor in `composer.json`, so the supported range follows `composer.json` and `:latest` tracks the newest stable version automatically.

Only `configure-php-versions.sh` is committed. Its helpers — `scripts/assemble-docker-tags.sh`, `scripts/generate-matrix.sh`, and `scripts/conf/php-versions.yml` — are fetched or generated from upstream during CI and are intentionally gitignored; don't commit them.

## ⚡️ Adding or Improving our performance tests
This is where we really rely on the community to help us ensure our performance tests are realistic and as accurate as possible.

If you're looking to request a new test, you can do so by [submitting a feature request](https://github.com/serversideup/benchkit-laravel/discussions/2).

### If you'd like to build a test yourself

Benchkit uses a standardized benchmarking suite called [PHPBench](https://phpbench.readthedocs.io/en/latest/installing.html). It operates very similar to PHP Unit testing. To contribute your own benchmark, I'd familiarize yourself with PHP Bench (AI is very helpful).

A few notes on structure.

#### Laravel is Pre-Loaded
Since Benchkit relies heavily on testing Laravel applications, I've bootstrapped Laravel when starting Benchkit tests. In the root directory there's a file `phpbench-bootstrap.php`. This runs before every test so if you want to bench a specific Laravel function, you can!

#### Where Should I Place My New Benchmark?
All benchmarks for PHP are located in `app/Benchmarks/Php`. In that directory, there are two sub-directories:

1. Database
2. Performance

The `Database` directory contains all PHP Benchmarks that require a database. The `Performance` directory contains all PHP Benchmarks that are isolated to strictly the PHP docker container. If your benchmark requires a database query or interaction, place it in the `Database` directory. If you are isolating a PHP method to bench, place it in the `Performance` directory.

If you have questions or want to contribute a whole new set of benchmarks, feel free to make a PR. I'd love to collaborate and add some extended benchmarks! We plan on making an API system to bench full responses from remote requests very soon!

#### Database Tests Must Extend BaseBenchmark.php
When testing the database, there needs to be a way to reset the database before each test. If you are writing your own, entirely different database test, you will need to extend the `BaseBenchmark.php` class. This will clear and reset the database each time you run a test.

If you have a test you want run that matches the standard CRUD of a database, add it in the PHP Bench format to either:
- InsertBenchmark.php
- DeleteBenchmark.php
- QueryBenchmark.php
- UpdateBenchmark.php