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

## Run the development environment
Run the following command to start the development environment.

```bash
spin up --build
```

You should now be able to access the development environment at [https://benchkit.dev.test](https://benchkit.dev.test).

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

## ⚡️ Adding or Improving our performance tests
This is where we really rely on the community to help us ensure our performance tests are realistic and as accurate as possible.

If you're looking to request a new test, you can do so by [submitting a feature request](https://github.com/serversideup/benchkit-laravel/discussions/2).

### If you'd like to build a test yourself

> [!IMPORTANT]  
> This content should be replaced by @danpastori on how users can build their own tests.
