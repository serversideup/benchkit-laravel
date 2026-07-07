<?php

namespace App\Benchmarks\Php\Performance;

use Illuminate\Support\Facades\File;
use PhpBench\Attributes as Bench;

class FileBenchmark
{
    private string $testDir;

    private string $smallContent;

    private string $mediumContent;

    private string $largeContent;

    private string $smallFile;

    private string $mediumFile;

    private string $largeFile;

    private array $csvData;

    public function __construct()
    {
        $this->testDir = storage_path('benchmarks');

        // Create test directory
        if (! File::exists($this->testDir)) {
            File::makeDirectory($this->testDir, 0755, true);
        }

        // Small content - 1KB
        $this->smallContent = str_repeat('Small file content. ', 50);

        // Medium content - 100KB
        $this->mediumContent = str_repeat('Medium file content with some data. ', 3000);

        // Large content - 1MB
        $this->largeContent = str_repeat('Large file content with significant amount of data. ', 20000);

        // Create test files
        $this->smallFile = $this->testDir.'/small_test.txt';
        $this->mediumFile = $this->testDir.'/medium_test.txt';
        $this->largeFile = $this->testDir.'/large_test.txt';

        file_put_contents($this->smallFile, $this->smallContent);
        file_put_contents($this->mediumFile, $this->mediumContent);
        file_put_contents($this->largeFile, $this->largeContent);

        // CSV data
        $this->csvData = [];
        for ($i = 0; $i < 1000; $i++) {
            $this->csvData[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'score' => ($i * 7) % 100,
            ];
        }
    }

    public function __destruct()
    {
        // Cleanup test files
        if (File::exists($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }
    }

    /**
     * Benchmark writing small file
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'write'])]
    public function benchWriteSmallFile(): void
    {
        file_put_contents($this->testDir.'/write_small.txt', $this->smallContent);
    }

    /**
     * Benchmark writing medium file
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'write'])]
    public function benchWriteMediumFile(): void
    {
        file_put_contents($this->testDir.'/write_medium.txt', $this->mediumContent);
    }

    /**
     * Benchmark writing large file
     */
    #[Bench\Revs(200)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'write'])]
    public function benchWriteLargeFile(): void
    {
        file_put_contents($this->testDir.'/write_large.txt', $this->largeContent);
    }

    /**
     * Benchmark reading small file
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'read'])]
    public function benchReadSmallFile(): void
    {
        file_get_contents($this->smallFile);
    }

    /**
     * Benchmark reading medium file
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'read'])]
    public function benchReadMediumFile(): void
    {
        file_get_contents($this->mediumFile);
    }

    /**
     * Benchmark reading large file
     */
    #[Bench\Revs(200)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'read'])]
    public function benchReadLargeFile(): void
    {
        file_get_contents($this->largeFile);
    }

    /**
     * Benchmark reading file line by line
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'read'])]
    public function benchReadFileLineByLine(): void
    {
        $handle = fopen($this->mediumFile, 'r');
        while (($line = fgets($handle)) !== false) {
            // Process line
        }
        fclose($handle);
    }

    /**
     * Benchmark Laravel File facade put
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'laravel'])]
    public function benchLaravelFilePut(): void
    {
        File::put($this->testDir.'/laravel_test.txt', $this->smallContent);
    }

    /**
     * Benchmark Laravel File facade get
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'laravel'])]
    public function benchLaravelFileGet(): void
    {
        File::get($this->smallFile);
    }

    /**
     * Benchmark file append operations
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'append'])]
    public function benchFileAppend(): void
    {
        $file = $this->testDir.'/append_test.txt';
        file_put_contents($file, "Line 1\n", FILE_APPEND);
    }

    /**
     * Benchmark file copy
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'copy'])]
    public function benchFileCopy(): void
    {
        copy($this->mediumFile, $this->testDir.'/copy_test.txt');
    }

    /**
     * Benchmark file delete
     */
    #[Bench\Revs(2000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'delete'])]
    public function benchFileDelete(): void
    {
        $file = $this->testDir.'/delete_test.txt';
        file_put_contents($file, $this->smallContent);
        unlink($file);
    }

    /**
     * Benchmark file_exists checks
     */
    #[Bench\Revs(10000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'exists'])]
    public function benchFileExists(): void
    {
        file_exists($this->smallFile);
        file_exists($this->testDir.'/nonexistent.txt');
    }

    /**
     * Benchmark directory listing
     */
    #[Bench\Revs(5000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'directory'])]
    public function benchDirectoryListing(): void
    {
        scandir($this->testDir);
    }

    /**
     * Benchmark CSV writing
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'csv'])]
    public function benchCsvWrite(): void
    {
        $file = $this->testDir.'/test.csv';
        $handle = fopen($file, 'w');
        foreach ($this->csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    /**
     * Benchmark CSV reading
     */
    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'csv'])]
    public function benchCsvRead(): void
    {
        $file = $this->testDir.'/read_test.csv';

        // Create CSV file first
        $handle = fopen($file, 'w');
        foreach ($this->csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        // Now read it
        $handle = fopen($file, 'r');
        while (($row = fgetcsv($handle)) !== false) {
            // Process row
        }
        fclose($handle);
    }

    /**
     * Benchmark JSON file operations
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(2)]
    #[Bench\Groups(['file', 'json'])]
    public function benchJsonFileOperations(): void
    {
        $file = $this->testDir.'/data.json';
        $data = ['users' => $this->csvData];

        // Write JSON
        file_put_contents($file, json_encode($data));

        // Read JSON
        $content = file_get_contents($file);
        json_decode($content, true);
    }
}
