<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Command;

use OCA\JournalNotes\Service\JournalFileService;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateFrontMatterCommand extends Command
{
    public function __construct(
        private IDBConnection $db,
        private JournalFileService $journalFileService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('journalnotes:migrate-frontmatter')
            ->setDescription(
                'Añade Front Matter YAML a las entradas de Journal'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Muestra las entradas pendientes sin modificar archivos'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Reescribe también archivos que ya tienen Front Matter'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $qb = $this->db->getQueryBuilder();

        $qb->select(
            'id',
            'uid',
            'entry_date',
            'entry_metadata',
            'file_path'
        )
            ->from('journalnotes')
            ->where(
                $qb->expr()->isNotNull('file_path')
            )
            ->orderBy('entry_date', 'ASC');

        $result = $qb->executeQuery();

        $pending = [];
        $alreadyMigrated = 0;
        $readErrors = 0;

        while ($row = $result->fetchAssociative()) {
            $uid = (string) $row['uid'];
            $date = (string) $row['entry_date'];
            $filePath = (string) $row['file_path'];

            try {
                $document = $this->journalFileService->readDocument(
                    $uid,
                    $filePath
                );

                if ($document['hasFrontMatter'] && !$force) {
                    $alreadyMigrated++;
                    continue;
                }

                $metadata = $this->decodeMetadata(
                    (string) ($row['entry_metadata'] ?? '')
                );

                $categories = $this->extractCategories($metadata);

                $pending[] = [
                    'uid' => $uid,
                    'date' => $date,
                    'filePath' => $filePath,
                    'content' => $document['content'],
                    'categories' => $categories,
                ];
            } catch (\Throwable $e) {
                $readErrors++;

                $output->writeln(sprintf(
                    '<error>[error de lectura] %s — %s</error>',
                    $date,
                    $e->getMessage()
                ));
            }
        }

        $result->closeCursor();

        $output->writeln(sprintf(
            '<info>Pendientes: %d</info>',
            count($pending)
        ));

        $output->writeln(sprintf(
            'Ya tenían Front Matter: %d',
            $alreadyMigrated
        ));

        if ($dryRun) {
            foreach ($pending as $row) {
                $categoryText = $row['categories'] === []
                    ? 'sin categorías'
                    : implode(', ', $row['categories']);

                $output->writeln(sprintf(
                    '  [pendiente] %s — %s',
                    $row['date'],
                    $categoryText
                ));
            }

            $output->writeln(
                '<comment>Simulación terminada. No se modificó nada.</comment>'
            );

            return $readErrors === 0
                ? Command::SUCCESS
                : Command::FAILURE;
        }

        $migrated = 0;
        $errors = $readErrors;

        foreach ($pending as $row) {
            try {
                $this->journalFileService->write(
                    $row['uid'],
                    $row['date'],
                    $row['content'],
                    [
                        'categories' => $row['categories'],
                    ]
                );

                $migrated++;

                $output->writeln(sprintf(
                    '<info>[migrada] %s → %s</info>',
                    $row['date'],
                    $row['filePath']
                ));
            } catch (\Throwable $e) {
                $errors++;

                $output->writeln(sprintf(
                    '<error>[error] %s — %s</error>',
                    $row['date'],
                    $e->getMessage()
                ));
            }
        }

        $output->writeln('');
        $output->writeln('<info>Resumen</info>');
        $output->writeln('  Migradas: '.$migrated);
        $output->writeln('  Ya migradas: '.$alreadyMigrated);
        $output->writeln('  Errores: '.$errors);

        return $errors === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMetadata(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Compatibilidad con:
     *
     * {"categories":["Trabajo","Personal"]}
     * {"category":"Trabajo"}
     *
     * @param array<string,mixed> $metadata
     *
     * @return string[]
     */
    private function extractCategories(array $metadata): array
    {
        $categories = $metadata['categories'] ?? [];

        if (
            $categories === []
            && isset($metadata['category'])
            && is_string($metadata['category'])
        ) {
            $categories = [$metadata['category']];
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
}
