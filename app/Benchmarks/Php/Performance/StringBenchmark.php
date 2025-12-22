<?php

namespace App\Benchmarks\Php\Performance;

use Illuminate\Support\Str;
use PhpBench\Attributes as Bench;

class StringBenchmark
{
    private string $shortString;
    private string $longString;
    private string $htmlString;
    private string $unicodeString;
    private array $stringArray;

    public function __construct()
    {
        $this->shortString = 'The quick brown fox jumps over the lazy dog';
        
        // Generate a long string (50KB)
        $this->longString = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 1000);
        
        $this->htmlString = '<div class="container"><p>Hello <strong>World</strong>!</p></div>' . str_repeat('<span>Content</span>', 100);
        
        $this->unicodeString = 'Hello 世界 🌍 Привет مرحبا';
        
        $this->stringArray = array_fill(0, 1000, 'test string for implode');
    }

    /**
     * Benchmark regex replacement on long string
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'regex'])]
    public function benchRegexReplace(): void
    {
        preg_replace('/ipsum/', 'IPSUM', $this->longString);
    }

    /**
     * Benchmark multiple regex operations
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'regex'])]
    public function benchMultipleRegex(): void
    {
        $text = $this->longString;
        $text = preg_replace('/lorem/i', 'LOREM', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    }

    /**
     * Benchmark string replacement
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'replace'])]
    public function benchStrReplace(): void
    {
        str_replace('ipsum', 'IPSUM', $this->longString);
    }

    /**
     * Benchmark substr operations
     */
    #[Bench\Revs(50000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'substr'])]
    public function benchSubstr(): void
    {
        for ($i = 0; $i < 100; $i++) {
            substr($this->longString, $i, 100);
        }
    }

    /**
     * Benchmark Laravel Str::slug
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'slug'])]
    public function benchStrSlug(): void
    {
        Str::slug($this->shortString);
    }

    /**
     * Benchmark Laravel Str::camel
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'camel'])]
    public function benchStrCamel(): void
    {
        Str::camel('this_is_a_snake_case_string_for_testing');
    }

    /**
     * Benchmark Laravel Str::contains with multiple needles
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'contains'])]
    public function benchStrContains(): void
    {
        Str::contains($this->longString, ['lorem', 'ipsum', 'dolor']);
    }

    /**
     * Benchmark string concatenation
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'concatenation'])]
    public function benchStringConcatenation(): void
    {
        $result = '';
        for ($i = 0; $i < 100; $i++) {
            $result .= "String {$i} ";
        }
    }

    /**
     * Benchmark implode operation
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'implode'])]
    public function benchImplode(): void
    {
        implode(', ', $this->stringArray);
    }

    /**
     * Benchmark explode operation
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'explode'])]
    public function benchExplode(): void
    {
        explode(' ', $this->longString);
    }

    /**
     * Benchmark Unicode operations
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'unicode'])]
    public function benchUnicodeOperations(): void
    {
        mb_strlen($this->unicodeString);
        mb_strtoupper($this->unicodeString);
        mb_substr($this->unicodeString, 0, 10);
    }

    /**
     * Benchmark HTML strip tags
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['string', 'strip-tags'])]
    public function benchStripTags(): void
    {
        strip_tags($this->htmlString);
    }
}