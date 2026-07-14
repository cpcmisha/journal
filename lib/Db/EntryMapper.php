<?php

namespace OCA\JournalNotes\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

class EntryMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'journalnotes', Entry::class);
    }

    /**
     * Find the journal entry for the given user and date.
     *
     * @return mixed|Entity
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     */
    public function find(string $uid, string $date)
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )->andWhere(
                $qb->expr()->eq('entry_date', $qb->createNamedParameter($date))
            );

        return $this->findEntity($qb);
    }

    /**
     * Find all journal entries for the given user ID, ordered by date ascending.
     *
     * @return array|Entity[]
     *
     * @throws Exception
     */
    public function findAll(string $uid): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )
            ->orderBy('entry_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find the latest $amount journal entries ordered by date descending.
     *
     * @return array|Entity[]
     *
     * @throws Exception
     */
    public function findLast(string $uid, int $amount): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )
            ->setMaxResults($amount)
            ->orderBy('entry_date', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Delete all journal entries for the given user.
     *
     * @throws Exception
     * @returns int Number of deleted entries
     */
    public function deleteAllEntriesForUser(string $uid): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            );

        return $qb->executeStatement();
    }
}
