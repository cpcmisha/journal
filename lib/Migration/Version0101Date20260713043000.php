<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0101Date20260713043000 extends SimpleMigrationStep
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

        if (!$table->hasColumn('entry_metadata')) {
            $table->addColumn('entry_metadata', 'text', [
                'notnull' => false,
                'default' => null,
            ]);
        }

        return $schema;
    }
}
