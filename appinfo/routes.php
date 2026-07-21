<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\JournalNotes\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#index', 'url' => '/date/{date}', 'verb' => 'GET', 'postfix' => 'catchAll'],
        ['name' => 'page#get_entry', 'url' => '/entry/{date}', 'verb' => 'GET'],
        ['name' => 'page#get_last_entries', 'url' => '/entries/{amount}', 'verb' => 'GET'],
        ['name' => 'page#update_entry', 'url' => '/entry/{date}', 'verb' => 'PUT'],
        ['name' => 'system_tag#list_tags', 'url' => '/system-tags', 'verb' => 'GET'],
        ['name' => 'system_tag#create_tag', 'url' => '/system-tags', 'verb' => 'POST'],
        ['name' => 'system_tag#get_entry_tags', 'url' => '/entry/{date}/system-tags', 'verb' => 'GET'],
        ['name' => 'system_tag#update_entry_tags', 'url' => '/entry/{date}/system-tags', 'verb' => 'PUT'],
        ['name' => 'search#search', 'url' => '/search', 'verb' => 'GET'],
        ['name' => 'search#backlinks', 'url' => '/backlinks', 'verb' => 'GET'],
        ['name' => 'relations#get_relations', 'url' => '/relations', 'verb' => 'GET'],
        ['name' => 'relations#resolve_note', 'url' => '/resolve-note', 'verb' => 'GET'],
        ['name' => 'export#get_markdown', 'url' => '/export/markdown', 'verb' => 'GET'],
        ['name' => 'export#get_pdf', 'url' => '/export/pdf', 'verb' => 'GET'],
    ]
];
