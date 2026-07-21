<?php

namespace OCA\JournalNotes\Vendor\iio\libmergepdf\Driver;

use OCA\JournalNotes\Vendor\iio\libmergepdf\Source\SourceInterface;
interface DriverInterface
{
    /**
     * Merge multiple sources
     */
    public function merge(SourceInterface ...$sources): string;
}
