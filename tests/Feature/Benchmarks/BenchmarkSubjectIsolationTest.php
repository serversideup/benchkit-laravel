<?php

namespace Tests\Feature\Benchmarks;

use App\Actions\Results\PhpBenchmarkResults;
use App\Benchmarks\Php\BaseBenchmark;
use PhpBench\Attributes as Bench;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * phpbench times the whole subject method, so whatever else is in there is
 * reported as the cost of the operation. That has gone wrong twice — subjects
 * rebuilding their own state, and subjects reading the clock — and neither
 * failure is visible in the resulting number, which is exactly why it needs a
 * test rather than review.
 *
 * These read the source of every measured body rather than running it: the
 * property is "this code isn't in there", and no timing assertion can check
 * that without being flaky on a busy machine.
 */
class BenchmarkSubjectIsolationTest extends TestCase
{
    /**
     * Reading the clock inside a measured body times PHP's date handling
     * alongside the query. It is cheap on a healthy host and was not cheap on
     * every host: create and update, the two subjects that wrote timestamps,
     * reported up to 100x their real cost while read and delete — which touch
     * no timestamps — were unaffected, so the four stopped being comparable
     * with each other or across machines.
     */
    private const CLOCK_READS = [
        'now()' => '/(?<![\w>])now\s*\(/',
        'Carbon::' => '/\bCarbon::/',
        'new DateTime' => '/\bnew\s+\\\\?DateTime/',
        'time()' => '/(?<![\w>])time\s*\(/',
        'microtime()' => '/(?<![\w>])microtime\s*\(/',
        'date()' => '/(?<![\w>])date\s*\(/',
    ];

    #[DataProvider('subjects')]
    public function test_a_measured_body_never_reads_the_clock(string $class, string $subject): void
    {
        $body = $this->sourceOf($class, $subject);

        foreach (self::CLOCK_READS as $call => $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $body,
                "{$class}::{$subject}() calls {$call} inside the measured body, so it times PHP date handling alongside the query. ".
                'Resolve the value in setUp() and bind it — see BaseBenchmark::$now.'
            );
        }
    }

    /**
     * A subject that creates or drops its own table is timing schema changes.
     * Both belong in setUp(), which phpbench does not measure.
     */
    #[DataProvider('subjects')]
    public function test_a_measured_body_never_builds_its_own_schema(string $class, string $subject): void
    {
        $body = $this->sourceOf($class, $subject);

        foreach (['Schema::', 'resetTestTable', 'dropTestTable'] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $body,
                "{$class}::{$subject}() changes the schema inside the measured body; move it to setUp()."
            );
        }
    }

    /**
     * The four headline subjects are the ones the run page puts on a shared
     * bar scale, so a rename that silently drops one to null is worth failing
     * over rather than rendering as a missing tile.
     */
    public function test_every_headline_subject_exists_and_is_a_phpbench_subject(): void
    {
        foreach (PhpBenchmarkResults::headlineSubjects() as $operation => $spec) {
            $class = "App\\Benchmarks\\Php\\Database\\{$spec['benchmark']}";

            $this->assertTrue(method_exists($class, $spec['subject']), "The {$operation} headline points at {$class}::{$spec['subject']}(), which does not exist.");
            $this->assertNotEmpty(
                (new ReflectionMethod($class, $spec['subject']))->getAttributes(Bench\Revs::class),
                "{$class}::{$spec['subject']}() is not attributed as a phpbench subject."
            );
        }
    }

    /**
     * All four tiles are drawn against one scale, so they have to run the same
     * number of statements over the same number of records. Anything else is a
     * chart comparing different work.
     */
    public function test_the_headline_operations_describe_the_same_unit_of_work(): void
    {
        $specs = PhpBenchmarkResults::headlineSubjects();

        $this->assertCount(1, array_unique(array_column($specs, 'statements')));
        $this->assertCount(1, array_unique(array_column($specs, 'records')));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function subjects(): array
    {
        $cases = [];

        // Data providers run before the application boots, so the path is
        // resolved from this file rather than from app_path().
        foreach (Finder::create()->files()->in(self::appPath().'/Benchmarks/Php')->name('*.php') as $file) {
            $class = self::classFor($file);

            if (! class_exists($class) || ! is_subclass_of($class, BaseBenchmark::class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (str_starts_with($method->getName(), 'bench') && $method->getDeclaringClass()->getName() === $class) {
                    $cases[$class.'::'.$method->getName()] = [$class, $method->getName()];
                }
            }
        }

        return $cases;
    }

    protected static function appPath(): string
    {
        return dirname(__DIR__, 3).'/app';
    }

    protected static function classFor(SplFileInfo $file): string
    {
        $relative = str_replace([self::appPath().'/', '.php', '/'], ['', '', '\\'], $file->getPathname());

        return 'App\\'.$relative;
    }

    protected function sourceOf(string $class, string $subject): string
    {
        $method = new ReflectionMethod($class, $subject);
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
