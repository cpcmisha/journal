<?php

declare(strict_types=1);

$serverRoot = getenv('NEXTCLOUD_SERVER_ROOT');

if ($serverRoot === false || trim($serverRoot) === '') {
    $serverRoot = dirname(__DIR__, 3);
}

$bootstrap = rtrim($serverRoot, '/').'/tests/bootstrap.php';

if (!is_file($bootstrap)) {
    throw new RuntimeException(
        'Nextcloud test bootstrap not found at '.$bootstrap.'. '
        .'Set NEXTCLOUD_SERVER_ROOT to a Nextcloud source checkout '
        .'that includes tests/bootstrap.php.'
    );
}

require_once $bootstrap;

\OC_App::loadApp('journalnotes');
