<?php

namespace App\Cdrwa\Builder;

use App\Cdrwa\Content\ContentRepository;
use Closure;
use InvalidArgumentException;
use Mustache\Engine;
use Mustache\Loader\ArrayLoader;
use RuntimeException;
use Throwable;

/**
 * Renders the sections of one page (port of build.js: getSectionData, addFlags,
 * renderSection, and the sort + join inside buildPage).
 *
 * - section data = optional shared source, then inline "data" on top (data wins,
 *   shallow merge), then "filter" and "excludeSelf" on "items"
 * - addFlags adds has_<name> next to every list, at any depth
 * - a section that fails is skipped and logged; it never stops the page
 *
 * It reads templates and partials but never writes anything.
 */
class SectionRenderer
{
    private const TYPE_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    private string $templatePath;

    private ?Closure $onWarning;

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, string> */
    private array $templates = [];

    /** @var array<string, string>|null */
    private ?array $partials = null;

    private ?Engine $engine = null;

    public function __construct(
        private ContentRepository $content,
        string $templatePath,
        ?Closure $onWarning = null
    ) {
        $path = rtrim(str_replace('\\', '/', trim($templatePath)), '/');

        if ($path === '') {
            throw new InvalidArgumentException('The template path is empty. Check CDRWA_TEMPLATE_PATH.');
        }

        $this->templatePath = $path;
        $this->onWarning = $onWarning;
    }

    /**
     * Messages for every section or entry skipped so far, for example
     * [skipped] section "faq" (faq): Data file not found: shared/faq.json
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Renders any Mustache template (a section or the layout) with the shared
     * engine: custom escape, partials from templates/partials, strict callables.
     *
     * @param array<string, mixed> $view
     *
     * @throws RuntimeException when the partials folder is missing
     */
    public function render(string $template, array $view): string
    {
        return $this->engine()->render($template, $view);
    }

    /**
     * Sorts the page's sections by "order" (missing = 0, ties keep file order),
     * renders each and joins them with "\n". A disabled or failed section
     * leaves an empty entry, exactly like build.js.
     *
     * @param array<string, mixed> $page the page, already carrying "crumbs"
     * @param array<string, mixed> $site
     *
     * @throws InvalidArgumentException when "sections" is not a list
     */
    public function renderBody(array $page, array $site): string
    {
        $sections = $page['sections'] ?? [];

        if (!is_array($sections) || !array_is_list($sections)) {
            throw new InvalidArgumentException('The page "sections" value must be a list.');
        }

        usort($sections, static fn ($a, $b): int => self::orderOf($a) <=> self::orderOf($b));

        $parts = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                $this->warn('[skipped] a section entry is not an object');
                $parts[] = '';
                continue;
            }

