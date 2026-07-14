<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use ReturnTypeWillChange;

class Entry extends Entity implements JsonSerializable
{
    protected $entryDate;
    protected $uid;
    protected $entryContent;
    protected $entryMetadata;
    protected $fileId;
    protected $filePath;

    public function __construct()
    {
        $this->addType('id', 'string');
        $this->addType('uid', 'string');
        $this->addType('entryDate', 'string');
        $this->addType('entryContent', 'string');
        $this->addType('entryMetadata', 'string');
        $this->addType('fileId', 'integer');
        $this->addType('filePath', 'string');
    }

    /**
     * Devuelve la metadata como arreglo, aunque la columna esté vacía
     * o contenga un JSON inválido.
     */
    public function getMetadataArray(): array
    {
        if ($this->entryMetadata === null || $this->entryMetadata === '') {
            return [];
        }

        $metadata = json_decode($this->entryMetadata, true);

        return is_array($metadata) ? $metadata : [];
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'entryDate' => $this->entryDate,
            'entryContent' => $this->entryContent,
            'metadata' => (object) $this->getMetadataArray(),
            'fileId' => $this->fileId,
            'filePath' => $this->filePath,
        ];
    }
}
