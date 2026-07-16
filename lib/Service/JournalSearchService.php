<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Service;

final class JournalSearchService
{
    public function __construct(
        private JournalRepository $journalRepository
    ) {
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(
        string $uid,
        string $query,
        int $limit = 50
    ): array {
        $query = trim($query);
        $limit = max(1, min($limit, 100));

        if ($query === '') {
            return [];
        }

        $normalizedQuery = $this->normalize($query);
        $entries = $this->journalRepository->getAllEntries($uid);
        $results = [];

        foreach ($entries as $entry) {
            $result = $this->matchEntry(
                $entry,
                $query,
                $normalizedQuery
            );

            if ($result !== null) {
                $results[] = $result;
            }
        }

        usort(
            $results,
            static function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                return strcmp($b['date'], $a['date']);
            }
        );

        return array_slice($results, 0, $limit);
    }

    /**
     * Busca entradas que contienen un wikilink exacto al título indicado.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findBacklinks(
        string $uid,
        string $title,
        int $limit = 50
    ): array {
        $title = trim($title);
        $limit = max(1, min($limit, 100));

        if ($title === '') {
            return [];
        }

        $normalizedTitle = $this->normalize($title);
        $entries = $this->journalRepository->getAllEntries($uid);
        $results = [];

        foreach ($entries as $entry) {
            $content = (string) (
                $entry['entryContent'] ?? ''
            );

            $wikilinks = $this->extractWikiLinks($content);
            $matchesTitle = false;

            foreach ($wikilinks as $wikilink) {
                if (
                    $this->normalize($wikilink)
                    === $normalizedTitle
                ) {
                    $matchesTitle = true;
                    break;
                }
            }

            if (!$matchesTitle) {
                continue;
            }

            $results[] = [
                'date' => (string) (
                    $entry['entryDate'] ?? ''
                ),
                'title' => trim((string) (
                    $entry['title'] ?? ''
                )),
                'excerpt' => $this->buildExcerpt(
                    $content,
                    $title
                ),
                'categories' => $this->normalizeList(
                    $entry['categories'] ?? []
                ),
                'tags' => $this->normalizeList(
                    $entry['tags'] ?? []
                ),
                'wikilinks' => $wikilinks,
                'fileId' => $entry['fileId'] ?? null,
                'filePath' => $entry['filePath'] ?? null,
                'created' => $entry['created'] ?? null,
                'updated' => $entry['updated'] ?? null,
            ];
        }

        usort(
            $results,
            static fn (array $a, array $b): int =>
                strcmp($b['date'], $a['date'])
        );

        return array_slice($results, 0, $limit);
    }

    /**
     * @param array<string,mixed> $entry
     *
     * @return array<string,mixed>|null
     */
    private function matchEntry(
        array $entry,
        string $originalQuery,
        string $normalizedQuery
    ): ?array {
        $date = (string) ($entry['entryDate'] ?? '');
        $title = trim((string) ($entry['title'] ?? ''));
        $content = (string) ($entry['entryContent'] ?? '');

        $categories = $this->normalizeList(
            $entry['categories'] ?? []
        );

        $tags = $this->normalizeList(
            $entry['tags'] ?? []
        );

        $score = 0;
        $matches = [];

        if ($title !== '') {
            $normalizedTitle = $this->normalize($title);

            if ($normalizedTitle === $normalizedQuery) {
                $score += 150;
                $matches[] = 'title';
            } elseif (
                $normalizedQuery !== ''
                && str_contains(
                    $normalizedTitle,
                    $normalizedQuery
                )
            ) {
                $score += 105;
                $matches[] = 'title';
            }
        }

        if ($this->normalize($date) === $normalizedQuery) {
            $score += 120;
            $matches[] = 'date';
        } elseif (
            $normalizedQuery !== ''
            && str_contains(
                $this->normalize($date),
                $normalizedQuery
            )
        ) {
            $score += 70;
            $matches[] = 'date';
        }

        foreach ($categories as $category) {
            $normalizedCategory = $this->normalize($category);

            if ($normalizedCategory === $normalizedQuery) {
                $score += 100;
                $matches[] = 'category';
                break;
            }

            if (
                $normalizedQuery !== ''
                && str_contains(
                    $normalizedCategory,
                    $normalizedQuery
                )
            ) {
                $score += 75;
                $matches[] = 'category';
                break;
            }
        }

        foreach ($tags as $tag) {
            $normalizedTag = $this->normalize($tag);

            if ($normalizedTag === $normalizedQuery) {
                $score += 110;
                $matches[] = 'tag';
                break;
            }

            if (
                $normalizedQuery !== ''
                && str_contains(
                    $normalizedTag,
                    $normalizedQuery
                )
            ) {
                $score += 85;
                $matches[] = 'tag';
                break;
            }
        }

        $normalizedContent = $this->normalize($content);

        if (
            $normalizedQuery !== ''
            && str_contains(
                $normalizedContent,
                $normalizedQuery
            )
        ) {
            $score += 40;
            $matches[] = 'content';
        }

        $wikilinks = $this->extractWikiLinks($content);

        foreach ($wikilinks as $wikilink) {
            $normalizedLink = $this->normalize($wikilink);

            if ($normalizedLink === $normalizedQuery) {
                $score += 95;
                $matches[] = 'wikilink';
                break;
            }

            if (
                $normalizedQuery !== ''
                && str_contains(
                    $normalizedLink,
                    $normalizedQuery
                )
            ) {
                $score += 65;
                $matches[] = 'wikilink';
                break;
            }
        }

        if ($score === 0) {
            return null;
        }

        return [
            'date' => $date,
            'title' => $title,
            'excerpt' => $this->buildExcerpt(
                $content,
                $originalQuery
            ),
            'categories' => $categories,
            'tags' => $tags,
            'wikilinks' => $wikilinks,
            'matches' => array_values(
                array_unique($matches)
            ),
            'score' => $score,
            'fileId' => $entry['fileId'] ?? null,
            'filePath' => $entry['filePath'] ?? null,
            'created' => $entry['created'] ?? null,
            'updated' => $entry['updated'] ?? null,
        ];
    }

