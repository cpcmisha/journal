<?php

namespace OCA\JournalNotes\Vendor\iio\libmergepdf;

interface PagesInterface
{
    /**
     * @return int[]
     */
    public function getPageNumbers(): array;
}
