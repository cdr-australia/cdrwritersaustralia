<?php

namespace Tests\Unit;

use App\Cdrwa\Builder\AtomicWriter;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class AtomicWriterTest extends TestCase
{
    private string $base;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $this->base = $tmp . '/cdrwa-test-' . bin2hex(random_bytes(6));
        $this->root = $this->base . '/out'; // not created: the writer must create it
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);

        parent::tearDown();
    }

    public function test_write_creates_missing_folders_and_file(): void
    {
        $writer = new AtomicWriter($this->root);

        $target = $writer->write('services/cdr-writing/index.html', '<p>hi</p>');

        $this->assertSame($this->root . '/services/cdr-writing/index.html', $target);
        $this->assertSame('<p>hi</p>', file_get_contents($target));
        $this->assertSame(['index.html'], $this->listDir($this->root . '/services/cdr-writing'));
    }

    public function test_write_overwrites_an_existing_file(): void
    {
        $writer = new AtomicWriter($this->root);

        $writer->write('index.html', 'v1');
        $writer->write('index.html', 'v2');

        $this->assertSame('v2', file_get_contents($this->root . '/index.html'));
        $this->assertSame(['index.html'], $this->listDir($this->root));
    }

    public function test_content_bytes_are_written_unchanged(): void
    {
        $writer = new AtomicWriter($this->root);
        $content = "line1\r\nline2\n\xC3\xA9\x00end";

        $writer->write('raw.bin', $content);

        $this->assertSame($content, file_get_contents($this->root . '/raw.bin'));
    }

    public function test_rejects_unsafe_relative_paths(): void
    {
        $writer = new AtomicWriter($this->root);

        $bad = [
            '', '..', '../x.txt', 'a/../../x.txt', '/abs.txt', 'C:/x.txt', 'c:x.txt',
            'a\\b.txt', 'a//b.txt', 'a/', 'a/./b.txt', "a\0b.txt", "a\nb.txt",
            'a/b.', 'a/b ', 'a/*.txt',
        ];

        foreach ($bad as $path) {
            try {
                $writer->write($path, 'x');
                $this->fail('Accepted an unsafe path: ' . json_encode($path));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertFalse(file_exists($this->base . '/x.txt'));
    }

    public function test_allows_dotfiles_and_404_html(): void
    {
        $writer = new AtomicWriter($this->root);

        $writer->write('.htaccess', 'a');
        $writer->write('404.html', 'b');
        $writer->write('sw.js', 'c');

        $this->assertSame(['.htaccess', '404.html', 'sw.js'], $this->listDir($this->root));
    }

    public function test_never_touches_files_it_did_not_write(): void
    {
        mkdir($this->root, 0777, true);
        file_put_contents($this->root . '/keep.txt', 'mine');

        (new AtomicWriter($this->root))->write('index.html', 'x');

        $this->assertSame('mine', file_get_contents($this->root . '/keep.txt'));
        $this->assertSame(['index.html', 'keep.txt'], $this->listDir($this->root));
    }

    public function test_refuses_to_replace_a_folder(): void
    {
        $writer = new AtomicWriter($this->root);
        $writer->write('keep.txt', 'x');
        mkdir($this->root . '/foo');

        $this->expectException(RuntimeException::class);

        $writer->write('foo', 'x');
    }

    public function test_refuses_when_a_parent_is_a_file(): void
    {
        $writer = new AtomicWriter($this->root);
        $writer->write('a', 'file');

        try {
            $writer->write('a/b.txt', 'y');
            $this->fail('Wrote below a path that is a file.');
        } catch (RuntimeException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('file', file_get_contents($this->root . '/a'));
    }

    public function test_copy_file_copies_bytes_exactly(): void
    {
        $writer = new AtomicWriter($this->root);
        $source = $this->base . '/source.bin';
        $bytes = "\x89PNG\r\n\x1a\n\x00\x00\xFF";
        file_put_contents($source, $bytes);

        $target = $writer->copyFile($source, 'assets/icons/icon.png');

        $this->assertSame($bytes, file_get_contents($target));
        $this->assertSame(['icon.png'], $this->listDir($this->root . '/assets/icons'));
        $this->assertSame($bytes, file_get_contents($source));
    }

    public function test_copy_file_rejects_missing_source(): void
    {
        $writer = new AtomicWriter($this->root);

        $this->expectException(RuntimeException::class);

        $writer->copyFile($this->base . '/nope.bin', 'x.bin');
    }

    public function test_constructor_requires_absolute_root(): void
    {
        foreach (['', '   ', 'relative/out', 'C:', '/'] as $root) {
            try {
                new AtomicWriter($root);
                $this->fail('Accepted root: ' . json_encode($root));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }

        // A trailing slash on a valid root is fine.
        (new AtomicWriter($this->root . '/'))->write('t.txt', 'x');
        $this->assertFileExists($this->root . '/t.txt');
    }

    /** @return list<string> */
    private function listDir(string $dir): array
    {
        return array_values(array_diff(scandir($dir), ['.', '..']));
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}