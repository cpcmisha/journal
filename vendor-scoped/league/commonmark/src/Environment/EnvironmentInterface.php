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
namespace OCA\JournalNotes\Vendor\League\CommonMark\Environment;

use OCA\JournalNotes\Vendor\League\CommonMark\Delimiter\Processor\DelimiterProcessorCollection;
use OCA\JournalNotes\Vendor\League\CommonMark\Extension\ExtensionInterface;
use OCA\JournalNotes\Vendor\League\CommonMark\Node\Node;
use OCA\JournalNotes\Vendor\League\CommonMark\Normalizer\TextNormalizerInterface;
use OCA\JournalNotes\Vendor\League\CommonMark\Parser\Block\BlockStartParserInterface;
use OCA\JournalNotes\Vendor\League\CommonMark\Parser\Inline\InlineParserInterface;
use OCA\JournalNotes\Vendor\League\CommonMark\Renderer\NodeRendererInterface;
use OCA\JournalNotes\Vendor\League\Config\ConfigurationProviderInterface;
use OCA\JournalNotes\Vendor\Psr\EventDispatcher\EventDispatcherInterface;
interface EnvironmentInterface extends ConfigurationProviderInterface, EventDispatcherInterface
{
    /**
     * Get all registered extensions
     *
     * @return ExtensionInterface[]
     */
    public function getExtensions(): iterable;
    /**
     * @return iterable<BlockStartParserInterface>
     */
    public function getBlockStartParsers(): iterable;
    /**
     * @return iterable<InlineParserInterface>
     */
    public function getInlineParsers(): iterable;
    public function getDelimiterProcessors(): DelimiterProcessorCollection;
    /**
     * @psalm-param class-string<Node> $nodeClass
     *
     * @return iterable<NodeRendererInterface>
     */
    public function getRenderersForClass(string $nodeClass): iterable;
    public function getSlugNormalizer(): TextNormalizerInterface;
}
