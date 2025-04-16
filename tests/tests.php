<?php

require_once __DIR__ . '/testframework.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/database.php';
require_once __DIR__ . '/../modules/page.php';

$tests = new TestFramework();

function testDbConnection() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        return assertExpression(true, "Connection successful");
    } catch (Exception $e) {
        return assertExpression(false, "", "Connection failed: " . $e->getMessage());
    }
}

function testDbCount() {
    global $config;
    $db = new Database($config["db"]["path"]);
    $count = $db->Count("page");
    return assertExpression($count >= 3, "Table has records", "Count failed");
}

function testDbCreate() {
    global $config;
    $db = new Database($config["db"]["path"]);
    $id = $db->Create("page", [
        "title" => "Test Title",
        "content" => "Test Content"
    ]);
    return assertExpression($id > 0, "Create successful", "Create failed");
}

function testDbRead() {
    global $config;
    $db = new Database($config["db"]["path"]);
    $data = $db->Read("page", 1);
    return assertExpression(isset($data["title"]), "Read successful", "Read failed");
}

function testPageRender() {
    $templatePath = __DIR__ . '/../templates/index.tpl';
    $page = new Page($templatePath);
    $html = $page->Render([
        "title" => "Test Title",
        "content" => "Test Content"
    ]);
    return assertExpression(strpos($html, "Test Title") !== false, "Render successful", "Render failed");
}

// Добавляем тесты
$tests->add("Database connection", "testDbConnection");
$tests->add("Count pages", "testDbCount");
$tests->add("Create page", "testDbCreate");
$tests->add("Read page", "testDbRead");
$tests->add("Page render", "testPageRender");

// Запускаем тесты
$tests->run();

echo $tests->getResult() . PHP_EOL;
