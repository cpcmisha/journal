<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Controller;

use OCA\JournalNotes\Service\JournalRelationsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

final class RelationsController extends Controller
{
    public function __construct(
        string $AppName,
        IRequest $request,
        private JournalRelationsService $journalRelationsService,
        private ?string $UserId
    ) {
        parent::__construct($AppName, $request);
    }

    /**
     * Devuelve los enlaces salientes y entrantes de una entrada.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getRelations(
        string $date = '',
        int $limit = 100
    ): DataResponse {
        if ($this->UserId === null) {
            return new DataResponse(
                ['error' => 'User not authenticated'],
                Http::STATUS_UNAUTHORIZED
            );
        }

        $date = trim($date);

        if (!$this->isValidDate($date)) {
            return new DataResponse(
                ['error' => 'Invalid entry date'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            return new DataResponse(
                $this->journalRelationsService->getRelations(
                    $this->UserId,
                    $date,
                    $limit
                )
            );
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new DataResponse(
                [
                    'date' => $date,
                    'title' => '',
                    'outgoing' => [],
                    'incoming' => [],
                ],
                Http::STATUS_OK
            );
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }
}
