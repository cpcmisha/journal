<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Service;

final class JournalRelationsService
{
    public function __construct(
        private JournalRepository $journalRepository
    ) {
    }

    /**
     * Resuelve un título explícito a las entradas que lo utilizan.
     *
     * @return array{
     *     title:string,
     *     status:string,
     *     matches:array<int,array<string,mixed>>
     * }
     */
    public function resolveTitle(
        string $uid,
        string $title
    ): array {
        $title = preg_replace(
            '/\\s+/u',
            ' ',
            trim($title)
        ) ?? trim($title);

        if ($title === '') {
            return [
                'title' => '',
                'status' => 'not_found',
                'matches' => [],
            ];
        }

        $normalizedTitle = $this->normalize($title);
        $matches = [];

        foreach (
            $this->journalRepository->getAllEntries($uid)
            as $entry
        ) {
            $entryTitle = trim((string) (
                $entry['title'] ?? ''
            ));

            if (
                $entryTitle === ''
                || $this->normalize($entryTitle)
                    !== $normalizedTitle
            ) {
                continue;
            }

            $matches[] = [
                'title' => $entryTitle,
                'date' => (string) (
                    $entry['entryDate'] ?? ''
                ),
                'excerpt' => $this->buildExcerpt(
                    (string) (
                        $entry['entryContent'] ?? ''
                    )
                ),
                'fileId' => $entry['fileId'] ?? null,
                'filePath' => $entry['filePath'] ?? null,
                'created' => $entry['created'] ?? null,
                'updated' => $entry['updated'] ?? null,
            ];
        }

        usort(
            $matches,
            static fn (array $a, array $b): int =>
                strcmp(
                    (string) ($b['date'] ?? ''),
                    (string) ($a['date'] ?? '')
                )
        );

        return [
            'title' => $title,
            'status' => match (count($matches)) {
                0 => 'not_found',
                1 => 'found',
                default => 'multiple',
            },
            'matches' => $matches,
        ];
    }

    /**
     * Devuelve las relaciones explícitas de una entrada.
     *
     * @return array{
     *     date:string,
     *     title:string,
     *     outgoing:array<int,array<string,mixed>>,
     *     incoming:array<int,array<string,mixed>>
     * }
     */
    public function getRelations(
        string $uid,
        string $date,
        int $limit = 100
    ): array {
        $date = trim($date);
        $limit = max(1, min($limit, 200));

        $currentEntry = $this->journalRepository->getEntry(
            $uid,
            $date
        );

        $currentTitle = trim((string) (
            $currentEntry['title'] ?? ''
        ));

        $currentContent = (string) (
            $currentEntry['entryContent'] ?? ''
        );

        $entries = $this->journalRepository->getAllEntries($uid);

        /*
         * Construimos un índice por título normalizado para resolver
         * rápidamente los enlaces salientes.
         */
        $entriesByTitle = [];

        foreach ($entries as $entry) {
            $entryTitle = trim((string) (
                $entry['title'] ?? ''
            ));

            if ($entryTitle === '') {
                continue;
            }

            $normalizedTitle = $this->normalize($entryTitle);

            /*
             * Si existen títulos duplicados, conservamos la entrada
             * más reciente según la fecha.
             */
            if (
                !isset($entriesByTitle[$normalizedTitle])
                || strcmp(
                    (string) ($entry['entryDate'] ?? ''),
                    (string) (
                        $entriesByTitle[$normalizedTitle]['entryDate']
                        ?? ''
                    )
                ) > 0
            ) {
                $entriesByTitle[$normalizedTitle] = $entry;
            }
        }

        $outgoing = [];

        foreach ($this->extractWikiLinks($currentContent) as $linkTitle) {
            $normalizedLink = $this->normalize($linkTitle);
            $targetEntry = $entriesByTitle[$normalizedLink] ?? null;

            if ($targetEntry === null) {
                $outgoing[] = [
                    'title' => $linkTitle,
                    'exists' => false,
                    'date' => null,
                    'excerpt' => null,
                    'fileId' => null,
                    'filePath' => null,
                ];

                continue;
            }

            $outgoing[] = [
                'title' => trim((string) (
                    $targetEntry['title'] ?? $linkTitle
                )),
                'exists' => true,
                'date' => (string) (
                    $targetEntry['entryDate'] ?? ''
                ),
                'excerpt' => $this->buildExcerpt(
                    (string) (
                        $targetEntry['entryContent'] ?? ''
                    )
                ),
                'fileId' => $targetEntry['fileId'] ?? null,
                'filePath' => $targetEntry['filePath'] ?? null,
            ];
        }

        $incoming = [];

        if ($currentTitle !== '') {
            $normalizedCurrentTitle = $this->normalize(
                $currentTitle
            );

            foreach ($entries as $entry) {
                $entryDate = (string) (
                    $entry['entryDate'] ?? ''
                );

                /*
                 * No mostramos la propia nota como relación entrante.
                 */
                if ($entryDate === $date) {
                    continue;
                }

                $entryContent = (string) (
                    $entry['entryContent'] ?? ''
                );

                $linksToCurrentEntry = false;

                foreach (
                    $this->extractWikiLinks($entryContent)
                    as $wikilink
                ) {
                    if (
                        $this->normalize($wikilink)
                        === $normalizedCurrentTitle
                    ) {
                        $linksToCurrentEntry = true;
                        break;
                    }
                }

                if (!$linksToCurrentEntry) {
                    continue;
                }

                $incoming[] = [
                    'title' => trim((string) (
                        $entry['title'] ?? ''
                    )),
                    'date' => $entryDate,
                    'excerpt' => $this->buildExcerpt(
                        $entryContent
                    ),
                    'fileId' => $entry['fileId'] ?? null,
                    'filePath' => $entry['filePath'] ?? null,
                    'created' => $entry['created'] ?? null,
                    'updated' => $entry['updated'] ?? null,
                ];
            }
        }

        usort(
            $incoming,
            static fn (array $a, array $b): int =>
                strcmp(
                    (string) ($b['date'] ?? ''),
                    (string) ($a['date'] ?? '')
                )
        );

        return [
            'date' => $date,
            'title' => $currentTitle,
            'outgoing' => array_slice(
                $outgoing,
                0,
                $limit
            ),
            'incoming' => array_slice(
                $incoming,
                0,
                $limit
            ),
        ];
    }

    /**
     * @return string[]
     */
    private function extractWikiLinks(string $content): array
    {
        /*
         * Nextcloud Text puede escapar los corchetes Markdown.
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

        $result = [];
        $seen = [];

        foreach ($matches[1] ?? [] as $value) {
            $title = preg_replace(
                '/\s+/u',
                ' ',
                trim((string) $value)
            ) ?? trim((string) $value);

            if ($title === '') {
                continue;
            }

            $normalizedTitle = $this->normalize($title);

            if (isset($seen[$normalizedTitle])) {
                continue;
            }

            $seen[$normalizedTitle] = true;
            $result[] = $title;
        }

        return $result;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace(
            '/\s+/u',
            ' ',
            trim($value)
        ) ?? trim($value);

        return mb_strtolower($value, 'UTF-8');
    }

    private function buildExcerpt(string $content): string
    {
        $plain = preg_replace(
            '/\s+/u',
            ' ',
            trim($content)
        ) ?? trim($content);

        if ($plain === '') {
            return '';
        }

        return mb_strimwidth(
            $plain,
            0,
            160,
            '...',
            'UTF-8'
        );
    }
}
