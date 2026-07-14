<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Controller;

use OCA\JournalNotes\Db\Entry;
use OCA\JournalNotes\Db\EntryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\SystemTag\TagCreationForbiddenException;
use OCP\SystemTag\TagNotFoundException;

class SystemTagController extends Controller
{
    /**
     * Tipo oficial utilizado por Nextcloud para archivos.
     */
    private const OBJECT_TYPE_FILES = 'files';

    /**
     * Tipo utilizado por las versiones anteriores de Journal.
     * Se conserva únicamente para migrar relaciones existentes.
     */
    private const OBJECT_TYPE_LEGACY = 'journalnotes';

    public function __construct(
        string $AppName,
        IRequest $request,
        private ISystemTagManager $tagManager,
        private ISystemTagObjectMapper $tagMapper,
        private IUserSession $userSession,
        private EntryMapper $entryMapper,
        private ?string $UserId
    ) {
        parent::__construct($AppName, $request);
    }

    /**
     * Devuelve las etiquetas globales visibles y asignables.
     *
     * @NoAdminRequired
     */
    public function listTags(): DataResponse
    {
        $tags = array_filter(
            $this->tagManager->getAllTags(true),
            static fn (ISystemTag $tag): bool =>
                $tag->isUserVisible()
                && $tag->isUserAssignable()
        );

        $response = array_map(
            fn (ISystemTag $tag): array => $this->serializeTag($tag),
            array_values($tags)
        );

        usort(
            $response,
            static fn (array $a, array $b): int =>
                strcasecmp($a['name'], $b['name'])
        );

        return new DataResponse($response);
    }