            $parts[] = $this->renderSection($section, $page, $site);
        }

        return implode("\n", $parts);
    }

    /**
     * Renders ONE section. Never throws: on any failure it logs and returns ''.
     *
     * @param array<string, mixed> $section
     * @param array<string, mixed> $page
     * @param array<string, mixed> $site
     */
    public function renderSection(array $section, array $page, array $site): string
    {
        if (($section['enabled'] ?? true) === false) {
            return '';
        }

        try {
            $type = $section['type'] ?? null;
            if (!is_string($type) || preg_match(self::TYPE_PATTERN, $type) !== 1) {
                throw new InvalidArgumentException('Invalid section type.');
            }

            $template = $this->sectionTemplate($type);

            $view = self::addFlags($this->sectionData($section, $page));
            $view['id'] = $section['id'] ?? '';
            $view['site'] = $site;
            $view['page'] = $page;

            return $this->render($template, $view);
        } catch (Throwable $e) {
            $this->warn(sprintf(
                '[skipped] section "%s" (%s): %s',
                self::label($section['id'] ?? null),
                self::label($section['type'] ?? null),
                $e->getMessage()
            ));

            return '';
        }
    }

    /**
     * Source merged with inline data, then filter and excludeSelf on "items".
     * Without $page, excludeSelf is not applied (the FAQ structured data uses this).
     * It does NOT add has_* flags; renderSection does that.
     *
     * @param array<string, mixed> $section
     * @param array<string, mixed>|null $page
     *
     * @return array<string, mixed>
     */
    public function sectionData(array $section, ?array $page = null): array
    {
        $data = [];

        $source = $section['source'] ?? null;
        if ($source !== null && $source !== '') {
            if (!is_string($source)) {
                throw new InvalidArgumentException('The section "source" must be text such as shared/faq.json.');
            }

            $data = $this->content->source($source);
        }

        $inline = $section['data'] ?? null;
        if (is_array($inline)) {
            foreach ($inline as $key => $value) {
                $data[$key] = $value;
            }
        }

        if (isset($data['items']) && is_array($data['items']) && array_is_list($data['items'])) {
            $items = $data['items'];

            $filter = $section['filter'] ?? null;
            if (is_array($filter)) {
                if ($filter === []) {
                    throw new RuntimeException('The filter is empty. Expected one key and value, for example {"tag": "Core"}.');
                }

                $key = array_key_first($filter);
                $wanted = $filter[$key];

                $items = array_values(array_filter(
                    $items,
                    static fn ($item): bool => is_array($item)
                        && array_key_exists($key, $item)
                        && $item[$key] === $wanted
                ));
            }

            if ($page !== null && ($section['excludeSelf'] ?? false) === true) {
                $path = $page['path'] ?? null;

                $items = array_values(array_filter(
                    $items,
                    static fn ($item): bool => (is_array($item) ? ($item['href'] ?? null) : null) !== $path
                ));
            }

            $data['items'] = $items;
        }

        return $data;
    }

    /**
     * For every list at any depth, adds a sibling key has_<name> that is true
     * when the list is not empty. Only keys that exist before the call are
     * visited, so the added has_* keys are never processed themselves.
     *
     * @param array<mixed> $node
     *
     * @return array<mixed>
     */
    public static function addFlags(array $node): array
    {
        if (array_is_list($node)) {
            foreach ($node as $index => $child) {
                if (is_array($child)) {
                    $node[$index] = self::addFlags($child);
                }
            }

            return $node;
        }

        foreach (array_keys($node) as $key) {
            $child = $node[$key];

            if (!is_array($child)) {
                continue;
            }

            if (array_is_list($child)) {
                $node['has_' . $key] = $child !== [];
            }

            $node[$key] = self::addFlags($child);
        }

        return $node;
    }

    /**
     * Same rule as build.js: only & < > " ' are replaced, "/" and "=" are not.
     * Booleans print as "true" / "false" (JavaScript), not "1" / "".
     */
    private static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return strtr($value, [
                '&' => '&amp;',
                '<' => '&lt;',
                '>' => '&gt;',
                '"' => '&quot;',
                "'" => '&#39;',
            ]);
        }

        throw new RuntimeException('A list or object cannot be printed with {{ }}; use a section instead.');
    }

    private static function orderOf(mixed $section): int|float
    {
        if (is_array($section) && isset($section['order']) && is_numeric($section['order'])) {
            return $section['order'] + 0;
        }

        return 0;
    }

    private static function label(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '?';
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;

        if ($this->onWarning !== null) {
            ($this->onWarning)($message);
        }
    }

    private function engine(): Engine
    {
        return $this->engine ??= new Engine([
            'escape' => static function ($value): string {
                return self::escape($value);
            },
            'partials_loader' => new ArrayLoader($this->partials()),
            // Without this, a data string such as "date" would be CALLED as a function.
            'strict_callables' => true,
        ]);
    }

    private function sectionTemplate(string $type): string
    {
        return $this->templates[$type] ??= $this->readTemplate(
            $this->templatePath . '/sections/' . $type . '.html',
            'sections/' . $type . '.html'
        );
    }

    /** @return array<string, string> */
    private function partials(): array
    {
        if ($this->partials !== null) {
            return $this->partials;
        }

        $dir = $this->templatePath . '/partials';
        if (!is_dir($dir)) {
            throw new RuntimeException('Template folder not found: partials/');
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            throw new RuntimeException('Cannot read template folder: partials/');
        }

        $files = [];
        foreach ($entries as $entry) {
            if (str_ends_with($entry, '.html') && is_file($dir . '/' . $entry)) {
                $files[] = $entry;
            }
        }
        sort($files, SORT_STRING);

        $partials = [];
        foreach ($files as $file) {
            $partials[substr($file, 0, -5)] = $this->readTemplate($dir . '/' . $file, 'partials/' . $file);
        }

        return $this->partials = $partials;
    }

    private function readTemplate(string $file, string $label): string
    {
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$label}");
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException("Cannot read template: {$label}");
        }

        return $raw;
    }
}