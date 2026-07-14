<?php

namespace OCA\JournalNotes\Controller;

use OCA\JournalNotes\Db\Entry;
use OCA\JournalNotes\Db\EntryMapper;
use OCA\JournalNotes\Service\JournalFileService;
use OCA\JournalNotes\Service\JournalRepository;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\Util;
use Psr\Log\LoggerInterface;

class PageController extends Controller
{
    private $userId;
    /**
     * @var EntryMapper
     */
    private $mapper;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var IEventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var JournalFileService
     */
    private $journalFileService;

    /**
     * @var JournalRepository
     */
    private $journalRepository;

    public function __construct(
        $AppName,
        IRequest $request,
        $UserId,
        EntryMapper $mapper,
        LoggerInterface $logger,
        IEventDispatcher $eventDispatcher,
        JournalFileService $journalFileService,
        JournalRepository $journalRepository
    ) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->mapper = $mapper;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->journalFileService = $journalFileService;
        $this->journalRepository = $journalRepository;
    }

    /**
     * CAUTION: the @Stuff turns off security checks; for this page no admin is
     *          required and no CSRF check. If you don't know what CSRF is, read
     *          it up in the docs or you might create a security hole. This is
     *          basically the only required method to add this exemption, don't
     *          add it to any other method if you don't exactly know what it does.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse
    {
        if (class_exists(\OCA\Text\Event\LoadEditor::class)) {
            $this->eventDispatcher->dispatchTyped(
                new \OCA\Text\Event\LoadEditor()
            );
        }

        Util::addScript($this->appName, 'journalnotes-main');

        return new TemplateResponse('journalnotes', 'index');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getEntry(string $date): DataResponse
    {
        try {
            $entry = $this->journalRepository->getEntry(
                $this->userId,
                $date
            );

            return new DataResponse($entry);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['isEmpty' => true]);
        } catch (MultipleObjectsReturnedException|Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @param int $amount Number of past entries to fetch
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getLastEntries(int $amount): DataResponse
    {
        try {
            $entries = $this->journalRepository->getLastEntries(
                $this->userId,
                $amount
            );

            return new DataResponse($entries);
        } catch (DoesNotExistException $e) {
            return new DataResponse([]);
        } catch (MultipleObjectsReturnedException|Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update or create a daily journal entry.
     *
     * @param string $date
     * @param string $content
     * @param string|null $metadataJson
     * @NoAdminRequired
     */
    public function updateEntry(
        string $date,
        string $content,
        ?string $metadataJson = null
    ): DataResponse {
        $content = strip_tags($content);

        if (trim($content) === '') {
            try {
                $entry = $this->mapper->find($this->userId, $date);

                $this->journalFileService->delete(
                    $this->userId,
                    $entry->getFilePath()
                );

                $this->mapper->delete($entry);
            } catch (DoesNotExistException $e) {
                // The entry does not exist.
            } catch (\Exception $e) {
                $this->logger->notice(
                    'Could not delete journal entry: '.$e->getMessage()
                );
            }

            return new DataResponse(['isEmpty' => true]);
        }

        try {
            $isNewEntry = false;

            try {
                $entry = $this->mapper->find(
                    $this->userId,
                    $date
                );
            } catch (DoesNotExistException $e) {
                $isNewEntry = true;

                $entry = new Entry();
                $entry->setId($this->userId.$date);
                $entry->setUid($this->userId);
                $entry->setEntryDate($date);
                $entry->setEntryMetadata('{}');
            }

            /*
             * entry_metadata permanece como respaldo durante la transición.
             * Las categorías del YAML son la fuente principal.
             */
            $metadata = $entry->getMetadataArray();

            if ($metadataJson !== null) {
                $incomingMetadata = json_decode(
                    $metadataJson,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (!is_array($incomingMetadata)) {
                    return new DataResponse(
                        ['error' => 'Metadata must be a JSON object'],
                        Http::STATUS_BAD_REQUEST
                    );
                }

                $metadata = array_replace(
                    $metadata,
                    $incomingMetadata
                );
            }

            $fileData = $this->journalFileService->write(
                $this->userId,
                $date,
                $content,
                $metadata
            );

            $entry->setEntryContent($content);
            $entry->setFileId($fileData['fileId']);
            $entry->setFilePath($fileData['filePath']);
            $entry->setEntryMetadata(
                json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                )
            );

            $savedEntry = $isNewEntry
                ? $this->mapper->insert($entry)
                : $this->mapper->update($entry);

            return new DataResponse($savedEntry);
        } catch (\JsonException $e) {
            return new DataResponse(
                ['error' => 'Invalid metadata JSON: '.$e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        } catch (MultipleObjectsReturnedException|Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

}
