<?php

require_once __DIR__ . '/modules/database.php';
require_once __DIR__ . '/modules/page.php';
require_once __DIR__ . '/config.php';

$db = new Database($config["db"]["path"]);
$page = new Page(__DIR__ . '/templates/index.tpl');

// ⚠ Плохая практика, но оставим для простоты примера
$pageId = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$data = $db->Read("page", $pageId);

// Если страница не найдена — заглушка
if (!$data) {
    $data = [
        'title' => 'Страница не найдена',
        'content' => 'Упс! Такой страницы не существует.'
    ];
}

echo $page->Render($data);
