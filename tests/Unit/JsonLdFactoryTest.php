<?php

namespace Tests\Unit;

use App\Cdrwa\Builder\JsonLdFactory;
use App\Cdrwa\Builder\SectionRenderer;
use App\Cdrwa\Content\ContentRepository;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class JsonLdFactoryTest extends TestCase
{
    private string $base;

    private string $data;

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $this->base = $tmp . '/cdrwa-jsonld-test-' . bin2hex(random_bytes(6));
        $this->data = $this->base . '/data';

        mkdir($this->data . '/shared', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);

        parent::tearDown();
    }

    public function test_graph_has_the_expected_types_in_order(): void
    {
        $page = $this->page([
            'service' => ['name' => 'CDR Writing', 'type' => 'Writing', 'area' => 'AU'],
            'article' => ['headline' => 'H', 'published' => '2026-01-02', 'modified' => '2026-02-03'],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/'],
                ['name' => 'CDR Writing', 'path' => '/services/cdr-writing/'],
            ],
            'sections' => [
                ['id' => 'faq', 'type' => 'faq', 'order' => 1, 'data' => ['items' => [['q' => 'Q', 'a' => 'A']]]],
            ],
        ]);

        $graph = $this->factory()->graph($page, $this->site());

        $this->assertSame(
            ['ProfessionalService', 'WebSite', 'WebPage', 'Service', 'Article', 'BreadcrumbList', 'FAQPage'],
            array_column($graph, '@type')
        );
    }

    public function test_organization_node(): void
    {
        $org = $this->node($this->factory()->graph($this->page(), $this->site()), 'ProfessionalService');

        $this->assertSame([
            '@type' => 'ProfessionalService',
            '@id' => 'https://example.com/#organization',
            'name' => 'CDR Writers Australia',
            'alternateName' => 'CDRWA',
            'url' => 'https://example.com/',
            'logo' => 'https://example.com/assets/icons/logo.png',
            'description' => 'Engineers Australia CDR experts',
            'telephone' => '+61 400 000 000',
            'email' => 'hi@example.com',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '1 Main St',
                'addressLocality' => 'Sydney',
                'addressRegion' => 'NSW',
                'postalCode' => '2000',
                'addressCountry' => 'AU',
            ],
            'areaServed' => 'Worldwide',
            'sameAs' => ['https://x.com/cdrwa', 'https://linkedin.com/company/cdrwa'],
        ], $org);
    }

    public function test_organization_omits_absent_keys_and_uses_logo_fallback(): void
    {
        $site = ['name' => 'N', 'url' => 'https://example.com', 'tagline' => 'T'];

        $decoded = json_decode($this->factory()->json($this->page(), $site), true);
        $org = $decoded['@graph'][0];

        $this->assertSame(
            ['@type', '@id', 'name', 'url', 'logo', 'description', 'areaServed', 'sameAs'],
            array_keys($org)
        );
        $this->assertSame('https://example.com/assets/icons/icon-512.png', $org['logo']);
        $this->assertSame([], $org['sameAs']);
    }

    public function test_website_node(): void
    {
        $node = $this->node($this->factory()->graph($this->page(), $this->site()), 'WebSite');

        $this->assertSame([
            '@type' => 'WebSite',
            '@id' => 'https://example.com/#website',
            'url' => 'https://example.com/',
            'name' => 'CDR Writers Australia',
            'publisher' => ['@id' => 'https://example.com/#organization'],
            'inLanguage' => 'en-AU',
        ], $node);
    }

    public function test_webpage_node(): void
    {
        $node = $this->node($this->factory()->graph($this->page(), $this->site()), 'WebPage');

        $this->assertSame([
            '@type' => 'WebPage',
            '@id' => 'https://example.com/services/cdr-writing/#webpage',
            'url' => 'https://example.com/services/cdr-writing/',
            'name' => 'CDR Writing',
            'description' => 'We write CDRs.',
            'isPartOf' => ['@id' => 'https://example.com/#website'],
            'about' => ['@id' => 'https://example.com/#organization'],
            'inLanguage' => 'en-AU',
        ], $node);
    }

    public function test_service_node_only_when_page_has_service(): void
    {
        $factory = $this->factory();

        $without = $factory->graph($this->page(), $this->site());
        $this->assertNotContains('Service', array_column($without, '@type'));

        $with = $factory->graph(
            $this->page(['service' => ['name' => 'CDR Writing', 'type' => 'Writing', 'area' => 'AU']]),
            $this->site()
        );

        $this->assertSame([
            '@type' => 'Service',
            '@id' => 'https://example.com/services/cdr-writing/#service',
            'name' => 'CDR Writing',
            'serviceType' => 'Writing',
            'description' => 'We write CDRs.',
            'provider' => ['@id' => 'https://example.com/#organization'],
            'areaServed' => 'AU',
        ], $this->node($with, 'Service'));

        // No area given: falls back to AU.
        $noArea = $factory->graph(
            $this->page(['service' => ['name' => 'S', 'type' => 'T']]),
            $this->site()
        );
        $this->assertSame('AU', $this->node($noArea, 'Service')['areaServed']);
    }

    public function test_article_node_and_fallbacks(): void
    {
        $factory = $this->factory();

        $article = $this->node($factory->graph(
            $this->page(['article' => ['headline' => 'H', 'published' => '2026-01-02']]),
            $this->site()
        ), 'Article');

        $this->assertSame([
            '@type' => 'Article',
            '@id' => 'https://example.com/services/cdr-writing/#article',
            'headline' => 'H',
            'description' => 'We write CDRs.',
            'datePublished' => '2026-01-02',
            'dateModified' => '2026-01-02',
            'author' => ['@id' => 'https://example.com/#organization'],
            'publisher' => ['@id' => 'https://example.com/#organization'],
            'mainEntityOfPage' => ['@id' => 'https://example.com/services/cdr-writing/#webpage'],
            'image' => 'https://example.com/assets/icons/og.png',
        ], $article);

        // Page ogImage wins over the site's; no headline falls back to the title.
        $site = $this->site();
        $viaPage = $this->node($factory->graph(
            $this->page(['ogImage' => '/assets/p.png', 'article' => ['published' => '2026-01-02', 'modified' => '2026-03-04']]),
            $site
        ), 'Article');
        $this->assertSame('https://example.com/assets/p.png', $viaPage['image']);
        $this->assertSame('CDR Writing', $viaPage['headline']);
        $this->assertSame('2026-03-04', $viaPage['dateModified']);

        // Neither page nor site has an image: og-image.png.
        unset($site['ogImage']);
        $fallback = $this->node($factory->graph(
            $this->page(['article' => ['published' => '2026-01-02']]),
            $site
        ), 'Article');
        $this->assertSame('https://example.com/assets/icons/og-image.png', $fallback['image']);
    }

    public function test_breadcrumb_list(): void
    {
        $graph = $this->factory()->graph(
            $this->page(['breadcrumbs' => [
                ['name' => 'Home', 'path' => '/'],
                ['name' => 'Services', 'path' => '/services/'],
            ]]),
            $this->site()
        );

        $this->assertSame([
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://example.com/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services', 'item' => 'https://example.com/services/'],
            ],
        ], $this->node($graph, 'BreadcrumbList'));
    }

    public function test_no_breadcrumb_node_when_empty(): void
    {
        $factory = $this->factory();

        $missing = $factory->graph($this->page(), $this->site());
        $empty = $factory->graph($this->page(['breadcrumbs' => []]), $this->site());

        $this->assertNotContains('BreadcrumbList', array_column($missing, '@type'));
        $this->assertNotContains('BreadcrumbList', array_column($empty, '@type'));
    }

    public function test_faq_from_inline_items(): void
    {
        $graph = $this->factory()->graph(
            $this->page(['sections' => [
                ['id' => 'faq', 'type' => 'faq', 'order' => 1, 'data' => ['items' => [['q' => 'Q', 'a' => 'A']]]],
            ]]),
            $this->site()
        );

        $this->assertSame([
            '@type' => 'FAQPage',
            'mainEntity' => [[
                '@type' => 'Question',
                'name' => 'Q',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'A'],
            ]],
        ], $this->node($graph, 'FAQPage'));
    }

    public function test_faq_from_source(): void
    {
        file_put_contents(
            $this->data . '/shared/faq.json',
            json_encode(['heading' => 'FAQ', 'items' => [['q' => 'One?', 'a' => 'Yes'], ['q' => 'Two?', 'a' => 'No']]])
        );

        $graph = $this->factory()->graph(
            $this->page(['sections' => [
                ['id' => 'f', 'type' => 'faq', 'order' => 1, 'source' => 'shared/faq.json', 'data' => ['heading' => 'Own']],
            ]]),
            $this->site()
        );

        $names = array_column($this->node($graph, 'FAQPage')['mainEntity'], 'name');

        $this->assertSame(['One?', 'Two?'], $names);
    }

    public function test_faq_ignores_disabled_and_broken_sections_and_merges_several(): void
    {
        $graph = $this->factory()->graph(
            $this->page(['sections' => [
                ['id' => 'a', 'type' => 'faq', 'order' => 1, 'data' => ['items' => [['q' => 'First', 'a' => '1']]]],
                ['id' => 'b', 'type' => 'faq', 'order' => 2, 'enabled' => false, 'data' => ['items' => [['q' => 'Hidden', 'a' => 'x']]]],
                ['id' => 'c', 'type' => 'faq', 'order' => 3, 'source' => 'shared/missing.json'],
                ['id' => 'd', 'type' => 'content-block', 'order' => 4, 'data' => ['items' => [['q' => 'Wrong type', 'a' => 'x']]]],
                'junk',
                ['id' => 'e', 'type' => 'faq', 'order' => 5, 'data' => ['items' => [['q' => 'Second', 'a' => '2']]]],
            ]]),
            $this->site()
        );

        $this->assertSame(
            ['First', 'Second'],
            array_column($this->node($graph, 'FAQPage')['mainEntity'], 'name')
        );
    }

    public function test_no_faq_node_without_faq_sections(): void
    {
        $graph = $this->factory()->graph(
            $this->page(['sections' => [
                ['id' => 'c', 'type' => 'content-block', 'order' => 1, 'data' => ['heading' => 'H']],
            ]]),
            $this->site()
        );

        $this->assertNotContains('FAQPage', array_column($graph, '@type'));
    }

    public function test_json_is_compact_unescaped_and_round_trips(): void
    {
        $page = $this->page(['title' => 'Café — 🙂 a/b']);

        $json = $this->factory()->json($page, $this->site());

        $this->assertStringStartsWith('{"@context":"https://schema.org","@graph":[{"@type":"ProfessionalService"', $json);
        $this->assertStringContainsString('Café — 🙂 a/b', $json);
        $this->assertStringContainsString('https://example.com/services/cdr-writing/', $json);
        $this->assertStringNotContainsString('\/', $json);
        $this->assertStringNotContainsString('\u00', $json);
        $this->assertStringNotContainsString("\n", $json);

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertSame('Café — 🙂 a/b', $this->node($decoded['@graph'], 'WebPage')['name']);
    }

    public function test_json_cannot_close_a_script_tag(): void
    {
        $evil = 'x</script><!-- y';

        $json = $this->factory()->json($this->page(['title' => $evil]), $this->site());

        $this->assertStringNotContainsString('</', $json);
        $this->assertStringNotContainsString('<!', $json);
        $this->assertStringContainsString('\u003c/script>', $json);

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($evil, $this->node($decoded['@graph'], 'WebPage')['name']);
    }

    public function test_trailing_slash_of_site_url_is_removed(): void
    {
        $graph = $this->factory()->graph($this->page(), $this->site(['url' => 'https://example.com/']));

        $this->assertSame('https://example.com/#organization', $this->node($graph, 'ProfessionalService')['@id']);
        $this->assertSame('https://example.com/', $this->node($graph, 'WebSite')['url']);
        $this->assertSame(
            'https://example.com/services/cdr-writing/#webpage',
            $this->node($graph, 'WebPage')['@id']
        );
    }

    private function factory(): JsonLdFactory
    {
        return new JsonLdFactory(
            new SectionRenderer(new ContentRepository($this->data), $this->base . '/templates')
        );
    }

    /**
     * @param list<array<string, mixed>> $graph
     *
     * @return array<string, mixed>
     */
    private function node(array $graph, string $type): array
    {
        foreach ($graph as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        $this->fail("No {$type} node in the graph.");
    }

    /** @return array<string, mixed> */
    private function site(array $over = []): array
    {
        return $over + [
            'name' => 'CDR Writers Australia',
            'altName' => 'CDRWA',
            'url' => 'https://example.com',
            'tagline' => 'Engineers Australia CDR experts',
            'logo' => '/assets/icons/logo.png',
            'ogImage' => '/assets/icons/og.png',
            'email' => 'hi@example.com',
            'phone' => '+61 400 000 000',
            'address' => [
                'street' => '1 Main St',
                'locality' => 'Sydney',
                'region' => 'NSW',
                'postcode' => '2000',
                'country' => 'AU',
            ],
            'socials' => [
                ['name' => 'X', 'icon' => 'x', 'url' => 'https://x.com/cdrwa'],
                ['name' => 'LinkedIn', 'icon' => 'in', 'url' => 'https://linkedin.com/company/cdrwa'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function page(array $over = []): array
    {
        return $over + [
            'path' => '/services/cdr-writing/',
            'title' => 'CDR Writing',
            'description' => 'We write CDRs.',
            'sections' => [],
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