<?php

namespace Tests\Feature;

use Tests\TestCase;

class InitDataCommandTest extends TestCase
{
    private string $base;
    private string $seed;
    private string $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = str_replace('\\', '/', sys_get_temp_dir()) . '/cdrwa-init-' . bin2hex(random_bytes(6));
        $this->seed = $this->base . '/seed';
        $this->data = $this->base . '/data';

        mkdir($this->seed . '/pages/deep', 0775, true);
        file_put_contents($this->seed . '/site.json', '{"a":1}');
        file_put_contents($this->seed . '/pages/home.json', '{"b":2}');
        file_put_contents($this->seed . '/pages/deep/x.json', '{"c":3}');

        config([
            'cdrwa.seed_data_path' => $this->seed,
            'cdrwa.data_path' => $this->data,
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);

        parent::tearDown();
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function test_copies_seed_when_data_folder_is_missing(): void
    {
        $this->artisan('cdrwa:init-data')
            ->expectsOutputToContain('Copied 3 file(s)')
            ->assertExitCode(0);

        $this->assertSame('{"a":1}', file_get_contents($this->data . '/site.json'));
        $this->assertSame('{"c":3}', file_get_contents($this->data . '/pages/deep/x.json'));
        $this->assertDirectoryDoesNotExist($this->data . '.init-tmp');
    }

    public function test_copies_seed_when_data_folder_exists_but_is_empty(): void
    {
        mkdir($this->data, 0775, true);

        $this->artisan('cdrwa:init-data')
            ->expectsOutputToContain('Copied 3 file(s)')
            ->assertExitCode(0);

        $this->assertFileExists($this->data . '/pages/home.json');
    }

    public function test_second_run_does_nothing_and_never_overwrites(): void
    {
        $this->artisan('cdrwa:init-data')->assertExitCode(0);

        file_put_contents($this->data . '/site.json', '{"edited":true}');

        $this->artisan('cdrwa:init-data')
            ->expectsOutputToContain('nothing to do')
            ->assertExitCode(0);

        $this->assertSame('{"edited":true}', file_get_contents($this->data . '/site.json'));
    }

    public function test_fails_when_seed_folder_is_missing(): void
    {
        config(['cdrwa.seed_data_path' => $this->base . '/no-such-seed']);

        $this->artisan('cdrwa:init-data')->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($this->data);
    }

    public function test_fails_when_data_folder_is_inside_seed_folder(): void
    {
        config(['cdrwa.data_path' => $this->seed . '/inner']);

        $this->artisan('cdrwa:init-data')->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($this->seed . '/inner');
    }
}