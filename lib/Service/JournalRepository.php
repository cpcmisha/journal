<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Service;

use OCA\JournalNotes\Db\Entry;
use OCA\JournalNotes\Db\EntryMapper;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

/**
 * Punto único de lectura de las entradas de Journal.
 *
 * La base de datos se utiliza como índice.
 * El archivo Markdown es la fuente principal del contenido y del YAML.
 * Las etiquetas se leen desde las System Tags del archivo.
 */
final class JournalRepository
{
    private const OBJECT_TYPE_FILES = 'files';

    public function __construct(
        private EntryMapper $entryMapper,
        private JournalFileService $journalFileService,
        private ISystemTagManager $tagManager,
        private ISystemTagObjectMapper $tagMapper,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Devuelve una entrada completamente hidratada.
     *
     * @return array{
     *     id:string,
     *     uid:string,
     *     entryDate:string,
     *     entryContent:string,
     *     metadata:array<string,mixed>,
     *     categories:string[],
     *     tags:string[],
     *     fileId:int|null,
     *     filePath:string|null,
     *     created:string|null,
     *     updated:string|null
     * }
     */
    public function getEntry(string $uid, string $date): array
    {
        /** @var Entry $entry */
        $entry = $this->entryMapper->find($uid, $date);

        return $this->hydrate($entry);
    }

    /**
     * Devuelve las entradas recientes hidratadas desde sus archivos.
     *
     * @return array<int,array{
     *     date:string,
     *     excerpt:string,
     *     categories:string[],
     *     tags:string[],
     *     fileId:int|null,
     *     filePath:string|null,
     *     created:string|null,
     *     updated:string|null
     * }>
     */
    public function getLastEntries(
        string $uid,
        int $amount
    ): array {
        $amount = max(1, min($amount, 500));

        $entries = $this->entryMapper->findLast(
            $uid,
            $amount
        );

        $result = [];

        foreach ($entries as $entry) {
            try {
                $hydrated = $this->hydrate($entry);

                $result[] = [
                    'date' => $hydrated['entryDate'],
                    'excerpt' => mb_strimwidth(
                        $this->normalizeExcerpt(
                            $hydrated['entryContent']
                        ),
                        0,
                        80,
                        '...',
                        'UTF-8'
                    ),
                    'categories' => $hydrated['categories'],
                    'tags' => $hydrated['tags'],
                    'fileId' => $hydrated['fileId'],
                    'filePath' => $hydrated['filePath'],
                    'created' => $hydrated['created'],
                    'updated' => $hydrated['updated'],
                ];
            } catch (\Throwable $e) {
                /*
                 * Un archivo dañado no debe impedir que se cargue toda
                 * la navegación. Conservamos un registro en el log.
                 */
                $this->logger->warning(
                    'Could not hydrate Journal entry '
                    .$entry->getEntryDate()
                    .': '
                    .$e->getMessage()
                );
            }
        }

        return $result;
    }

    /**
     * Devuelve todas las entradas del usuario.
     *
     * Se utilizará después para la búsqueda global.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAllEntries(string $uid): array
    {
        $entries = $this->entryMapper->findAll($uid);
        $result = [];

        foreach ($entries as $entry) {
            try {
                $result[] = $this->hydrate($entry);
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Could not hydrate Journal entry '
                    .$entry->getEntryDate()
                    .': '
                    .$e->getMessage()
                );
            }
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function hydrate(Entry $entry): array
    {
        $content = (string) $entry->getEntryContent();
        $databaseMetadata = $entry->getMetadataArray();
        $fileMetadata = [];

        $filePath = $this->normalizeNullableString(
            $entry->getFilePath()
        );

        $fileId = $entry->getFileId() !== null
            ? (int) $entry->getFileId()
            : null;

        /*
         * El Markdown y su Front Matter son la fuente principal.
         * Si el archivo no se puede leer, se conserva el respaldo SQL.
         */
        if ($filePath !== null) {
            try {
                $document = $this->journalFileService->readDocument(
                    (string) $entry->getUid(),
                    $filePath
                );

                $content = (string) $document['content'];
                $fileMetadata = is_array($document['metadata'])
                    ? $document['metadata']
                    : [];
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Could not read Journal Markdown file '
                    .$filePath
                    .': '
                    .$e->getMessage()
                );
            }
        }

        $categories = $this->extractCategories(
            $fileMetadata !== []
                ? $fileMetadata
                : $databaseMetadata
        );

        $tags = $fileId !== null && $fileId > 0
            ? $this->getFileTagNames($fileId)
            : $this->extractLegacyTags($databaseMetadata);

        $metadata = array_replace(
            $databaseMetadata,
            $fileMetadata
        );

        $metadata['categories'] = $categories;
        $metadata['tags'] = $tags;
        unset($metadata['category']);

        return [
            'id' => (string) $entry->getId(),
            'uid' => (string) $entry->getUid(),
            'entryDate' => (string) $entry->getEntryDate(),
            'entryContent' => $content,
            'metadata' => $metadata,
            'categories' => $categories,
            'tags' => $tags,
            'fileId' => $fileId,
            'filePath' => $filePath,
            'created' => $this->normalizeNullableString(
                $fileMetadata['created'] ?? null
            ),
            'updated' => $this->normalizeNullableString(
                $fileMetadata['updated'] ?? null
            ),
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     *
     * @return string[]
     */
    private function extractCategories(array $metadata): array
    {
        $categories = $metadata['categories'] ?? [];

        if (
            (!is_array($categories) || $categories === [])
            && isset($metadata['category'])
            && is_string($metadata['category'])
        ) {
            $categories = [$metadata['category']];
        }

        return $this->normalizeStringList($categories);
    }

    /**
     * @param array<string,mixed> $metadata
     *
     * @return string[]
     */
    private function extractLegacyTags(array $metadata): array
    {
        return $this->normalizeStringList(
            $metadata['tags'] ?? []
        );
    }

    /**
     * @return string[]
     */
    private function getFileTagNames(int $fileId): array
    {
        $objectId = (string) $fileId;

        $relations = $this->tagMapper->getTagIdsForObjects(
            $objectId,
            self::OBJECT_TYPE_FILES
        );

        $tagIds = array_values(array_unique(array_map(
            'strval',
            array_values($relations[$objectId] ?? [])
        )));

        if ($tagIds === []) {
            return [];
        }

        $tags = $this->tagManager->getTagsByIds($tagIds);

        $names = array_map(
            static fn (ISystemTag $tag): string =>
                trim($tag->getName()),
            array_values($tags)
        );

        $names = array_values(array_filter(
            $names,
            static fn (string $name): bool => $name !== ''
        ));

        natcasesort($names);

        return array_values($names);
    }

    /**
     * @param mixed $values
     *
     * @return string[]
     */
    private function normalizeStringList(mixed $values): array
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

            $exists = false;

            foreach ($normalized as $current) {
                if (
                    mb_strtolower($current, 'UTF-8')
                    === mb_strtolower($value, 'UTF-8')
                ) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeExcerpt(string $content): string
    {
        $content = preg_replace(
            '/\s+/u',
            ' ',
            $content
        ) ?? $content;

        return trim($content);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
