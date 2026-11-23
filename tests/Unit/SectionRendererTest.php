<?php

namespace Tests\Unit;

use App\Cdrwa\Builder\SectionRenderer;
use App\Cdrwa\Content\ContentRepository;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class SectionRendererTest extends TestCase
{
    private string $base;

    private string $templates;

    private string $data;

    /** @var list<string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $this->base = $tmp . '/cdrwa-render-test-' . bin2hex(random_bytes(6));
        $this->templates = $this->base . '/templates';
        $this->data = $this->base . '/data';

        mkdir($this->templates . '/sections', 0777, true);
        mkdir($this->templates . '/partials', 0777, true);
        mkdir($this->data . '/shared', 0777, true);

        $this->warnings = [];
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);

        parent::tearDown();
    }

    public function test_renders_inline_data_with_escaping(): void
    {
        $this->template('box', '<h2>{{heading}}</h2>');

        $out = $this->renderer()->renderSection(
            $this->section('a', 'box', 1, ['data' => ['heading' => 'Tom & "Jerry" <b>']]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('<h2>Tom &amp; &quot;Jerry&quot; &lt;b&gt;</h2>', $out);
        $this->assertSame([], $this->warnings);
    }

    public function test_source_is_merged_and_inline_data_wins(): void
    {
        $this->shared('faq', [
            'heading' => 'Shared',
            'items' => [['q' => 'Q1', 'a' => 'A1'], ['q' => 'Q2', 'a' => 'A2']],
        ]);
        $this->template('faq', '{{heading}}|{{#items}}{{q}},{{/items}}');

        $out = $this->renderer()->renderSection(
            $this->section('f', 'faq', 1, ['source' => 'shared/faq.json', 'data' => ['heading' => 'Own']]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('Own|Q1,Q2,', $out);
    }

    public function test_inline_data_replaces_whole_lists_without_deep_merge(): void
    {
        $this->shared('faq', ['items' => [['q' => 'A'], ['q' => 'B']]]);
        $this->template('faq', '{{#items}}{{q}};{{/items}}');

        $out = $this->renderer()->renderSection(
            $this->section('f', 'faq', 1, ['source' => 'shared/faq.json', 'data' => ['items' => [['q' => 'C']]]]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('C;', $out);
    }

    public function test_filter_keeps_only_matching_items(): void
    {
        $this->shared('services', ['items' => [
            ['title' => 'One', 'tag' => 'Core'],
            ['title' => 'Two', 'tag' => 'Use'],
            ['title' => 'Three', 'tag' => 'Core'],
        ]]);
        $this->template('grid', '{{#items}}{{title}};{{/items}}');

        $out = $this->renderer()->renderSection(
            $this->section('s', 'grid', 1, ['source' => 'shared/services.json', 'filter' => ['tag' => 'Core']]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('One;Three;', $out);
    }

    public function test_filter_applies_to_inline_items_and_empty_result_clears_flag(): void
    {
        $this->template('grid', '{{#has_items}}some{{/has_items}}{{^has_items}}none{{/has_items}}');
        $items = [['title' => 'x', 'tag' => 'Use']];

        $none = $this->renderer()->renderSection(
            $this->section('s', 'grid', 1, ['data' => ['items' => $items], 'filter' => ['tag' => 'Core']]),
            $this->page(),
            $this->site()
        );
        $some = $this->renderer()->renderSection(
            $this->section('s', 'grid', 1, ['data' => ['items' => $items], 'filter' => ['tag' => 'Use']]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('none', $none);
        $this->assertSame('some', $some);
    }

    public function test_exclude_self_drops_the_current_page(): void
    {
        $this->shared('guides', ['items' => [
            ['title' => 'A', 'href' => '/a/'],
            ['title' => 'B', 'href' => '/b/'],
        ]]);
        $this->template('list', '{{#items}}{{title}};{{/items}}');

        $out = $this->renderer()->renderSection(
            $this->section('g', 'list', 1, ['source' => 'shared/guides.json', 'excludeSelf' => true]),
            $this->page([], '/a/'),
            $this->site()
        );

        $this->assertSame('B;', $out);
    }

    public function test_exclude_self_needs_the_flag_and_a_page(): void
    {
        $this->shared('guides', ['items' => [
            ['title' => 'A', 'href' => '/a/'],
            ['title' => 'B', 'href' => '/b/'],
        ]]);
        $this->template('list', '{{#items}}{{title}};{{/items}}');
        $renderer = $this->renderer();

        $withoutFlag = $renderer->renderSection(
            $this->section('g', 'list', 1, ['source' => 'shared/guides.json']),
            $this->page([], '/a/'),
            $this->site()
        );
        $this->assertSame('A;B;', $withoutFlag);

        // No page given (the FAQ structured data case): excludeSelf is not applied.
        $data = $renderer->sectionData(
            $this->section('g', 'list', 1, ['source' => 'shared/guides.json', 'excludeSelf' => true])
        );
        $this->assertCount(2, $data['items']);
        $this->assertArrayNotHasKey('has_items', $data);
    }

    public function test_empty_filter_is_skipped_and_logged(): void
    {
        $this->template('grid', '{{#items}}{{title}}{{/items}}');

        $out = $this->renderer()->renderSection(
            $this->section('s', 'grid', 1, ['data' => ['items' => [['title' => 'x']]], 'filter' => []]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('', $out);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('filter', $this->warnings[0]);
    }

    public function test_disabled_section_renders_nothing_and_is_not_an_error(): void
    {
        // No template exists for "ghost": a disabled section must not even look for it.
        $out = $this->renderer()->renderSection(
            $this->section('g', 'ghost', 1, ['enabled' => false]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('', $out);
        $this->assertSame([], $this->warnings);
    }

    public function test_sections_are_sorted_by_order_and_ties_keep_file_order(): void
    {
        $this->template('item', '[{{id}}]');

        $page = $this->page([
            $this->section('a', 'item', 3),
            $this->section('b', 'item', 1),
            $this->section('c', 'item', 2),
            $this->section('d', 'item', 2),
            ['id' => 'e', 'type' => 'item'], // no order = 0
        ]);

        $this->assertSame(
            "[e]\n[b]\n[c]\n[d]\n[a]",
            $this->renderer()->renderBody($page, $this->site())
        );
    }

    public function test_disabled_sections_leave_their_join_newline(): void
    {
        $this->template('item', '[{{id}}]');

        $page = $this->page([
            $this->section('x', 'item', 1),
            $this->section('y', 'item', 2, ['enabled' => false]),
            $this->section('z', 'item', 3),
        ]);

        $this->assertSame("[x]\n\n[z]", $this->renderer()->renderBody($page, $this->site()));
    }

    public function test_add_flags_adds_has_keys_at_every_depth(): void
    {
        $out = SectionRenderer::addFlags([
            'items' => [
                ['title' => 'a', 'bullets' => ['x']],
                ['title' => 'b', 'bullets' => []],
            ],
            'empty' => [],
            'obj' => ['list' => [1, 2]],
            'text' => 's',
        ]);

        $this->assertTrue($out['has_items']);
        $this->assertTrue($out['items'][0]['has_bullets']);
        $this->assertFalse($out['items'][1]['has_bullets']);
        $this->assertFalse($out['has_empty']);
        $this->assertTrue($out['obj']['has_list']);

        // Only lists get a flag; objects, strings and the flags themselves do not.
        $this->assertArrayNotHasKey('has_obj', $out);
        $this->assertArrayNotHasKey('has_text', $out);
        $this->assertArrayNotHasKey('has_has_items', $out);
        $this->assertArrayNotHasKey('has_title', $out['items'][0]);

        // A list of plain strings is left alone.
        $this->assertSame([1, 2], $out['obj']['list']);
        $this->assertCount(2, $out['items']);
    }

    public function test_templates_can_use_nested_has_flags(): void
    {
        $this->template(
            'prose',
            '{{#items}}[{{title}}{{#has_bullets}}:{{#bullets}}{{.}},{{/bullets}}{{/has_bullets}}]{{/items}}'
        );

        $out = $this->renderer()->renderSection(
            $this->section('p', 'prose', 1, ['data' => ['items' => [
                ['title' => 'a', 'bullets' => ['x', 'y']],
                ['title' => 'b', 'bullets' => []],
            ]]]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('[a:x,y,][b]', $out);
    }

    public function test_failing_section_is_skipped_logged_and_others_render(): void
    {
        $this->template('item', '[{{id}}]');
        $this->template('broken', '{{#never-closed}}');

        $page = $this->page([
            $this->section('a', 'item', 1),
            $this->section('bad', 'nope', 2),       // no template file
            $this->section('broken', 'broken', 3),  // template that does not parse
            $this->section('c', 'item', 4),
        ]);

        $out = $this->renderer()->renderBody($page, $this->site());

        $this->assertSame("[a]\n\n\n[c]", $out);
        $this->assertCount(2, $this->warnings);
        $this->assertStringContainsString('section "bad" (nope)', $this->warnings[0]);
        $this->assertStringContainsString('section "broken" (broken)', $this->warnings[1]);
    }

    public function test_bad_source_is_skipped_not_fatal(): void
    {
        $this->template('faq', '{{heading}}');
        $renderer = $this->renderer();

        foreach (['shared/missing.json', '../site.json', 'pages/home.json'] as $source) {
            $out = $renderer->renderSection(
                $this->section('f', 'faq', 1, ['source' => $source]),
                $this->page(),
                $this->site()
            );
            $this->assertSame('', $out);
        }

        $this->assertCount(3, $this->warnings);
    }

    public function test_unsafe_type_names_are_rejected(): void
    {
        $this->template('box', 'ok');
        file_put_contents($this->templates . '/layout.html', 'LEAK');
        $renderer = $this->renderer();

        $types = ['../layout', 'a/b', 'Box', '', 'box.html', "box\n"];
        foreach ($types as $type) {
            $out = $renderer->renderSection(
                $this->section('x', $type, 1),
                $this->page(),
                $this->site()
            );
            $this->assertSame('', $out, 'Rendered type: ' . json_encode($type));
        }

        // A section with no "type" at all.
        $this->assertSame('', $renderer->renderSection(['id' => 'x', 'order' => 1], $this->page(), $this->site()));

        $this->assertCount(count($types) + 1, $this->warnings);
    }

    public function test_non_object_section_entry_is_skipped(): void
    {
        $this->template('item', '[{{id}}]');

        $page = $this->page(['junk', $this->section('a', 'item', 1)]);

        $this->assertSame("\n[a]", $this->renderer()->renderBody($page, $this->site()));
        $this->assertCount(1, $this->warnings);
    }

    public function test_sections_that_are_not_a_list_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->renderer()->renderBody(['path' => '/p/', 'sections' => ['a' => 1]], $this->site());
    }

    public function test_view_always_has_site_page_and_id_from_the_section(): void
    {
        $this->template('box', '{{site.name}}|{{page.title}}|{{id}}|{{heading}}');

        $out = $this->renderer()->renderSection(
            $this->section('real', 'box', 1, ['data' => [
                'id' => 'evil',
                'site' => 'x',
                'page' => 'y',
                'heading' => 'H',
            ]]),
            ['path' => '/p/', 'title' => 'Title'],
            ['name' => 'Site']
        );

        $this->assertSame('Site|Title|real|H', $out);
    }

    public function test_partials_are_available_inside_sections(): void
    {
        file_put_contents($this->templates . '/partials/icon.html', '<i>{{name}}</i>');
        $this->template('box', '{{> icon}}');

        $out = $this->renderer()->renderSection(
            $this->section('b', 'box', 1, ['data' => ['name' => 'Zed & Co']]),
            $this->page(),
            $this->site()
        );

        $this->assertSame('<i>Zed &amp; Co</i>', $out);
    }

    /**
     * Depends on Mustache calling the escape callback for false. If this fails
     * with an empty string for {{b}}, PHP Mustache short-circuits false.
     */
    public function test_escape_prints_booleans_and_numbers_like_node(): void
    {
        $out = $this->renderer()->render(
            '{{a}}|{{b}}|{{c}}|{{d}}|{{e}}|{{f}}',
            ['a' => true, 'b' => false, 'c' => 0, 'd' => 5, 'e' => 0.5, 'f' => null]
        );

        $this->assertSame('true|false|0|5|0.5|', $out);
    }

    /** Depends on Mustache letting an exception from the escape callback through. */
    public function test_escape_refuses_lists_and_objects(): void
    {
        $this->expectException(RuntimeException::class);

        $this->renderer()->render('{{a}}', ['a' => ['x', 'y']]);
    }

    /** Depends on the strict_callables option (seen in Engine.php line 354). */
    public function test_callable_looking_strings_stay_plain_text(): void
    {
        $renderer = $this->renderer();

        $this->assertSame('strtoupper|date', $renderer->render('{{a}}|{{b}}', ['a' => 'strtoupper', 'b' => 'date']));
        $this->assertSame('x', $renderer->render('{{#a}}x{{/a}}', ['a' => 'strtoupper']));
        $this->assertSame('y', $renderer->render('{{#b}}y{{/b}}', ['b' => 'date']));
    }

    public function test_missing_partials_folder_is_reported(): void
    {
        $renderer = new SectionRenderer(new ContentRepository($this->data), $this->base . '/missing-templates');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('partials');

        $renderer->render('x', []);
    }

    public function test_constructor_rejects_blank_template_path(): void
    {
        foreach (['', '   ', '/'] as $path) {
            try {
                new SectionRenderer(new ContentRepository($this->data), $path);
                $this->fail('Accepted template path: ' . json_encode($path));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function renderer(): SectionRenderer
    {
        return new SectionRenderer(
            new ContentRepository($this->data),
            $this->templates,
            function (string $message): void {
                $this->warnings[] = $message;
            }
        );
    }

    /** @return array<string, mixed> */
    private function section(string $id, string $type, int $order, array $extra = []): array
    {
        return ['id' => $id, 'type' => $type, 'order' => $order] + $extra;
    }

    /** @return array<string, mixed> */
    private function page(array $sections = [], string $path = '/p/'): array
    {
        return ['path' => $path, 'title' => 'Title', 'sections' => $sections];
    }

    /** @return array<string, mixed> */
    private function site(): array
    {
        return ['name' => 'Site'];
    }

    private function template(string $type, string $body): void
    {
        file_put_contents($this->templates . '/sections/' . $type . '.html', $body);
    }

    private function shared(string $name, array $content): void
    {
        file_put_contents(
            $this->data . '/shared/' . $name . '.json',
            json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
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