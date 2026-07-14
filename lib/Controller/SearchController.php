<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Controller;

use OCA\JournalNotes\Service\JournalSearchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

final class SearchController extends Controller
{
    public function __construct(
        string $AppName,
        IRequest $request,
        private JournalSearchService $journalSearchService,
        private ?string $UserId
    ) {
        parent::__construct($AppName, $request);
    }

    /**
     * Busca por contenido, fecha, categorías, etiquetas y wikilinks.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function search(
        string $q = '',
        int $limit = 50
    ): DataResponse {
        if ($this->UserId === null) {
            return new DataResponse(
                ['error' => 'User not authenticated'],
                Http::STATUS_UNAUTHORIZED
            );
        }

        $q = trim($q);

        if ($q === '') {
            return new DataResponse([]);
        }

        try {
            return new DataResponse(
                $this->journalSearchService->search(
                    $this->UserId,
                    $q,
                    $limit
                )
            );
        } catch (\Throwable $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