    /**
     * Crea una etiqueta global visible y asignable.
     *
     * @NoAdminRequired
     */
    public function createTag(string $name): DataResponse
    {
        $name = trim($name);

        if ($name === '') {
            return new DataResponse(
                ['error' => 'El nombre de la etiqueta es obligatorio'],
                Http::STATUS_BAD_REQUEST
            );
        }

        foreach ($this->tagManager->getAllTags(true, $name) as $tag) {
            if (
                $tag->isUserVisible()
                && $tag->isUserAssignable()
                && mb_strtolower($tag->getName())
                    === mb_strtolower($name)
            ) {
                return new DataResponse(
                    $this->serializeTag($tag) + ['created' => false]
                );
            }
        }

        try {
            $tag = $this->tagManager->createTag(
                $name,
                true,
                true
            );

            return new DataResponse(
                $this->serializeTag($tag) + ['created' => true],
                Http::STATUS_CREATED
            );
        } catch (TagAlreadyExistsException $e) {
            foreach ($this->tagManager->getAllTags(true, $name) as $tag) {
                if (
                    mb_strtolower($tag->getName())
                    === mb_strtolower($name)
                ) {
                    return new DataResponse(
                        $this->serializeTag($tag)
                        + ['created' => false]
                    );
                }
            }

            return new DataResponse(
                ['error' => 'La etiqueta ya existe'],
                Http::STATUS_CONFLICT
            );
        } catch (TagCreationForbiddenException $e) {
            return new DataResponse(
                ['error' => 'No tienes permiso para crear etiquetas'],
                Http::STATUS_FORBIDDEN
            );
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Devuelve las etiquetas asignadas al archivo Markdown de la entrada.
     *
     * Las relaciones antiguas de tipo "journalnotes" se migran automáticamente
     * al archivo real durante la primera lectura.
     *
     * @NoAdminRequired
     */
    public function getEntryTags(string $date): DataResponse
    {
        if (!$this->isValidDate($date)) {
            return new DataResponse(
                ['error' => 'Fecha inválida'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $entry = $this->getEntry($date);
            $fileObjectId = $this->getFileObjectId($entry);

            $this->migrateLegacyRelations(
                $date,
                $fileObjectId
            );

            $tagIds = $this->getObjectTagIds(
                $fileObjectId,
                self::OBJECT_TYPE_FILES
            );

            return new DataResponse(
                $this->serializeTagsByIds($tagIds)
            );
        } catch (DoesNotExistException $e) {
            return new DataResponse([]);
        } catch (MultipleObjectsReturnedException $e) {
            return new DataResponse(
                ['error' => 'Existe más de una entrada para esta fecha'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\UnexpectedValueException $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_CONFLICT
            );
        } catch (TagNotFoundException $e) {
            return new DataResponse(
                ['error' => 'Una de las etiquetas ya no existe'],
                Http::STATUS_NOT_FOUND
            );
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Reemplaza las etiquetas del archivo Markdown correspondiente.
     *
     * @param string[] $tagIds
     * @NoAdminRequired
     */
    public function updateEntryTags(
        string $date,
        array $tagIds = []
    ): DataResponse {
        if (!$this->isValidDate($date)) {
            return new DataResponse(
                ['error' => 'Fecha inválida'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $user = $this->userSession->getUser();

        if ($user === null || $this->UserId === null) {
            return new DataResponse(
                ['error' => 'Usuario no autenticado'],
                Http::STATUS_UNAUTHORIZED
            );
        }

        $tagIds = $this->normalizeTagIds($tagIds);

        try {
            $entry = $this->getEntry($date);
            $fileObjectId = $this->getFileObjectId($entry);

            $tags = $tagIds === []
                ? []
                : $this->tagManager->getTagsByIds(
                    $tagIds,
                    $user
                );

            foreach ($tags as $tag) {
                if (!$this->tagManager->canUserAssignTag($tag, $user)) {
                    return new DataResponse(
                        [
                            'error' =>
                                'No tienes permiso para asignar la etiqueta '
                                .$tag->getName(),
                        ],
                        Http::STATUS_FORBIDDEN
                    );
                }
            }

            /*
             * Copia primero cualquier relación antigua al archivo.
             * Después se aplica exactamente la selección enviada.
             */
            $this->migrateLegacyRelations(
                $date,
                $fileObjectId
            );

            $currentIds = $this->getObjectTagIds(
                $fileObjectId,
                self::OBJECT_TYPE_FILES
            );

            $toAssign = array_values(
                array_diff($tagIds, $currentIds)
            );

            $toUnassign = array_values(
                array_diff($currentIds, $tagIds)
            );

            if ($toAssign !== []) {
                $this->tagMapper->assignTags(
                    $fileObjectId,
                    self::OBJECT_TYPE_FILES,
                    $toAssign
                );
            }

            if ($toUnassign !== []) {
                $this->tagMapper->unassignTags(
                    $fileObjectId,
                    self::OBJECT_TYPE_FILES,
                    $toUnassign
                );
            }

            return new DataResponse(
                $this->serializeTags($tags)
            );
        } catch (DoesNotExistException $e) {
            return new DataResponse(
                [
                    'error' =>
                        'Primero debes escribir y guardar la entrada',
                ],
                Http::STATUS_NOT_FOUND
            );
        } catch (MultipleObjectsReturnedException $e) {
            return new DataResponse(
                ['error' => 'Existe más de una entrada para esta fecha'],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\UnexpectedValueException $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_CONFLICT
            );
        } catch (TagNotFoundException $e) {
            return new DataResponse(
                ['error' => 'Una de las etiquetas seleccionadas no existe'],
                Http::STATUS_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Migra las relaciones antiguas:
     *
     * diary:misha2026-07-13 → files:22914
     */
    private function migrateLegacyRelations(
        string $date,
        string $fileObjectId
    ): void {
        $legacyObjectId = $this->getLegacyObjectId($date);

        $legacyIds = $this->getObjectTagIds(
            $legacyObjectId,
            self::OBJECT_TYPE_LEGACY
        );

        if ($legacyIds === []) {
            return;
        }

        $fileIds = $this->getObjectTagIds(
            $fileObjectId,
            self::OBJECT_TYPE_FILES
        );

        $missingOnFile = array_values(
            array_diff($legacyIds, $fileIds)
        );

        if ($missingOnFile !== []) {
            $this->tagMapper->assignTags(
                $fileObjectId,
                self::OBJECT_TYPE_FILES,
                $missingOnFile
            );
        }

        /*
         * La relación antigua ya no es necesaria después de copiarla.
         */
        $this->tagMapper->unassignTags(
            $legacyObjectId,
            self::OBJECT_TYPE_LEGACY,
            $legacyIds
        );
    }

    /**
     * @return string[]
     */
    private function getObjectTagIds(
        string $objectId,
        string $objectType
    ): array {
        $relations = $this->tagMapper->getTagIdsForObjects(
            $objectId,
            $objectType
        );

        return array_values(array_unique(array_map(
            'strval',
            array_values($relations[$objectId] ?? [])
        )));
    }

    /**
     * @param string[] $tagIds
     *
     * @return array<int,array<string,mixed>>
     */
    private function serializeTagsByIds(array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }

        return $this->serializeTags(
            $this->tagManager->getTagsByIds($tagIds)
        );
    }

    /**
     * @param ISystemTag[] $tags
     *
     * @return array<int,array<string,mixed>>
     */
    private function serializeTags(array $tags): array
    {
        $response = array_map(
            fn (ISystemTag $tag): array =>
                $this->serializeTag($tag),
            array_values($tags)
        );

        usort(
            $response,
            static fn (array $a, array $b): int =>
                strcasecmp($a['name'], $b['name'])
        );

        return $response;
    }

    private function serializeTag(ISystemTag $tag): array
    {
        return [
            'id' => (string) $tag->getId(),
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
            'userVisible' => $tag->isUserVisible(),
            'userAssignable' => $tag->isUserAssignable(),
        ];
    }

    /**
     * @param mixed[] $tagIds
     *
     * @return string[]
     */
    private function normalizeTagIds(array $tagIds): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn ($tagId): string =>
                    trim((string) $tagId),
                $tagIds
            ),
            static fn (string $tagId): bool =>
                $tagId !== ''
                && ctype_digit($tagId)
        )));
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    private function getEntry(string $date): Entry
    {
        if ($this->UserId === null) {
            throw new DoesNotExistException(
                'Usuario no autenticado'
            );
        }

        return $this->entryMapper->find(
            $this->UserId,
            $date
        );
    }

    private function getFileObjectId(Entry $entry): string
    {
        $fileId = $entry->getFileId();

        if ($fileId === null || (int) $fileId <= 0) {
            throw new \UnexpectedValueException(
                'La entrada todavía no tiene un archivo Markdown asociado'
            );
        }

        return (string) $fileId;
    }

    private function getLegacyObjectId(string $date): string
    {
        return (string) $this->UserId.$date;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }
}
