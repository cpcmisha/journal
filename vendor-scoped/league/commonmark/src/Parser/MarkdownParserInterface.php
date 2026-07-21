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
namespace OCA\JournalNotes\Vendor\League\CommonMark\Parser;

use OCA\JournalNotes\Vendor\League\CommonMark\Exception\CommonMarkException;
use OCA\JournalNotes\Vendor\League\CommonMark\Node\Block\Document;
interface MarkdownParserInterface
{
    /**
     * @throws CommonMarkException
     */
    public function parse(string $input): Document;
}
