<?php

namespace Tests\Unit;

use App\Cdrwa\Builder\AtomicWriter;
use App\Cdrwa\Builder\SeoFilesWriter;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SeoFilesWriterTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $this->base = $tmp . '/cdrwa-seo-test-' . bin2hex(random_bytes(6));
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);

        parent::tearDown();
    }

    public function test_sitemap_exact_output(): void
    {
        $files = (new SeoFilesWriter())->files($this->pages(), $this->site(), '2026-11-21');

        $expected = implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            '  <url>',
            '    <loc>https://example.com/</loc>',
            '    <lastmod>2026-11-21</lastmod>',
            '    <priority>1.0</priority>',
            '  </url>',
            '  <url>',
            '    <loc>https://example.com/about/</loc>',
            '    <lastmod>2026-11-21</lastmod>',
            '    <priority>0.7</priority>',
            '  </url>',
            '</urlset>',
            '',
        ]);

        $this->assertSame($expected, $files['sitemap.xml']);
    }

    public function test_sitemap_skips_non_indexable_and_defaults_priority(): void
    {
        $sitemap = (new SeoFilesWriter())->files($this->pages(), $this->site(), '2026-11-21')['sitemap.xml'];

        $this->assertStringNotContainsString('/hidden/', $sitemap);
        $this->assertSame(2, substr_count($sitemap, '<url>'));
        $this->assertStringContainsString('<priority>0.7</priority>', $sitemap);
    }

    public function test_sitemap_with_no_pages(): void
    {
        $sitemap = (new SeoFilesWriter())->files([], $this->site(), '2026-11-21')['sitemap.xml'];

        $this->assertSame(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n\n</urlset>\n",
            $sitemap
        );
    }

    public function test_sitemap_escapes_xml(): void
    {
        $pages = [['path' => '/a&b/', 'title' => 'T', 'description' => 'D']];

        $sitemap = (new SeoFilesWriter())->files($pages, $this->site(), '2026-11-21')['sitemap.xml'];

        $this->assertStringContainsString('<loc>https://example.com/a&amp;b/</loc>', $sitemap);
    }

    public function test_robots_exact(): void
    {
        $files = (new SeoFilesWriter())->files($this->pages(), $this->site(), '2026-11-21');

        $this->assertSame(
            "User-agent: *\nAllow: /\n\nSitemap: https://example.com/sitemap.xml\n",
            $files['robots.txt']
        );
    }

    public function test_llms_exact(): void
    {
        $files = (new SeoFilesWriter())->files($this->pages(), $this->site(), '2026-11-21');

        $expected = implode("\n", [
            '# CDR Writers Australia',
            '',
            '> Engineers Australia CDR experts',
            '',
            '## Pages',
            '- [Home](https://example.com/): Welcome',
            '- [About](https://example.com/about/): About us',
            '',
        ]);

        $this->assertSame($expected, $files['llms.txt']);
    }

    public function test_llms_skips_non_indexable(): void
    {
        $llms = (new SeoFilesWriter())->files($this->pages(), $this->site(), '2026-11-21')['llms.txt'];

        $this->assertStringNotContainsString('Hidden', $llms);
    }

    public function test_base_url_trailing_slash_is_removed(): void
    {
        $files = (new SeoFilesWriter())->files(
            $this->pages(),
            $this->site(['url' => 'https://example.com/']),
            '2026-11-21'
        );

        $this->assertStringContainsString('<loc>https://example.com/about/</loc>', $files['sitemap.xml']);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $files['robots.txt']);
        $this->assertStringNotContainsString('//about', $files['llms.txt']);
    }

    public function test_default_date_has_the_expected_format(): void
    {
        $sitemap = (new SeoFilesWriter())->files($this->pages(), $this->site())['sitemap.xml'];

        $this->assertMatchesRegularExpression('#<lastmod>\d{4}-\d{2}-\d{2}</lastmod>#', $sitemap);
    }

    public function test_rejects_a_bad_date(): void
    {
        foreach (['', '2026-1-2', '21-11-2026', '2026-11-21 ', "2026-11-21\n"] as $date) {
            try {
                (new SeoFilesWriter())->files($this->pages(), $this->site(), $date);
                $this->fail('Accepted date: ' . json_encode($date));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_write_writes_three_files_through_the_atomic_writer(): void
    {
        $out = $this->base . '/out';
        $seo = new SeoFilesWriter();

        $written = $seo->write(new AtomicWriter($out), $this->pages(), $this->site(), '2026-11-21');

        $this->assertSame(['sitemap.xml', 'robots.txt', 'llms.txt'], $written);

        foreach ($seo->files($this->pages(), $this->site(), '2026-11-21') as $name => $content) {
            $this->assertSame($content, file_get_contents($out . '/' . $name));
        }

        $this->assertSame(
            ['llms.txt', 'robots.txt', 'sitemap.xml'],
            array_values(array_diff(scandir($out), ['.', '..']))
        );
    }

    public function test_writing_twice_gives_identical_files(): void
    {
        $out = $this->base . '/out';
        $writer = new AtomicWriter($out);
        $seo = new SeoFilesWriter();

        $seo->write($writer, $this->pages(), $this->site(), '2026-11-21');
        $first = [
            file_get_contents($out . '/sitemap.xml'),
            file_get_contents($out . '/robots.txt'),
            file_get_contents($out . '/llms.txt'),
        ];

        $seo->write($writer, $this->pages(), $this->site(), '2026-11-21');
        $second = [
            file_get_contents($out . '/sitemap.xml'),
            file_get_contents($out . '/robots.txt'),
            file_get_contents($out . '/llms.txt'),
        ];

        $this->assertSame($first, $second);
    }

    /** @return list<array<string, mixed>> */
    private function pages(): array
    {
        return [
            ['path' => '/', 'title' => 'Home', 'description' => 'Welcome', 'priority' => '1.0'],
            ['path' => '/about/', 'title' => 'About', 'description' => 'About us'],
            ['path' => '/hidden/', 'title' => 'Hidden', 'description' => 'Not listed', 'indexable' => false],
        ];
    }

    /** @return array<string, mixed> */
    private function site(array $over = []): array
    {
        return $over + [
            'name' => 'CDR Writers Australia',
            'tagline' => 'Engineers Australia CDR experts',
            'url' => 'https://example.com',
        ];
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