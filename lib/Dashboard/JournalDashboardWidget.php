<?php

declare(strict_types=1);

namespace OCA\JournalNotes\Dashboard;

use OCA\JournalNotes\AppInfo\Application;
use OCA\JournalNotes\Service\JournalRepository;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

final class JournalDashboardWidget implements
    IAPIWidgetV2,
    IButtonWidget,
    IIconWidget
{
    /**
     * El Dashboard tiene una altura limitada.
     * Reservamos espacio para el aviso de hoy y el botón principal.
     */
    /**
     * Reservamos espacio para el estado de hoy y el botón principal.
     */
    private const DEFAULT_LIMIT = 5;
    private const MAX_LIMIT = 5;

    public function __construct(
        private JournalRepository $journalRepository,
        private IURLGenerator $urlGenerator,
        private IL10N $l10n
    ) {
    }

    public function getId(): string
    {
        return Application::APP_ID;
    }

    public function getTitle(): string
    {
        return $this->l10n->t('Journal');
    }

    public function getOrder(): int
    {
        return 10;
    }

    /**
     * Clase de respaldo para clientes que todavía no utilizan IIconWidget.
     */
    public function getIconClass(): string
    {
        return 'icon-filetype-text';
    }

    public function getIconUrl(): string
    {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath(
                Application::APP_ID,
                'journal-dark.svg'
            )
        );
    }

    public function getUrl(): ?string
    {
        return $this->buildEntryUrl(
            (new \DateTimeImmutable('today'))->format('Y-m-d')
        );
    }

    /**
     * El widget usa exclusivamente la API del Dashboard.
     */
    public function load(): void
    {
    }

    public function getItemsV2(
        string $userId,
        ?string $since = null,
        int $limit = self::DEFAULT_LIMIT
    ): WidgetItems {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        /*
         * Solicitamos una entrada adicional porque la entrada de hoy
         * puede aparecer tanto como destacada como dentro de recientes.
         */
        $recentEntries = $this->journalRepository->getLastEntries(
            $userId,
            $limit + 1
        );

        $items = [];
        $todayEntry = null;

        foreach ($recentEntries as $entry) {
            if (($entry['date'] ?? null) === $today) {
                $todayEntry = $entry;
                break;
            }
        }

        if ($todayEntry !== null) {
            $items[] = $this->createWidgetItem(
                $todayEntry,
                $this->l10n->t('Today')
            );
        }

        foreach ($recentEntries as $entry) {
            $date = (string) ($entry['date'] ?? '');

            if ($date === '' || $date === $today) {
                continue;
            }

            $items[] = $this->createWidgetItem($entry);

            if (count($items) >= $limit) {
                break;
            }
        }

        return new WidgetItems(
            $items,
            $this->l10n->t(
                'You have not written any Journal entries yet.'
            ),
            $todayEntry === null
                ? $this->l10n->t(
                    'You have not written today yet.'
                )
                : ''
        );
    }

    /**
     * @return list<WidgetButton>
     */
    public function getWidgetButtons(string $userId): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            new WidgetButton(
                WidgetButton::TYPE_NEW,
                $this->buildEntryUrl($today),
                $this->l10n->t('Write today')
            ),
            new WidgetButton(
                WidgetButton::TYPE_MORE,
                $this->urlGenerator->linkToRouteAbsolute(
                    'journalnotes.page.index'
                ),
                $this->l10n->t('Open Journal')
            ),
        ];
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function createWidgetItem(
        array $entry,
        ?string $prefix = null
    ): WidgetItem {
        $date = (string) ($entry['date'] ?? '');
        $title = trim((string) ($entry['title'] ?? ''));
        $excerpt = trim((string) ($entry['excerpt'] ?? ''));

        if ($title === '') {
            $title = $date;
        }

        if ($prefix !== null && $prefix !== '') {
            $title = $prefix.' · '.$title;
        }

        return new WidgetItem(
            $title,
            $excerpt,
            $this->buildEntryUrl($date),
            $this->getEmptyIconUrl(),
            (string) ($entry['updated'] ?? $date)
        );
    }

    private function getEmptyIconUrl(): string
    {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath(
                Application::APP_ID,
                'empty.svg'
            )
        );
    }

    private function buildEntryUrl(string $date): string
    {
        $baseUrl = rtrim(
            $this->urlGenerator->linkToRouteAbsolute(
                'journalnotes.page.index'
            ),
            '/'
        );

        return $baseUrl.'/date/'.rawurlencode($date);
    }
}
