<?php

namespace App\Cdrwa\Builder;

use InvalidArgumentException;
use RuntimeException;

/**
 * Writes files into ONE root folder (the public output folder) and nowhere else.
 *
 * - every write goes to a temporary file in the target folder, then rename()
 * - missing folders are created
 * - relative paths are validated: no "..", no absolute paths, no backslashes,
 *   no drive letters, no control characters
 * - the real (symlink-resolved) target folder must stay inside the real root
 * - it never deletes anything except its own temporary file
 */
class AtomicWriter
{
    private string $root;

    public function __construct(string $root)
    {
        $root = rtrim(str_replace('\\', '/', trim($root)), '/');

        if ($root === '' || preg_match('#^[A-Za-z]:$#', $root) === 1) {
            throw new InvalidArgumentException('The output root must be a real folder, not a drive or the filesystem root.');
        }

        if (preg_match('#^(?:/|[A-Za-z]:/)#', $root) !== 1) {
            throw new InvalidArgumentException('The output root must be an absolute path.');
        }

        $this->root = $root;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Validates a relative path and returns root + "/" + path.
     *
     * @throws InvalidArgumentException when the path is unsafe
     */
    public function absolutePath(string $relativePath): string
    {
        $this->assertSafeRelativePath($relativePath);

        return $this->root . '/' . $relativePath;
    }

    /**
     * Writes $content to $relativePath (temp file, then rename).
     * Returns the absolute path of the written file.
     *
     * @throws InvalidArgumentException when the path is unsafe
     * @throws RuntimeException when the file cannot be written
     */
    public function write(string $relativePath, string $content): string
    {
        $target = $this->prepareTarget($relativePath);
        $tmp = $this->newTempFile(dirname($target), $relativePath);

        $written = @file_put_contents($tmp, $content);
        if ($written === false || $written !== strlen($content)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot write the temporary file for: {$relativePath}");
        }

        $this->commit($tmp, $target, $relativePath);

        return $target;
    }

    /**
     * Copies an existing file (any bytes) to $relativePath, atomically.
     * $sourceFile is read only; it is never modified or deleted.
     *
     * @throws InvalidArgumentException when the target path is unsafe
     * @throws RuntimeException when the source is missing or the copy fails
     */
    public function copyFile(string $sourceFile, string $relativePath): string
    {
        if (!is_file($sourceFile) || !is_readable($sourceFile)) {
            throw new RuntimeException("Source file not found or not readable for: {$relativePath}");
        }

        $target = $this->prepareTarget($relativePath);
        $tmp = $this->newTempFile(dirname($target), $relativePath);

        if (!@copy($sourceFile, $tmp)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot copy the file for: {$relativePath}");
        }

        $this->commit($tmp, $target, $relativePath);

        return $target;
    }

    private function assertSafeRelativePath(string $path): void
    {
        if ($path === '') {
            throw new InvalidArgumentException('The relative path is empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F\\\\:*?"<>|]/', $path) === 1) {
            throw new InvalidArgumentException('The relative path contains a forbidden character.');
        }

        if ($path[0] === '/' || str_ends_with($path, '/')) {
            throw new InvalidArgumentException('The relative path must not start or end with "/".');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('The relative path contains an empty, "." or ".." segment.');
            }

            // Windows silently drops trailing dots and spaces, which could alias another name.
            if (rtrim($segment, '. ') !== $segment) {
                throw new InvalidArgumentException('A path segment must not end with a dot or a space.');
            }
        }
    }

    /**
     * Validates the path, creates missing folders (only inside the root) and
     * returns the absolute target path. Nothing is written to the target yet.
     */
    private function prepareTarget(string $relativePath): string
    {
        $absolute = $this->absolutePath($relativePath);
        $dir = dirname($absolute);

        $this->realRoot();

        // Find the deepest folder that already exists BEFORE creating anything,
        // so a symlinked folder cannot make us create folders outside the root.
        // If something that is not a folder sits in the way (a file, or a broken
        // link), stop here with a clear message instead of calling mkdir().
        $existing = $dir;
        while (!is_dir($existing)) {
            if (file_exists($existing) || is_link($existing)) {
                throw new RuntimeException("A file is in the way of the folder for: {$relativePath}");
            }

            $parent = dirname($existing);
            if ($parent === $existing) {
                throw new RuntimeException("Cannot find a parent folder for: {$relativePath}");
            }
            $existing = $parent;
        }
        $this->assertInsideRoot($existing, $relativePath);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create the folder for: {$relativePath}");
        }
        $this->assertInsideRoot($dir, $relativePath);

        if (is_dir($absolute)) {
            throw new RuntimeException("The target is a folder, not a file: {$relativePath}");
        }

        return $absolute;
    }

    /** Creates the root if needed and returns its real path (forward slashes). */
    private function realRoot(): string
    {
        if (!is_dir($this->root) && !@mkdir($this->root, 0755, true) && !is_dir($this->root)) {
            throw new RuntimeException('Cannot create the output folder.');
        }

        $real = realpath($this->root);
        if ($real === false) {
            throw new RuntimeException('Cannot resolve the output folder.');
        }

        return $this->normaliseReal($real);
    }

    private function assertInsideRoot(string $dir, string $relativePath): void
    {
        $real = realpath($dir);

        if ($real === false || !$this->isInside($this->normaliseReal($real), $this->realRoot())) {
            throw new RuntimeException("Refusing to write outside the output folder: {$relativePath}");
        }
    }

    private function isInside(string $realDir, string $realRoot): bool
    {
        return str_starts_with($this->fold($realDir . '/'), $this->fold($realRoot . '/'));
    }

    private function newTempFile(string $dir, string $relativePath): string
    {
        $tmp = @tempnam($dir, '.cdrwa-');
        if ($tmp === false) {
            throw new RuntimeException("Cannot create a temporary file for: {$relativePath}");
        }

        // tempnam() silently falls back to the system temp folder when $dir is not
        // writable. A rename from there would not be atomic, so refuse it.
        $tmpDir = realpath(dirname($tmp));
        $realDir = realpath($dir);
        if (
            $tmpDir === false
            || $realDir === false
            || $this->fold($this->normaliseReal($tmpDir)) !== $this->fold($this->normaliseReal($realDir))
        ) {
            @unlink($tmp);
            throw new RuntimeException("The target folder is not writable: {$relativePath}");
        }

        return $tmp;
    }

    private function commit(string $tmp, string $target, string $relativePath): void
    {
        @chmod($tmp, 0644);

        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot move the finished file into place: {$relativePath}");
        }
    }

    private function normaliseReal(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function fold(string $path): string
    {
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}