    /**
     * @return string[]
     */
    private function extractWikiLinks(string $content): array
    {
        /*
         * Nextcloud Text puede guardar los corchetes escapados:
         *
         * [[Proyecto]]
         * \[\[Proyecto\]\]
         *
         * Normalizamos primero el formato escapado para que ambos
         * se interpreten como wikilinks.
         */
        $normalizedContent = str_replace(
            ['\\[', '\\]'],
            ['[', ']'],
            $content
        );

        preg_match_all(
            '/\[\[([^\[\]]+)\]\]/u',
            $normalizedContent,
            $matches
        );

        return $this->normalizeList(
            $matches[1] ?? []
        );
    }

    private function buildExcerpt(
        string $content,
        string $query
    ): string {
        $plain = preg_replace(
            '/\s+/u',
            ' ',
            trim($content)
        ) ?? trim($content);

        if ($plain === '') {
            return '';
        }

        $position = mb_stripos(
            $plain,
            $query,
            0,
            'UTF-8'
        );

        if ($position === false) {
            return mb_strimwidth(
                $plain,
                0,
                160,
                '...',
                'UTF-8'
            );
        }

        $start = max(0, $position - 45);

        if ($start > 0) {
            $prefix = '...';
        } else {
            $prefix = '';
        }

        $excerpt = mb_substr(
            $plain,
            $start,
            160,
            'UTF-8'
        );

        $suffix = mb_strlen($plain, 'UTF-8')
            > $start + 160
            ? '...'
            : '';

        return $prefix.$excerpt.$suffix;
    }

    /**
     * @param mixed $values
     *
     * @return string[]
     */
    private function normalizeList(mixed $values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $key = $this->normalize($value);

            if (!isset($normalized[$key])) {
                $normalized[$key] = $value;
            }
        }

        return array_values($normalized);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(
            trim($value),
            'UTF-8'
        );

        $transliterated = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?? $value;

        return trim(
            preg_replace('/\s+/', ' ', $value) ?? $value
        );
    }
}
