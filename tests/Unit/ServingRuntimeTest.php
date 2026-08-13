<?php

namespace Tests\Unit;

use App\Actions\Specs\ServingRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Runtime detection reads environment variables and, for FPM, a pool file. The
 * parts that depend on the SAPI can only be exercised under a real web server
 * (see the /bench/env endpoint), but everything downstream of a signal can be
 * tested by supplying the signal.
 */
class ServingRuntimeTest extends TestCase
{
    protected array $touched = [];

    protected function tearDown(): void
    {
        foreach ($this->touched as $key) {
            putenv($key);
            unset($_SERVER[$key]);
        }

        parent::tearDown();
    }

    protected function withEnvironment(string $key, string $value): void
    {
        $this->touched[] = $key;
        putenv("{$key}={$value}");
    }

    /**
     * php_sapi_name() answers "fpm-fcgi" for nginx and Apache alike, so the
     * only thing that tells the two images apart at runtime is the
     * SERVER_SOFTWARE that both pass over FastCGI.
     */
    #[DataProvider('frontEnds')]
    public function test_the_front_end_web_server_is_read_from_server_software(string $software, ?string $name, ?string $version): void
    {
        $_SERVER['SERVER_SOFTWARE'] = $software;
        $this->touched[] = 'SERVER_SOFTWARE';

        $runtime = (new ServingRuntime)->execute();

        $this->assertSame($name, $runtime['front_end']);
        $this->assertSame($version, $runtime['front_end_version']);
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function frontEnds(): array
    {
        return [
            'nginx' => ['nginx/1.27.3', 'nginx', '1.27.3'],
            'apache' => ['Apache/2.4.62 (Debian)', 'apache', '2.4.62'],
            'caddy' => ['Caddy', 'caddy', null],
            'openlitespeed folds into litespeed' => ['OpenLiteSpeed/1.8.1', 'litespeed', '1.8.1'],
            'an unknown server passes through as reported' => ['tinyhttpd/0.1', 'tinyhttpd', '0.1'],
            'nothing recognisable is not guessed at' => ['   ', null, null],
        ];
    }

    /**
     * The name and the version are split so that a four-part version string
     * never reaches the community validator's privacy scan, which reads
     * anything shaped like 1.2.3.4 as an IP address.
     */
    public function test_the_front_end_version_is_never_published_inside_the_name(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.2.3.4';
        $this->touched[] = 'SERVER_SOFTWARE';

        $runtime = (new ServingRuntime)->execute();

        $this->assertSame('nginx', $runtime['front_end']);
        $this->assertStringNotContainsString('.', $runtime['front_end']);
    }

    /**
     * Octane exports this into the worker environment at runtime, which is why
     * it is read directly rather than through config — config may have been
     * cached during an image build, before any of this was true.
     */
    public function test_an_octane_worker_reports_worker_mode(): void
    {
        $this->withEnvironment('LARAVEL_OCTANE', '1');

        $this->assertSame('worker', (new ServingRuntime)->execute()['mode']);
    }

    /**
     * FrankenPHP's worker mode does not require Octane, so its own marker has
     * to count on its own.
     */
    public function test_frankenphp_worker_mode_is_recognised_without_octane(): void
    {
        $this->withEnvironment('FRANKENPHP_WORKER', '1');

        $this->assertSame('worker', (new ServingRuntime)->execute()['mode']);
    }

    /**
     * The serversideup images configure FPM through environment variables, and
     * a value set there is the one the operator chose — it wins over whatever
     * the pool file happens to still say.
     */
    public function test_fpm_settings_are_read_from_the_image_environment(): void
    {
        $this->withEnvironment('PHP_FPM_PM_CONTROL', 'static');
        $this->withEnvironment('PHP_FPM_PM_MAX_CHILDREN', '64');
        $this->withEnvironment('PHP_FPM_PM_MAX_REQUESTS', '500');

        $runtime = (new ServingRuntime)->execute();

        $this->assertSame('static', $runtime['settings']['pm']);
        $this->assertSame('64', $runtime['settings']['pm.max_children']);
        $this->assertSame('500', $runtime['settings']['pm.max_requests']);
        $this->assertSame(64, $runtime['workers']);
        $this->assertSame('pm.max_children', $runtime['workers_source']);
    }

    /**
     * A worker count is only comparable next to what it is a count of. Twenty
     * FPM children and eight FrankenPHP threads are both "workers" and are not
     * the same quantity.
     */
    public function test_a_worker_count_always_says_what_it_counts(): void
    {
        $runtime = (new ServingRuntime)->execute();

        if ($runtime['workers'] !== null) {
            $this->assertNotNull($runtime['workers_source']);
        }

        $this->assertContains($runtime['mode'], [null, 'worker', 'process-per-request']);
    }

    /**
     * A directive is a word, a number, or something like "10s". Anything else
     * means the file was not what we assumed, and a misread line is worse than
     * a missing one.
     */
    public function test_an_implausible_directive_value_is_dropped_rather_than_published(): void
    {
        $this->withEnvironment('PHP_FPM_PM_CONTROL', 'static; rm -rf /');

        $this->assertArrayNotHasKey('pm', (new ServingRuntime)->execute()['settings']);
    }
}
