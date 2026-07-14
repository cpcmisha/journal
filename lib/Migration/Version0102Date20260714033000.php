<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0102Date20260714033000 extends SimpleMigrationStep
{
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('journalnotes')) {
            return null;
        }

        $table = $schema->getTable('journalnotes');

        if (!$table->hasColumn('file_id')) {
            $table->addColumn('file_id', 'bigint', [
                'notnull' => false,
                'default' => null,
                'unsigned' => true,
            ]);
        }

        if (!$table->hasColumn('file_path')) {
            $table->addColumn('file_path', 'string', [
                'notnull' => false,
                'default' => null,
                'length' => 1024,
            ]);
        }

        return $schema;
    }
}
