<?php

namespace Tests\Unit;

use Mustache\Engine;
use Mustache\Loader\ArrayLoader;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test for mustache/mustache v3 on this PHP version.
 * It proves the rules the builder depends on (see B1 builder rules 1, 2, 4).
 * The last test only PRINTS observations; it asserts nothing about them.
 */
class MustacheSmokeTest extends TestCase
{
    private function engine(array $partials = []): Engine
    {
        return new Engine([
            // Same rule as build.js: escape ONLY & < > " ' and never touch "/" or "=".
            'escape' => static function ($value): string {
                return strtr((string) $value, [
                    '&' => '&amp;',
                    '<' => '&lt;',
                    '>' => '&gt;',
                    '"' => '&quot;',
                    "'" => '&#39;',
                ]);
            },
            'partials_loader' => new ArrayLoader($partials),
        ]);
    }

    public function test_custom_escape_only_touches_the_five_characters(): void
    {
        $out = $this->engine()->render('{{v}}', ['v' => 'Tom & "Jerry" <b> it\'s /path/?a=b']);

        $this->assertSame(
            'Tom &amp; &quot;Jerry&quot; &lt;b&gt; it&#39;s /path/?a=b',
            $out
        );
    }

    public function test_triple_mustache_is_raw(): void
    {
        $out = $this->engine()->render('{{{v}}}', ['v' => '<b>&</b>']);

        $this->assertSame('<b>&</b>', $out);
    }

    public function test_partials_render_with_the_current_context(): void
    {
        $engine = $this->engine(['header' => '<header>{{site.name}}</header>']);

        $out = $engine->render('{{> header}}<main>{{{body}}}</main>', [
            'site' => ['name' => 'A & B'],
            'body' => '<p>x</p>',
        ]);

        $this->assertSame('<header>A &amp; B</header><main><p>x</p></main>', $out);
    }

    public function test_implicit_iterator_over_list_of_strings(): void
    {
        $out = $this->engine()->render(
            '{{#items}}<li>{{.}}</li>{{/items}}',
            ['items' => ['a', 'b&c']]
        );

        $this->assertSame('<li>a</li><li>b&amp;c</li>', $out);
    }

    public function test_section_over_array_with_has_flag(): void
    {
        $tpl = '{{#has_items}}<ul>{{#items}}<li>{{.}}</li>{{/items}}</ul>{{/has_items}}';

        $full = $this->engine()->render($tpl, ['items' => ['x', 'y'], 'has_items' => true]);
        $this->assertSame('<ul><li>x</li><li>y</li></ul>', $full);

        $empty = $this->engine()->render($tpl, ['items' => [], 'has_items' => false]);
        $this->assertSame('', $empty);
    }

    public function test_section_over_list_of_objects_and_inverted_section(): void
    {
        // Same shape as page-hero.html: crumbs with a boolean "last".
        $tpl = '{{#crumbs}}<li>{{#last}}<span>{{name}}</span>{{/last}}{{^last}}<a href="{{path}}">{{name}}</a>{{/last}}</li>{{/crumbs}}';

        $out = $this->engine()->render($tpl, [
            'crumbs' => [
                ['name' => 'Home', 'path' => '/', 'last' => false],
                ['name' => 'Services', 'path' => '/services/', 'last' => true],
            ],
        ]);

        $this->assertSame(
            '<li><a href="/">Home</a></li><li><span>Services</span></li>',
            $out
        );
    }

    public function test_empty_array_section_renders_nothing(): void
    {
        $out = $this->engine()->render('[{{#items}}x{{/items}}]', ['items' => []]);

        $this->assertSame('[]', $out);
    }

    /**
     * Observation only. Prints how PHP Mustache renders falsy values so we can
     * compare with Node. Nothing is asserted about the printed values.
     */
    public function test_probe_falsy_values_prints_observations(): void
    {
        $cases = [
            'false'        => false,
            'true'         => true,
            'int 0'        => 0,
            'string "0"'   => '0',
            'empty string' => '',
            'null'         => null,
            'int 5'        => 5,
        ];

        $lines = ["\n--- MUSTACHE PROBE (PHP) ---"];
        foreach ($cases as $label => $value) {
            $variable = $this->engine()->render('[{{v}}]', ['v' => $value]);
            $section  = $this->engine()->render('[{{#v}}yes{{/v}}{{^v}}no{{/v}}]', ['v' => $value]);
            $lines[]  = sprintf('%-13s  {{v}} => %-8s  section => %s', $label, $variable, $section);
        }
        $lines[] = "--- END PROBE ---\n";

        fwrite(STDERR, implode("\n", $lines));

        $this->addToAssertionCount(1);
    }
}