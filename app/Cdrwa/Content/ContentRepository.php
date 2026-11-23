<?php

namespace App\Cdrwa\Content;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Read-only access to the live JSON content (site.json, nav.json,
 * pages/*.json, shared/*.json). It never writes anything.
 *
 * File keys follow H02: "site", "nav", "pages/<name>", "shared/<name>".
 * Exception messages use these relative names, never absolute paths.
 */
class ContentRepository
{
    private const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    private const SOURCE_PATTERN = '#^shared/([a-z0-9]+(?:-[a-z0-9]+)*)\.json$#D';

    private string $dataPath;

    public function __construct(string $dataPath)
    {
        $path = rtrim(str_replace('\\', '/', trim($dataPath)), '/');

        if ($path === '') {
            throw new InvalidArgumentException('The data path is empty. Check CDRWA_DATA_PATH.');
        }

        $this->dataPath = $path;
    }

    /** @return array<string, mixed> */
    public function site(): array
    {
        return $this->readJson('site.json');
    }

    /** @return array<string, mixed> */
    public function nav(): array
    {
        return $this->readJson('nav.json');
    }

    /**
     * Names (file name without ".json") of every *.json file in data/pages,
     * sorted by full file name, byte order.
     *
     * @return list<string>
     */
    public function pageNames(): array
    {
        return $this->listNames('pages');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when $name is not a valid slug
     * @throws RuntimeException when the file is missing or is not valid JSON
     */
    public function page(string $name): array
    {
        $this->assertName($name, 'page');

        return $this->readJson("pages/{$name}.json");
    }

    /** @return list<string> */
    public function sharedNames(): array
    {
        return $this->listNames('shared');
    }

    /** @return array<string, mixed> */
    public function shared(string $name): array
    {
        $this->assertName($name, 'shared file');

        return $this->readJson("shared/{$name}.json");
    }

    /**
     * Reads a section's "source" value, for example "shared/faq.json".
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the reference is not shared/<slug>.json
     */
    public function source(string $source): array
    {
        if (preg_match(self::SOURCE_PATTERN, $source, $match) !== 1) {
            throw new InvalidArgumentException('Invalid source reference. Expected shared/<name>.json.');
        }

        return $this->shared($match[1]);
    }

    private function assertName(string $name, string $what): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException("Invalid {$what} name. Use lowercase letters, digits and single hyphens.");
        }
    }

    /** @return list<string> */
    private function listNames(string $folder): array
    {
        $dir = $this->dataPath . '/' . $folder;

        if (!is_dir($dir)) {
            throw new RuntimeException("Data folder not found: {$folder}/");
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            throw new RuntimeException("Cannot read data folder: {$folder}/");
        }

        $files = [];
        foreach ($entries as $entry) {
            if (str_ends_with($entry, '.json') && is_file($dir . '/' . $entry)) {
                $files[] = $entry;
            }
        }

        // SORT_STRING on purpose: plain sort() compares numeric-looking names
        // such as "404" as numbers.
        sort($files, SORT_STRING);

        return array_map(static fn (string $file): string => substr($file, 0, -5), $files);
    }

    /** @return array<string, mixed> */
    private function readJson(string $relative): array
    {
        $file = $this->dataPath . '/' . $relative;

        if (!is_file($file)) {
            throw new RuntimeException("Data file not found: {$relative}");
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException("Cannot read data file: {$relative}");
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $hint = str_starts_with($raw, "\xEF\xBB\xBF")
                ? ' (the file starts with a UTF-8 BOM; save it without BOM)'
                : '';

            throw new RuntimeException("Invalid JSON in {$relative}: {$e->getMessage()}{$hint}", 0, $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException("Data file must contain a JSON object: {$relative}");
        }

        return $data;
    }
}