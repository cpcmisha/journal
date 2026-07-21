<?php

declare (strict_types=1);
/*
 * This file is part of the league/commonmark package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace OCA\JournalNotes\Vendor\League\CommonMark;

use OCA\JournalNotes\Vendor\League\CommonMark\Exception\CommonMarkException;
use OCA\JournalNotes\Vendor\League\CommonMark\Output\RenderedContentInterface;
use OCA\JournalNotes\Vendor\League\Config\Exception\ConfigurationExceptionInterface;
/**
 * Interface for a service which converts content from one format (like Markdown) to another (like HTML).
 */
interface ConverterInterface
{
    /**
     * @throws CommonMarkException
     * @throws ConfigurationExceptionInterface
     */
    public function convert(string $input): RenderedContentInterface;
}
