<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Command;

use OCA\JournalNotes\Service\JournalFileService;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateMarkdownCommand extends Command
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
            ->setName('journalnotes:migrate-markdown')
            ->setDescription(
                'Migra las entradas de Journal a archivos Markdown'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Muestra las entradas pendientes sin crear archivos'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Reescribe también entradas que ya tienen archivo asociado'
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
            'entry_content',
            'file_id',
            'file_path'
        )
            ->from('journalnotes')
            ->orderBy('entry_date', 'ASC');

        if (!$force) {
            $qb->where(
                $qb->expr()->isNull('file_id')
            );
        }

        $result = $qb->executeQuery();

        $rows = [];

        while ($row = $result->fetchAssociative()) {
            $rows[] = $row;
        }

        $result->closeCursor();

        if ($rows === []) {
            $output->writeln(
                '<info>No existen entradas pendientes de migración.</info>'
            );

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>Entradas encontradas: %d</info>',
            count($rows)
        ));

        if ($dryRun) {
            foreach ($rows as $row) {
                $output->writeln(sprintf(
                    '  [pendiente] %s — %s — %d caracteres',
                    $row['uid'],
                    $row['entry_date'],
                    mb_strlen(
                        (string) ($row['entry_content'] ?? ''),
                        'UTF-8'
                    )
                ));
            }

            $output->writeln(
                '<comment>Simulación terminada. No se modificó nada.</comment>'
            );

            return Command::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $row) {
            $uid = (string) $row['uid'];
            $date = (string) $row['entry_date'];
            $content = (string) ($row['entry_content'] ?? '');

            if (trim($content) === '') {
                $skipped++;

                $output->writeln(sprintf(
                    '<comment>[omitida] %s — entrada vacía</comment>',
                    $date
                ));

                continue;
            }

            try {
                $fileData = $this->journalFileService->write(
                    $uid,
                    $date,
                    $content
                );

                $update = $this->db->getQueryBuilder();

                $update->update('journalnotes')
                    ->set(
                        'file_id',
                        $update->createNamedParameter(
                            $fileData['fileId'],
                            \OCP\DB\Types::BIGINT
                        )
                    )
                    ->set(
                        'file_path',
                        $update->createNamedParameter(
                            $fileData['filePath']
                        )
                    )
                    ->where(
                        $update->expr()->eq(
                            'id',
                            $update->createNamedParameter(
                                (string) $row['id']
                            )
                        )
                    );

                $update->executeStatement();

                $migrated++;

                $output->writeln(sprintf(
                    '<info>[migrada] %s → %s</info>',
                    $date,
                    $fileData['filePath']
                ));
            } catch (\Throwable $e) {
                $errors++;

                $output->writeln(sprintf(
                    '<error>[error] %s — %s</error>',
                    $date,
                    $e->getMessage()
                ));
            }
        }

        $output->writeln('');
        $output->writeln('<info>Resumen</info>');
        $output->writeln('  Migradas: '.$migrated);
        $output->writeln('  Omitidas: '.$skipped);
        $output->writeln('  Errores:  '.$errors);

        return $errors === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
