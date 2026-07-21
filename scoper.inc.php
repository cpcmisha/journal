<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

$root = __DIR__;

return [
    'prefix' => 'OCA\\JournalNotes\\Vendor',

    'output-dir' => $root . '/build/scoped',

    'finders' => [
        Finder::create()
            ->files()
            ->in($root . '/vendor')
            ->exclude([
                'bin',
            ]),
    ],

    'patchers' => [
        static function (
            string $filePath,
            string $prefix,
            string $contents
        ): string {
            $normalizedPath = str_replace('\\', '/', $filePath);

            /*
             * Corrige referencias dinámicas del autoloader de Composer.
             */
            if (
                basename($filePath) === 'autoload_real.php'
                && str_contains($normalizedPath, '/composer/')
            ) {
                $scopedClassLoader
                    = $prefix . '\\Composer\\Autoload\\ClassLoader';

                $scopedInitializer
                    = $prefix . '\\ComposerAutoloaderInitJournalNotes';

                $contents = str_replace(
                    [
                        "'Composer\\\\Autoload\\\\ClassLoader'",
                        "'Composer\\Autoload\\ClassLoader'",
                    ],
                    [
                        "'" . addslashes($scopedClassLoader) . "'",
                        "'" . addslashes($scopedClassLoader) . "'",
                    ],
                    $contents,
                );

                $contents = str_replace(
                    [
                        "array('ComposerAutoloaderInitJournalNotes', 'loadClassLoader')",
                        'array("ComposerAutoloaderInitJournalNotes", "loadClassLoader")',
                    ],
                    [
                        "array('" . addslashes($scopedInitializer)
                            . "', 'loadClassLoader')",
                        'array("' . addslashes($scopedInitializer)
                            . '", "loadClassLoader")',
                    ],
                    $contents,
                );
            }

            /*
             * Dompdf construye estas clases dinámicamente como strings.
             */
            if (
                str_ends_with(
                    $normalizedPath,
                    '/dompdf/dompdf/src/Frame/Factory.php'
                )
            ) {
                $contents = str_replace(
                    [
                        '"Dompdf\\\\FrameDecorator\\\\$decorator"',
                        '"Dompdf\\\\FrameReflower\\\\$reflower"',
                        '"Dompdf\\\\Positioner\\\\$positioner"',
                    ],
                    [
                        '"' . addslashes(
                            $prefix . '\\Dompdf\\FrameDecorator\\'
                        ) . '$decorator"',
                        '"' . addslashes(
                            $prefix . '\\Dompdf\\FrameReflower\\'
                        ) . '$reflower"',
                        '"' . addslashes(
                            $prefix . '\\Dompdf\\Positioner\\'
                        ) . '$positioner"',
                    ],
                    $contents,
                );
            }

            return $contents;
        },
    ],

    'expose-global-functions' => false,
    'expose-global-constants' => false,
    'expose-global-classes' => false,
];
