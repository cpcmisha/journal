<?php

declare(strict_types=1);

namespace OCA\JournalNotes\AppInfo;

use OCA\JournalNotes\Dashboard\JournalDashboardWidget;
use OCA\JournalNotes\Listener\UserDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'journalnotes';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        include_once __DIR__.'/../../vendor-scoped/autoload.php';

        $context->registerEventListener(
            UserDeletedEvent::class,
            UserDeletedListener::class
        );

        $context->registerDashboardWidget(
            JournalDashboardWidget::class
        );
    }

    public function boot(IBootContext $context): void
    {
    }
}
