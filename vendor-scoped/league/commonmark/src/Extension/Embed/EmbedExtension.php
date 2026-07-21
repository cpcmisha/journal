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
namespace OCA\JournalNotes\Vendor\League\CommonMark\Extension\Embed;

use OCA\JournalNotes\Vendor\League\CommonMark\Environment\EnvironmentBuilderInterface;
use OCA\JournalNotes\Vendor\League\CommonMark\Event\DocumentParsedEvent;
use OCA\JournalNotes\Vendor\League\CommonMark\Extension\ConfigurableExtensionInterface;
use OCA\JournalNotes\Vendor\League\Config\ConfigurationBuilderInterface;
use OCA\JournalNotes\Vendor\Nette\Schema\Expect;
final class EmbedExtension implements ConfigurableExtensionInterface
{
    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        $builder->addSchema('embed', Expect::structure(['adapter' => Expect::type(EmbedAdapterInterface::class), 'allowed_domains' => Expect::arrayOf('string')->default([]), 'fallback' => Expect::anyOf('link', 'remove')->default('link')]));
    }
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $adapter = $environment->getConfiguration()->get('embed.adapter');
        \assert($adapter instanceof EmbedAdapterInterface);
        $allowedDomains = $environment->getConfiguration()->get('embed.allowed_domains');
        if ($allowedDomains !== []) {
            $adapter = new DomainFilteringAdapter($adapter, $allowedDomains);
        }
        $environment->addBlockStartParser(new EmbedStartParser(), 300)->addEventListener(DocumentParsedEvent::class, new EmbedProcessor($adapter, $environment->getConfiguration()->get('embed.fallback')), 1010)->addRenderer(Embed::class, new EmbedRenderer());
    }
}
