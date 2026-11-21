<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CdrwaInitData extends Command
{
    protected $signature = 'cdrwa:init-data';

    protected $description = 'Copy the seed content into CDRWA_DATA_PATH, only when that folder is missing or empty';

    public function handle(): int
    {
        $seed = $this->normalise((string) config('cdrwa.seed_data_path'));
        $dest = $this->normalise((string) config('cdrwa.data_path'));

        if ($seed === '' || $dest === '') {
            $this->error('CDRWA_SEED_DATA_PATH and CDRWA_DATA_PATH must both be set.');
            return self::FAILURE;
        }

        if (! is_dir($seed)) {
            $this->error('Seed folder does not exist: ' . $seed);
            return self::FAILURE;
        }

        if ($this->overlaps($seed, $dest)) {
            $this->error('The seed folder and the data folder must not be inside each other.');
            return self::FAILURE;
        }

        if (file_exists($dest) && ! is_dir($dest)) {
            $this->error('Data path exists but is not a folder: ' . $dest);
            return self::FAILURE;
        }

        if (is_dir($dest) && $this->hasEntries($dest)) {
            $this->info('nothing to do');
            return self::SUCCESS;
        }

        $stage = $dest . '.init-tmp';

        if (file_exists($stage)) {
            $this->error('A leftover temporary folder exists: ' . $stage . ' . Delete it and run again.');
            return self::FAILURE;
        }

        $parent = dirname($dest);
        if (! is_dir($parent) && ! mkdir($parent, 0775, true) && ! is_dir($parent)) {
            $this->error('Could not create folder: ' . $parent);
            return self::FAILURE;
        }

        $copied = $this->copyTree($seed, $stage);

        if ($copied === null) {
            $this->deleteStage($stage);
            $this->error('Copy failed. Nothing was written to ' . $dest);
            return self::FAILURE;
        }

        if ($copied === 0) {
            $this->deleteStage($stage);
            $this->error('The seed folder contains no files: ' . $seed);
            return self::FAILURE;
        }

        // An empty destination folder may exist; it must go before the rename.
        if (is_dir($dest) && ! rmdir($dest)) {
            $this->deleteStage($stage);
            $this->error('Could not replace the empty data folder: ' . $dest);
            return self::FAILURE;
        }

        if (! rename($stage, $dest)) {
            $this->deleteStage($stage);
            $this->error('Could not move the copied content into place: ' . $dest);
            return self::FAILURE;
        }

        $this->info('Copied ' . $copied . ' file(s) into ' . $dest);

        return self::SUCCESS;
    }

    private function normalise(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function overlaps(string $a, string $b): bool
    {
        $a .= '/';
        $b .= '/';

        return strncasecmp($a, $b, strlen($a)) === 0
            || strncasecmp($b, $a, strlen($b)) === 0;
    }

    private function hasEntries(string $dir): bool
    {
        $entries = scandir($dir);

        if ($entries === false) {
            // Cannot read it: treat as not empty so nothing is ever overwritten.
            return true;
        }

        return count(array_diff($entries, ['.', '..'])) > 0;
    }

    /**
     * Copies every regular file and folder from $from into $to.
     * Returns the number of files copied, or null on any failure.
     */
    private function copyTree(string $from, string $to): ?int
    {
        if (! mkdir($to, 0775, true) && ! is_dir($to)) {
            return null;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $count = 0;

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }

            $target = $to . '/' . str_replace('\\', '/', $iterator->getSubPathname());

            if ($item->isDir()) {
                if (! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
                    return null;
                }
                continue;
            }

            if (! $item->isFile()) {
                continue;
            }

            if (! copy($item->getPathname(), $target)) {
                return null;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Removes only the temporary folder this command created.
     */
    private function deleteStage(string $stage): void
    {
        if (! str_ends_with($stage, '.init-tmp') || ! is_dir($stage)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stage, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($stage);
    }
}