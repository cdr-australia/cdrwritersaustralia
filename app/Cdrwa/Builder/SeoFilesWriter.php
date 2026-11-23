<?php

namespace App\Cdrwa\Builder;

use InvalidArgumentException;

/**
 * sitemap.xml, robots.txt and llms.txt. Port of buildSeoFiles() in build.js.
 *
 * files() only builds the text. write() sends it through AtomicWriter.
 * Pages with indexable=false are left out of the sitemap and llms.txt.
 * The caller passes the pages in the order they should appear.
 */
class SeoFilesWriter
{
    /**
     * @param list<array<string, mixed>> $pages
     * @param array<string, mixed> $site
     * @param string|null $today YYYY-MM-DD for <lastmod>; default is today in UTC
     *
     * @return array<string, string> relative file name => content
     *
     * @throws InvalidArgumentException when $today is not YYYY-MM-DD
     */
    public function files(array $pages, array $site, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $today) !== 1) {
            throw new InvalidArgumentException('The date must look like YYYY-MM-DD.');
        }

        $base = self::baseUrl($site);

        $indexable = array_values(array_filter(
            $pages,
            static fn ($page): bool => is_array($page) && ($page['indexable'] ?? true) !== false
        ));

        $entries = array_map(
            static function (array $page) use ($base, $today): string {
                $priority = self::truthy($page['priority'] ?? null) ? self::str($page['priority']) : '0.7';

                return "  <url>\n"
                    . '    <loc>' . self::xml($base . self::str($page['path'] ?? '')) . "</loc>\n"
                    . '    <lastmod>' . $today . "</lastmod>\n"
                    . '    <priority>' . self::xml($priority) . "</priority>\n"
                    . '  </url>';
            },
            $indexable
        );

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode("\n", $entries) . "\n"
            . "</urlset>\n";

        $robots = "User-agent: *\nAllow: /\n\nSitemap: " . $base . "/sitemap.xml\n";

        $lines = [
            '# ' . self::str($site['name'] ?? ''),
            '',
            '> ' . self::str($site['tagline'] ?? ''),
            '',
            '## Pages',
        ];
        foreach ($indexable as $page) {
            $lines[] = '- [' . self::str($page['title'] ?? '') . '](' . $base . self::str($page['path'] ?? '') . '): '
                . self::str($page['description'] ?? '');
        }
        $lines[] = '';

        return [
            'sitemap.xml' => $sitemap,
            'robots.txt' => $robots,
            'llms.txt' => implode("\n", $lines),
        ];
    }

    /**
     * Writes the three files into the output folder.
     *
     * @param list<array<string, mixed>> $pages
     * @param array<string, mixed> $site
     *
     * @return list<string> relative paths written
     */
    public function write(AtomicWriter $writer, array $pages, array $site, ?string $today = null): array
    {
        $written = [];

        foreach ($this->files($pages, $site, $today) as $relative => $content) {
            $writer->write($relative, $content);
            $written[] = $relative;
        }

        return $written;
    }

    /** @param array<string, mixed> $site */
    private static function baseUrl(array $site): string
    {
        $url = self::str($site['url'] ?? '');

        return str_ends_with($url, '/') ? substr($url, 0, -1) : $url;
    }

    private static function truthy(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0) {
            return false;
        }

        return true;
    }

    private static function str(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $value === true ? 'true' : '';
    }

    private static function xml(string $text): string
    {
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }
}