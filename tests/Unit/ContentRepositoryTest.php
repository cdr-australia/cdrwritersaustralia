<?php

namespace Tests\Unit;

use App\Cdrwa\Content\ContentRepository;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class ContentRepositoryTest extends TestCase
{
    private string $data;

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $this->data = $tmp . '/cdrwa-data-test-' . bin2hex(random_bytes(6));
        mkdir($this->data, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->data);

        parent::tearDown();
    }

    public function test_reads_site_and_nav(): void
    {
        $this->put('site.json', '{"name":"CDR Writers Australia","url":"https://example.com"}');
        $this->put('nav.json', '{"header":[{"label":"Home","href":"/"}]}');

        $repo = new ContentRepository($this->data);

        $this->assertSame('CDR Writers Australia', $repo->site()['name']);
        $this->assertSame('/', $repo->nav()['header'][0]['href']);
    }

    public function test_page_names_are_sorted_by_file_name(): void
    {
        foreach (['home', '404', 'cdr-writing', 'about', 'guides', 'guides-extra'] as $name) {
            $this->put("pages/{$name}.json", '{}');
        }

        // Sorted by full file name: "guides-extra.json" comes before "guides.json"
        // because "-" sorts before ".". This matches NTFS order used by the Node build.
        $this->assertSame(
            ['404', 'about', 'cdr-writing', 'guides-extra', 'guides', 'home'],
            (new ContentRepository($this->data))->pageNames()
        );
    }

    public function test_page_names_ignore_non_json_and_folders(): void
    {
        $this->put('pages/home.json', '{}');
        $this->put('pages/notes.txt', 'x');
        $this->put('pages/sub/inner.json', '{}');
        mkdir($this->data . '/pages/dir.json');

        $this->assertSame(['home'], (new ContentRepository($this->data))->pageNames());
    }

    public function test_reads_a_page_and_keeps_key_order_and_unicode(): void
    {
        $this->put('pages/home.json', '{"z":1,"path":"/","title":"Café — 🙂","slash":"a\/b","a":2}');

        $page = (new ContentRepository($this->data))->page('home');

        $this->assertSame(['z', 'path', 'title', 'slash', 'a'], array_keys($page));
        $this->assertSame('Café — 🙂', $page['title']);
        $this->assertSame('a/b', $page['slash']);
    }

    public function test_empty_json_object_becomes_empty_array_known_limitation(): void
    {
        $this->put('pages/home.json', '{"data":{},"items":[]}');

        $page = (new ContentRepository($this->data))->page('home');

        // KNOWN LIMITATION: "{}" and "[]" both decode to []. The editor milestones
        // must keep this in mind when saving (see the report).
        $this->assertSame(['data' => [], 'items' => []], $page);
    }

    public function test_rejects_unsafe_page_names(): void
    {
        $repo = new ContentRepository($this->data);

        $bad = ['', '..', '../site', 'Home', 'a/b', 'a\\b', 'a_b', 'home.json', "home\n", '-home', 'home-'];

        foreach ($bad as $name) {
            try {
                $repo->page($name);
                $this->fail('Accepted page name: ' . json_encode($name));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_missing_file_has_clear_message(): void
    {
        $repo = new ContentRepository($this->data);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pages/nope.json');

        $repo->page('nope');
    }

    public function test_invalid_json_has_clear_message(): void
    {
        $this->put('pages/broken.json', '{"a":');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in pages/broken.json');

        (new ContentRepository($this->data))->page('broken');
    }

    public function test_bom_is_reported(): void
    {
        $this->put('pages/bom.json', "\xEF\xBB\xBF{}");

        try {
            (new ContentRepository($this->data))->page('bom');
            $this->fail('A file with a BOM was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('BOM', $e->getMessage());
        }
    }

    public function test_non_object_json_is_rejected(): void
    {
        $repo = new ContentRepository($this->data);

        foreach (['"hello"', '123', 'null'] as $json) {
            $this->put('pages/x.json', $json);

            try {
                $repo->page('x');
                $this->fail('Accepted JSON: ' . $json);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JSON object', $e->getMessage());
            }
        }
    }

    public function test_shared_files_and_source_reference(): void
    {
        $this->put('shared/faq.json', '{"heading":"FAQ","items":[]}');
        $this->put('shared/cta.json', '{"heading":"Go"}');

        $repo = new ContentRepository($this->data);

        $this->assertSame(['cta', 'faq'], $repo->sharedNames());
        $this->assertSame('FAQ', $repo->shared('faq')['heading']);
        $this->assertSame($repo->shared('faq'), $repo->source('shared/faq.json'));
    }

    public function test_rejects_unsafe_source_references(): void
    {
        $this->put('site.json', '{}');
        $this->put('shared/faq.json', '{}');

        $repo = new ContentRepository($this->data);

        $bad = [
            '', '../site.json', 'shared/../site.json', 'pages/home.json', 'shared/FAQ.json',
            'shared/faq', '/shared/faq.json', 'shared/faq.json ', "shared/faq.json\n", 'shared/a/b.json',
        ];

        foreach ($bad as $source) {
            try {
                $repo->source($source);
                $this->fail('Accepted source: ' . json_encode($source));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_missing_folder_is_reported(): void
    {
        $repo = new ContentRepository($this->data);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pages/');

        $repo->pageNames();
    }

    public function test_blank_data_path_is_rejected(): void
    {
        foreach (['', '   ', '/'] as $path) {
            try {
                new ContentRepository($path);
                $this->fail('Accepted data path: ' . json_encode($path));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function put(string $relative, string $content): void
    {
        $file = $this->data . '/' . $relative;
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($file, $content);
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