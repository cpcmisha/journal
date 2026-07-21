<?php

declare (strict_types=1);
namespace OCA\JournalNotes\Vendor\iio\libmergepdf\Source;

use OCA\JournalNotes\Vendor\iio\libmergepdf\PagesInterface;
use OCA\JournalNotes\Vendor\iio\libmergepdf\Pages;
use OCA\JournalNotes\Vendor\iio\libmergepdf\Exception;
/**
 * Pdf source from file
 */
final class FileSource implements SourceInterface
{
    /**
     * @var string
     */
    private $filename;
    /**
     * @var PagesInterface
     */
    private $pages;
    public function __construct(string $filename, PagesInterface $pages = null)
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new Exception("Invalid file '{$filename}'");
        }
        $this->filename = $filename;
        $this->pages = $pages ?: new Pages();
    }
    public function getName(): string
    {
        return $this->filename;
    }
    public function getContents(): string
    {
        return (string) file_get_contents($this->filename);
    }
    public function getPages(): PagesInterface
    {
        return $this->pages;
    }
}
