<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Service;

final class FrontMatterService
{
    /**
     * @return array{
     *     content:string,
     *     metadata:array<string,mixed>,
     *     hasFrontMatter:bool
     * }
     */
    public function decode(string $markdown): array
    {
        $markdown = preg_replace('/^\xEF\xBB\xBF/', '', $markdown) ?? $markdown;

        if (!preg_match(
            '/\A---\R(.*?)\R---(?:\R|$)(.*)\z/s',
            $markdown,
            $matches
        )) {
            return [
                'content' => $markdown,
                'metadata' => [],
                'hasFrontMatter' => false,
            ];
        }

        return [
            'content' => $matches[2],
            'metadata' => $this->parseYaml($matches[1]),
            'hasFrontMatter' => true,
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function encode(string $content, array $metadata): string
    {
        $categories = $this->normalizeCategories(
            $metadata['categories'] ?? []
        );

        $lines = [
            '---',
            'journal_version: '.(int) ($metadata['journal_version'] ?? 1),
            'date: '.$this->quote((string) ($metadata['date'] ?? '')),
            'title: '.$this->quote(
                $this->normalizeTitle($metadata['title'] ?? '')
            ),
            'categories:',
        ];

        foreach ($categories as $category) {
            $lines[] = '  - '.$this->quote($category);
        }

        if ($categories === []) {
            $lines[count($lines) - 1] = 'categories: []';
        }

        $lines[] = 'created: '.$this->quote(
            (string) ($metadata['created'] ?? '')
        );
        $lines[] = 'updated: '.$this->quote(
            (string) ($metadata['updated'] ?? '')
        );
        $lines[] = '---';
        $lines[] = '';

        return implode("\n", $lines).ltrim($content, "\r\n");
    }

    /**
     * Prepara los metadatos administrados por Journal.
     *
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $incoming
     *
     * @return array<string,mixed>
     */
    public function prepareMetadata(
        string $date,
        array $existing,
        array $incoming = []
    ): array {
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $merged = array_replace($existing, $incoming);

        return [
            'journal_version' => 1,
            'date' => $date,
            'title' => $this->normalizeTitle(
                $merged['title'] ?? ''
            ),
            'categories' => $this->normalizeCategories(
                $merged['categories'] ?? []
            ),
            'created' => $this->normalizeDateValue(
                $existing['created'] ?? null,
                $now
            ),
            'updated' => $now,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function parseYaml(string $yaml): array
    {
        $metadata = [];
        $currentList = null;

        foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (
                $currentList !== null
                && preg_match('/^\s*-\s*(.*)$/', $line, $matches)
            ) {
                $metadata[$currentList][] = $this->unquote(
                    trim($matches[1])
                );
                continue;
            }

            if (!preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $matches)) {
                $currentList = null;
                continue;
            }

            $key = $matches[1];
            $value = trim($matches[2]);

            if ($value === '') {
                $metadata[$key] = [];
                $currentList = $key;
                continue;
            }

            $currentList = null;

            if ($value === '[]') {
                $metadata[$key] = [];
            } elseif ($key === 'journal_version' && ctype_digit($value)) {
                $metadata[$key] = (int) $value;
            } else {
                $metadata[$key] = $this->unquote($value);
            }
        }

        if (isset($metadata['categories'])) {
            $metadata['categories'] = $this->normalizeCategories(
                $metadata['categories']
            );
        }

        return $metadata;
    }

    /**
     * @param mixed $categories
     *
     * @return string[]
     */
    private function normalizeCategories(mixed $categories): array
    {
        if (is_string($categories)) {
            $categories = [$categories];
        }

        if (!is_array($categories)) {
            return [];
        }

        $normalized = [];

        foreach ($categories as $category) {
            $category = trim((string) $category);

            if ($category === '') {
                continue;
            }

            $duplicate = false;

            foreach ($normalized as $existing) {
                if (mb_strtolower($existing) === mb_strtolower($category)) {
                    $duplicate = true;
                    break;
                }
            }

            if (!$duplicate) {
                $normalized[] = $category;
            }
        }

        return $normalized;
    }

    private function quote(string $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    private function unquote(string $value): string
    {
        if (
            strlen($value) >= 2
            && $value[0] === '"'
            && $value[strlen($value) - 1] === '"'
        ) {
            try {
                $decoded = json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                return is_string($decoded) ? $decoded : $value;
            } catch (\JsonException $e) {
                return trim($value, '"');
            }
        }

        if (
            strlen($value) >= 2
            && $value[0] === "'"
            && $value[strlen($value) - 1] === "'"
        ) {
            return str_replace(
                "''",
                "'",
                substr($value, 1, -1)
            );
        }

        return $value;
    }

    private function normalizeTitle(mixed $value): string
    {
        $value = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $value)
        ) ?? trim((string) $value);

        return mb_strimwidth(
            $value,
            0,
            180,
            '',
            'UTF-8'
        );
    }

    private function normalizeDateValue(
        mixed $value,
        string $fallback
    ): string {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }
}
