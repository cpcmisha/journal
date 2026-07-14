<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Command;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RenameRootFolderCommand extends Command
{
    private const OLD_FOLDER = 'Misha Journal';
    private const NEW_FOLDER = 'Journal';

    public function __construct(
        private IDBConnection $db,
        private IRootFolder $rootFolder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('journalnotes:rename-root-folder')
            ->setDescription(
                'Renombra la carpeta "Misha Journal" a "Journal"'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Muestra los cambios sin modificar archivos ni base de datos'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $dryRun = (bool) $input->getOption('dry-run');

        $qb = $this->db->getQueryBuilder();

        $qb->selectDistinct('uid')
            ->from('journalnotes')
            ->where(
                $qb->expr()->like(
                    'file_path',
                    $qb->createNamedParameter(
                        self::OLD_FOLDER.'/%'
                    )
                )
            );

        $result = $qb->executeQuery();
        $uids = [];

        while ($row = $result->fetchAssociative()) {
            $uid = trim((string) ($row['uid'] ?? ''));

            if ($uid !== '') {
                $uids[] = $uid;
            }
        }

        $result->closeCursor();

        if ($uids === []) {
            $output->writeln(
                '<info>No existen rutas pendientes de migración.</info>'
            );

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>Usuarios encontrados: %d</info>',
            count($uids)
        ));

        $errors = 0;
        $migrated = 0;

        foreach ($uids as $uid) {
            try {
                $userFolder = $this->rootFolder->getUserFolder($uid);

                $sourceExists = $userFolder->nodeExists(
                    self::OLD_FOLDER
                );

                $targetExists = $userFolder->nodeExists(
                    self::NEW_FOLDER
                );

                $output->writeln(sprintf(
                    '%s: %s → %s',
                    $uid,
                    self::OLD_FOLDER,
                    self::NEW_FOLDER
                ));

                if ($sourceExists && $targetExists) {
                    throw new \RuntimeException(
                        'Existen simultáneamente las carpetas "'
                        .self::OLD_FOLDER
                        .'" y "'
                        .self::NEW_FOLDER
                        .'". No se realizará una fusión automática.'
                    );
                }

                if (!$sourceExists && !$targetExists) {
                    throw new NotFoundException(
                        'No se encontró ninguna de las dos carpetas'
                    );
                }

                if ($dryRun) {
                    $output->writeln(sprintf(
                        '  [simulación] Actualizar rutas de %s',
                        $uid
                    ));

                    continue;
                }

                if ($sourceExists) {
                    $source = $userFolder->get(
                        self::OLD_FOLDER
                    );

                    if (!$source instanceof Folder) {
                        throw new \RuntimeException(
                            'La ruta de origen no es una carpeta'
                        );
                    }

                    $targetPath = rtrim(
                        $userFolder->getPath(),
                        '/'
                    ).'/'.self::NEW_FOLDER;

                    $source->move($targetPath);

                    $output->writeln(
                        '  <info>Carpeta movida correctamente.</info>'
                    );
                } else {
                    $output->writeln(
                        '  <comment>La carpeta ya se llama Journal.</comment>'
                    );
                }

                $update = $this->db->getQueryBuilder();

                $update->update('journalnotes')
                    ->set(
                        'file_path',
                        $update->createFunction(
                            'CONCAT('
                            .$update->createNamedParameter(
                                self::NEW_FOLDER
                            )
                            .', SUBSTRING(file_path, '
                            .(strlen(self::OLD_FOLDER) + 1)
                            .'))'
                        )
                    )
                    ->where(
                        $update->expr()->eq(
                            'uid',
                            $update->createNamedParameter($uid)
                        )
                    )
                    ->andWhere(
                        $update->expr()->like(
                            'file_path',
                            $update->createNamedParameter(
                                self::OLD_FOLDER.'/%'
                            )
                        )
                    );

                $affected = $update->executeStatement();

                $output->writeln(sprintf(
                    '  <info>Rutas actualizadas: %d</info>',
                    $affected
                ));

                $migrated++;
            } catch (\Throwable $e) {
                $errors++;

                $output->writeln(sprintf(
                    '<error>[error] %s — %s</error>',
                    $uid,
                    $e->getMessage()
                ));
            }
        }

        if ($dryRun) {
            $output->writeln(
                '<comment>Simulación terminada. No se modificó nada.</comment>'
            );
        }

        $output->writeln('');
        $output->writeln('<info>Resumen</info>');
        $output->writeln('  Usuarios migrados: '.$migrated);
        $output->writeln('  Errores: '.$errors);

        return $errors === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
