<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Service;

use InvalidArgumentException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use RuntimeException;

class JournalFileService
{
    private const ROOT_FOLDER = 'Journal';

    public function __construct(
        private IRootFolder $rootFolder,
        private FrontMatterService $frontMatterService
    ) {
    }

    /**
     * Crea o actualiza el archivo Markdown.
     *
     * Cuando $metadata es null, conserva los metadatos existentes.
     *
     * @param array<string,mixed>|null $metadata
     *
     * @return array{fileId:int,filePath:string}
     */
    public function write(
        string $uid,
        string $date,
        string $content,
        ?array $metadata = null
    ): array {
        $this->assertValidDate($date);

        $userFolder = $this->rootFolder->getUserFolder($uid);
        $targetFolder = $this->getDateFolder($userFolder, $date);
        $filename = $date.'.md';

        try {
            try {
                $node = $targetFolder->get($filename);

                if (!$node instanceof File) {
                    throw new RuntimeException(
                        'La ruta del diario existe, pero no es un archivo'
                    );
                }

                $existingDocument = $this->frontMatterService->decode(
                    $node->getContent()
                );

                $frontMatter = $this->frontMatterService->prepareMetadata(
                    $date,
                    $existingDocument['metadata'],
                    $metadata ?? []
                );

                $node->putContent(
                    $this->frontMatterService->encode(
                        $content,
                        $frontMatter
                    )
                );

                $file = $node;
            } catch (NotFoundException $e) {
                $frontMatter = $this->frontMatterService->prepareMetadata(
                    $date,
                    [],
                    $metadata ?? []
                );

                $file = $targetFolder->newFile(
                    $filename,
                    $this->frontMatterService->encode(
                        $content,
                        $frontMatter
                    )
                );
            }
        } catch (NotPermittedException $e) {
            throw new RuntimeException(
                'No se pudo escribir el archivo Markdown',
                0,
                $e
            );
        }

        return [
            'fileId' => (int) $file->getId(),
            'filePath' => $this->getRelativePath($date),
        ];
    }

    /**
     * Mantiene compatibilidad con el código que solo necesita el cuerpo.
     */
    public function read(string $uid, string $filePath): string
    {
        return $this->readDocument($uid, $filePath)['content'];
    }

    /**
     * @return array{
     *     content:string,
     *     metadata:array<string,mixed>,
     *     hasFrontMatter:bool
     * }
     */
    public function readDocument(
        string $uid,
        string $filePath
    ): array {
        $userFolder = $this->rootFolder->getUserFolder($uid);

        try {
            $node = $userFolder->get($filePath);
        } catch (NotFoundException $e) {
            throw new RuntimeException(
                'No se encontró el archivo Markdown',
                0,
                $e
            );
        }

        if (!$node instanceof File) {
            throw new RuntimeException(
                'La ruta almacenada no corresponde a un archivo'
            );
        }

        return $this->frontMatterService->decode(
            $node->getContent()
        );
    }

    public function delete(string $uid, ?string $filePath): void
    {
        if ($filePath === null || trim($filePath) === '') {
            return;
        }

        $userFolder = $this->rootFolder->getUserFolder($uid);

        try {
            $node = $userFolder->get($filePath);
            $node->delete();
        } catch (NotFoundException $e) {
            // La eliminación es idempotente.
        } catch (NotPermittedException $e) {
            throw new RuntimeException(
                'No se pudo eliminar el archivo Markdown',
                0,
                $e
            );
        }
    }

    public function getRelativePath(string $date): string
    {
        $this->assertValidDate($date);

        [$year, $month] = explode('-', $date);

        return self::ROOT_FOLDER
            .'/'.$year
            .'/'.$month
            .'/'.$date.'.md';
    }

    private function getDateFolder(
        Folder $userFolder,
        string $date
    ): Folder {
        [$year, $month] = explode('-', $date);

        $root = $this->getOrCreateFolder(
            $userFolder,
            self::ROOT_FOLDER
        );

        $yearFolder = $this->getOrCreateFolder(
            $root,
            $year
        );

        return $this->getOrCreateFolder(
            $yearFolder,
            $month
        );
    }

    private function getOrCreateFolder(
        Folder $parent,
        string $name
    ): Folder {
        try {
            $node = $parent->get($name);

            if (!$node instanceof Folder) {
                throw new RuntimeException(
                    sprintf(
                        'La ruta "%s" existe, pero no es una carpeta',
                        $name
                    )
                );
            }

            return $node;
        } catch (NotFoundException $e) {
            try {
                return $parent->newFolder($name);
            } catch (NotPermittedException $exception) {
                throw new RuntimeException(
                    sprintf(
                        'No se pudo crear la carpeta "%s"',
                        $name
                    ),
                    0,
                    $exception
                );
            }
        }
    }

    private function assertValidDate(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        if (
            $parsed === false
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException(
                'La fecha debe tener el formato YYYY-MM-DD'
            );
        }
    }
}
