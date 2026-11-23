<?php

namespace App\Cdrwa\Builder;

use Throwable;

/**
 * Structured data (JSON-LD). Port of buildJsonLd() in build.js.
 *
 * Graph order (same as Node): ProfessionalService, WebSite, WebPage,
 * Service (only when page.service), Article (only when page.article),
 * BreadcrumbList (only when there are breadcrumbs), FAQPage (only when an
 * enabled "faq" section has items).
 *
 * null values are dropped from objects. List entries are kept.
 */
class JsonLdFactory
{
    public function __construct(private SectionRenderer $sections)
    {
    }

    /**
     * The nodes of the @graph, before null removal.
     *
     * @param array<string, mixed> $page
     * @param array<string, mixed> $site
     *
     * @return list<array<string, mixed>>
     */
    public function graph(array $page, array $site): array
    {
        $url = self::baseUrl($site);
        $path = self::str($page['path'] ?? '');
        $pageUrl = $url . $path;
        $org = ['@id' => $url . '/#organization'];

        $nodes = [];

        $address = $site['address'] ?? null;
        $socials = is_array($site['socials'] ?? null) ? $site['socials'] : [];

        $nodes[] = [
            '@type' => 'ProfessionalService',
            '@id' => $url . '/#organization',
            'name' => $site['name'] ?? null,
            'alternateName' => $site['altName'] ?? null,
            'url' => $url . '/',
            'logo' => $url . self::str(self::orElse($site['logo'] ?? null, '/assets/icons/icon-512.png')),
            'description' => $site['tagline'] ?? null,
            'telephone' => $site['phone'] ?? null,
            'email' => $site['email'] ?? null,
            'address' => is_array($address) && $address !== []
                ? [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $address['street'] ?? null,
                    'addressLocality' => $address['locality'] ?? null,
                    'addressRegion' => $address['region'] ?? null,
                    'postalCode' => $address['postcode'] ?? null,
                    'addressCountry' => $address['country'] ?? null,
                ]
                : null,
            'areaServed' => 'Worldwide',
            'sameAs' => array_map(
                static fn ($s) => is_array($s) ? ($s['url'] ?? null) : null,
                array_values($socials)
            ),
        ];

        $nodes[] = [
            '@type' => 'WebSite',
            '@id' => $url . '/#website',
            'url' => $url . '/',
            'name' => $site['name'] ?? null,
            'publisher' => $org,
            'inLanguage' => 'en-AU',
        ];

        $nodes[] = [
            '@type' => 'WebPage',
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => $page['title'] ?? null,
            'description' => $page['description'] ?? null,
            'isPartOf' => ['@id' => $url . '/#website'],
            'about' => $org,
            'inLanguage' => 'en-AU',
        ];

        $service = $page['service'] ?? null;
        if (is_array($service) && $service !== []) {
            $nodes[] = [
                '@type' => 'Service',
                '@id' => $pageUrl . '#service',
                'name' => $service['name'] ?? null,
                'serviceType' => $service['type'] ?? null,
                'description' => $page['description'] ?? null,
                'provider' => $org,
                'areaServed' => self::orElse($service['area'] ?? null, 'AU'),
            ];
        }

        $article = $page['article'] ?? null;
        if (is_array($article) && $article !== []) {
            $nodes[] = [
                '@type' => 'Article',
                '@id' => $pageUrl . '#article',
                'headline' => self::orElse($article['headline'] ?? null, $page['title'] ?? null),
                'description' => $page['description'] ?? null,
                'datePublished' => $article['published'] ?? null,
                'dateModified' => self::orElse($article['modified'] ?? null, $article['published'] ?? null),
                'author' => $org,
                'publisher' => $org,
                'mainEntityOfPage' => ['@id' => $pageUrl . '#webpage'],
                'image' => $url . self::str(self::orElse(
                    $page['ogImage'] ?? null,
                    self::orElse($site['ogImage'] ?? null, '/assets/icons/og-image.png')
                )),
            ];
        }

        $crumbs = $page['breadcrumbs'] ?? null;
        if (is_array($crumbs) && $crumbs !== []) {
            $elements = [];
            foreach (array_values($crumbs) as $index => $crumb) {
                $crumb = is_array($crumb) ? $crumb : [];
                $elements[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'] ?? null,
                    'item' => $url . self::str($crumb['path'] ?? ''),
                ];
            }

            $nodes[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $elements,
            ];
        }

        $faq = $this->faqItems($page);
        if ($faq !== []) {
            $nodes[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(
                    static fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['q'] ?? null,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['a'] ?? null,
                        ],
                    ],
                    $faq
                ),
            ];
        }

        return $nodes;
    }

    /**
     * The JSON-LD document as one compact line, ready for {{{jsonld}}}.
     * Slashes and unicode stay readable. "<" before "/" or "!" is written as
     * \u003c so the text can never close the <script> tag around it.
     *
     * @param array<string, mixed> $page
     * @param array<string, mixed> $site
     */
    public function json(array $page, array $site): string
    {
        $document = self::removeNulls([
            '@context' => 'https://schema.org',
            '@graph' => $this->graph($page, $site),
        ]);

        $json = json_encode(
            $document,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_LINE_TERMINATORS
            | JSON_THROW_ON_ERROR
        );

        return str_replace(['</', '<!'], ['\u003c/', '\u003c!'], $json);
    }

    /**
     * Items of every enabled "faq" section, in file order. A section whose
     * data cannot be read is ignored (it is skipped when rendering as well).
     *
     * @param array<string, mixed> $page
     *
     * @return list<array<string, mixed>>
     */
    private function faqItems(array $page): array
    {
        $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
        $items = [];

        foreach ($sections as $section) {
            if (
                !is_array($section)
                || ($section['type'] ?? null) !== 'faq'
                || ($section['enabled'] ?? true) === false
            ) {
                continue;
            }

            try {
                $data = $this->sections->sectionData($section);
            } catch (Throwable) {
                continue;
            }

            if (!is_array($data['items'] ?? null)) {
                continue;
            }

            foreach ($data['items'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $site */
    private static function baseUrl(array $site): string
    {
        $url = self::str($site['url'] ?? '');

        return str_ends_with($url, '/') ? substr($url, 0, -1) : $url;
    }

    /** JavaScript truthiness for JSON values (the string "0" is truthy). */
    private static function truthy(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0) {
            return false;
        }

        return true;
    }

    /** JavaScript "$value || $default". */
    private static function orElse(mixed $value, mixed $default): mixed
    {
        return self::truthy($value) ? $value : $default;
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

    /**
     * @param array<mixed> $node
     *
     * @return array<mixed>
     */
    private static function removeNulls(array $node): array
    {
        if (array_is_list($node)) {
            foreach ($node as $index => $child) {
                if (is_array($child)) {
                    $node[$index] = self::removeNulls($child);
                }
            }

            return $node;
        }

        $out = [];
        foreach ($node as $key => $child) {
            if ($child === null) {
                continue;
            }

            $out[$key] = is_array($child) ? self::removeNulls($child) : $child;
        }

        return $out;
    }
}