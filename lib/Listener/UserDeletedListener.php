<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Listener;

use OCA\JournalNotes\Db\EntryMapper;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

class UserDeletedListener implements IEventListener
{
    /** @var LoggerInterface */
    private $logger;
    /** @var EntryMapper */
    private $mapper;

    public function __construct(EntryMapper $mapper, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->mapper = $mapper;
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof UserDeletedEvent)) {
            return;
        }

        try {
            $deletedEntries = $this->mapper->deleteAllEntriesForUser($event->getUser()->getUID());
            $this->logger->info("All $deletedEntries journal entries deleted for user ".$event->getUser()->getUID());
        } catch (Exception $e) {
            $this->logger->error('Could not delete journal entries for user '.$event->getUser()->getUID());
        }
    }
}
