<?php

namespace OCA\JournalNotes\Tests\Unit\Controller;

use OCA\JournalNotes\Controller\PageController;
use OCA\JournalNotes\Db\Entry;
use OCA\JournalNotes\Db\EntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use OCA\JournalNotes\Service\JournalFileService;
use OCA\JournalNotes\Service\JournalRepository;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;

class PageControllerTest extends TestCase
{
    /** @var PageController */
    private $controller;
    private $userId = 'john';
    /** @var EntryMapper|MockObject */
    private $mapper;

    /** @var JournalFileService|MockObject */
    private $journalFileService;

    public function setUp(): void
    {
        $request = $this->getMockBuilder('OCP\IRequest')->getMock();
        $this->mapper = $this->getMockBuilder(EntryMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dispatcher = $this->createMock(IEventDispatcher::class);

        $this->journalFileService = $this->createMock(
            JournalFileService::class
        );

        $tagManager = $this->createMock(
            ISystemTagManager::class
        );

        $tagMapper = $this->createMock(
            ISystemTagObjectMapper::class
        );

        $journalRepository = new JournalRepository(
            $this->mapper,
            $this->journalFileService,
            $tagManager,
            $tagMapper,
            $logger
        );

        $this->controller = new PageController(
            'journalnotes',
            $request,
            $this->userId,
            $this->mapper,
            $logger,
            $dispatcher,
            $this->journalFileService,
            $journalRepository
        );
    }

    public function testIndex()
    {
        $result = $this->controller->index();

        $this->assertEquals('index', $result->getTemplateName());
        $this->assertTrue($result instanceof TemplateResponse);
    }

    public function testGetEntry()
    {
        $entryDate = '2022-08-07';
        $entryContent = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam';
        $entry = $this->createMockEntry($entryDate, $this->userId, $entryContent);
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->equalTo($this->userId),
                $this->equalTo($entryDate))
            ->will($this->returnValue($entry));
        $result = $this->controller->getEntry($entryDate);

        $this->assertEquals(
            Http::STATUS_OK,
            $result->getStatus()
        );

        $data = $result->getData();

        $this->assertIsArray($data);
        $this->assertSame($this->userId, $data['uid']);
        $this->assertSame($entryDate, $data['entryDate']);
        $this->assertSame(
            $entryContent,
            $data['entryContent']
        );
        $this->assertSame([], $data['categories']);
        $this->assertSame([], $data['tags']);
    }

    public function testNotFound()
    {
        $entryDate = '2022-08-07';
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->equalTo($this->userId),
                $this->equalTo($entryDate))
            ->will($this->throwException(new DoesNotExistException('Id not found')));
        $result = $this->controller->getEntry($entryDate);
        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertEquals(['isEmpty' => true], $result->getData());
    }

    public function testMultipleFound()
    {
        $entryDate = '2022-08-07';
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->equalTo($this->userId),
                $this->equalTo($entryDate))
            ->will($this->throwException(new MultipleObjectsReturnedException('Id not found')));
        $result = $this->controller->getEntry($entryDate);
        $this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $result->getStatus());
        $this->assertEquals(['error' => 'Id not found'], $result->getData());
    }

    public function testUpdateEntry()
    {
        $entryDate = '2022-08-07';
        $entryContent = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam';
        $entry = $this->createMockEntry(
            $entryDate,
            $this->userId,
            $entryContent
        );

        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->userId, $entryDate)
            ->willReturn($entry);

        $this->journalFileService->expects($this->once())
            ->method('write')
            ->with(
                $this->userId,
                $entryDate,
                $entryContent,
                []
            )
            ->willReturn([
                'fileId' => 123,
                'filePath' => 'Journal/2022/08/2022-08-07.md',
            ]);

        $this->mapper->expects($this->once())
            ->method('update')
            ->with($this->isInstanceOf(Entry::class))
            ->willReturn($entry);

        $result = $this->controller->updateEntry(
            $entryDate,
            $entryContent
        );

        $this->assertEquals(
            Http::STATUS_OK,
            $result->getStatus()
        );
        $this->assertSame($entry, $result->getData());
        $this->assertSame(123, $entry->getFileId());
        $this->assertSame(
            'Journal/2022/08/2022-08-07.md',
            $entry->getFilePath()
        );
    }

    public function testUpdateEntryFailure()
    {
        $entryDate = '2022-08-07';
        $entryContent = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam';
        $entry = $this->createMockEntry(
            $entryDate,
            $this->userId,
            $entryContent
        );

        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->userId, $entryDate)
            ->willReturn($entry);

        $this->journalFileService->expects($this->once())
            ->method('write')
            ->with(
                $this->userId,
                $entryDate,
                $entryContent,
                []
            )
            ->willReturn([
                'fileId' => 123,
                'filePath' => 'Journal/2022/08/2022-08-07.md',
            ]);

        $this->mapper->expects($this->once())
            ->method('update')
            ->with($this->isInstanceOf(Entry::class))
            ->willThrowException(
                new \OCP\DB\Exception(
                    'Some error while updating'
                )
            );

        $result = $this->controller->updateEntry(
            $entryDate,
            $entryContent
        );

        $this->assertEquals(
            Http::STATUS_INTERNAL_SERVER_ERROR,
            $result->getStatus()
        );
        $this->assertEquals(
            ['error' => 'Some error while updating'],
            $result->getData()
        );
    }

    public function testUpdateEntryDeleteEmpty()
    {
        $entryDate = '2022-08-07';
        $entryContent = '';
        $entry = $this->createMockEntry($entryDate, $this->userId, $entryContent);
        $this->mapper->expects($this->once())
            ->method('find')
            ->with($this->userId, $entryDate)
            ->will($this->returnValue($entry));
        $this->mapper->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($entry))
            ->will($this->returnValue($entry));
        $result = $this->controller->updateEntry($entryDate, $entryContent);
        $this->assertEquals(Http::STATUS_OK, $result->getStatus());
        $this->assertEquals(['isEmpty' => true], $result->getData());
    }

    /**
     * Create an Entry element.
     */
    private function createMockEntry(string $date, string $userId, string $content): Entry
    {
        $entry = new Entry();
        $entry->setId($userId . $date);
        $entry->setUid($userId);
        $entry->setEntryDate($date);
        $entry->setEntryContent($content);
        $entry->setEntryMetadata('{}');

        return $entry;
    }
}
