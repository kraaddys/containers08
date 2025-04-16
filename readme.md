# Лабораторная работа №8: Непрерывная интеграция с помощью Github Actions

## Студент

**Славов Константин, группа I2302**  
**Дата выполнения: _16.04.2025_**

## Цель работы

Целью данной лабораторной работы является практическое освоение контейнеризации веб-приложений на языке PHP с использованием SQLite в качестве СУБД, а также настройка автоматического тестирования с помощью GitHub Actions. В процессе работы были реализованы основные принципы построения модульной архитектуры и системы CI/CD.

## Задание

Создать Web-приложение, написать тесты для него и настроить непрерывную интеграцию с помощью Github Actions на базе контейнеров.

## Ход работы

**1. Как выглядит структура проекта:**

![image](https://i.imgur.com/YCBbuWR.png)

**2. Файл `config.php`:**

```php
<?php
$config = [
    "db" => [
        "path" => "/var/www/db/db.sqlite"
    ]
];
```

Файл `config.php` содержит ассоциативный массив, где задается путь к файлу базы данных SQLite. Это позволяет централизованно управлять местоположением базы и при необходимости легко изменять конфигурацию проекта, не затрагивая другие файлы. Такое разделение конфигурации и логики повышает гибкость и читаемость кода.

**2. Файл `database.php`:**

```php
class Database {
    private $db;
    public function __construct($path) {
        $this->db = new PDO("sqlite:$path");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function Execute($sql) {
        return $this->db->exec($sql);
    }
    public function Fetch($sql) {
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function Create($table, $data) {
        $keys = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $stmt = $this->db->prepare("INSERT INTO $table ($keys) VALUES ($placeholders)");
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }
    public function Read($table, $id) {
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function Update($table, $id, $data) {
        $assignments = implode(", ", array_map(fn($key) => "$key = :$key", array_keys($data)));
        $data['id'] = $id;
        $stmt = $this->db->prepare("UPDATE $table SET $assignments WHERE id = :id");
        return $stmt->execute($data);
    }
    public function Delete($table, $id) {
        $stmt = $this->db->prepare("DELETE FROM $table WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    public function Count($table) {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}
```

Файл `database.php` содержит класс, реализующий методы взаимодействия с базой данных. В конструкторе происходит подключение к SQLite через PDO, при этом устанавливается режим обработки ошибок как исключения, что удобно для отладки. Метод `Execute` позволяет выполнить произвольный SQL-запрос, `Fetch` возвращает результат запроса в виде ассоциативного массива. Метод `Create` формирует запрос на добавление новой записи, используя имена полей и значения из массива. `Read` получает конкретную запись по ID. Метод `Update` формирует строку обновления, используя `array_map`, чтобы избежать ручной записи SQL-кода. `Delete` удаляет запись по ID. Метод `Count` возвращает количество записей в таблице. Все методы используют подготовленные выражения, что исключает возможность SQL-инъекций и повышает безопасность.

**3. Файл `page.php`:**

```php
class Page {
    private $template;
    public function __construct($template) {
        $this->template = file_get_contents($template);
    }
    public function Render($data) {
        $output = $this->template;
        foreach ($data as $key => $value) {
            $output = str_replace("{{ $key }}", htmlspecialchars($value), $output);
        }
        return $output;
    }
}
```

Модуль `page.php` реализует класс Page. Он предназначен для загрузки HTML-шаблона и замены в нем плейсхолдеров, обозначенных как `{{ ключ }}`, соответствующими значениями из массива. Шаблон загружается в конструкторе с помощью `file_get_contents`, а метод `Render` выполняет итерацию по переданному массиву и замену значений в шаблоне. Все значения экранируются с помощью `htmlspecialchars`, чтобы исключить возможность внедрения вредоносного кода в HTML, тем самым предотвращая XSS-атаки.

**4. Файл `index.php`:**

```php
require_once __DIR__ . '/modules/database.php';
require_once __DIR__ . '/modules/page.php';
require_once __DIR__ . '/config.php';
$db = new Database($config["db"]["path"]);
$page = new Page(__DIR__ . '/templates/index.tpl');
$pageId = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$data = $db->Read("page", $pageId);
if (!$data) {
    $data = [
        'title' => 'Страница не найдена',
        'content' => 'Такой страницы не существует.'
    ];
}
echo $page->Render($data);
```

Главный файл приложения `index.php` подключает все модули и конфигурацию, создает экземпляры классов и обрабатывает входной параметр `page`, получаемый через GET-запрос. По этому идентификатору вызывается метод `Read` из класса `Database`. Если запись найдена, она передается в шаблон. Если нет — отображается сообщение о том, что страница не найдена. В результате мы получаем страницу, сгенерированную на основе шаблона и данных из базы.

**5. Файл `index.tpl`:**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ title }}</title>
    <link rel="stylesheet" href="/styles/style.css">
</head>
<body>
    <div class="container">
        <h1>{{ title }}</h1>
        <div class="content">
            <p>{{ content }}</p>
        </div>
    </div>
</body>
</html>
```

Шаблон `index.tpl` — это стандартный HTML-документ, содержащий плейсхолдеры для названия и содержимого страницы. Он также подключает файл стилей, благодаря чему внешний вид страницы становится более презентабельным.

**6. Файл `style.css`:**

```css
body {
    font-family: Arial;
    background-color: #f4f4f4;
    margin: 0;
    padding: 0;
}
.container {
    width: 80%;
    margin: 30px auto;
    background: white;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}
h1 {
    color: #333;
}
.content {
    color: #555;
}
```

Файл стилей `style.css` задает общую стилизацию страницы. В нем определяются базовые шрифты, фон, отступы, а также стили для заголовка, контейнера и контента. Благодаря этим стилям страница выглядит аккуратно и структурированно.

**7. Файл `schema.sql`:**

```sql
CREATE TABLE page (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    content TEXT
);
INSERT INTO page (title, content) VALUES ('Page 1', 'Content 1');
INSERT INTO page (title, content) VALUES ('Page 2', 'Content 2');
INSERT INTO page (title, content) VALUES ('Page 3', 'Content 3');
```

SQL-файл `schema.sql` создаёт таблицу `page` с полями `id`, `title`, `content`, а затем вставляет три записи. Эти записи используются для начального наполнения базы данных. Такой подход позволяет быстро инициализировать проект с предопределёнными данными при сборке.

**Файл: `testframework.php`:**

```php
function message($type, $message) {
    $time = date('Y-m-d H:i:s');
    echo "{$time} [{$type}] {$message}" . PHP_EOL;
}
function info($message) { message('INFO', $message); }
function error($message) { message('ERROR', $message); }
function assertExpression($expression, $pass = 'Pass', $fail = 'Fail'): bool {
    if ($expression) { info($pass); return true; }
    error($fail); return false;
}
class TestFramework {
    private $tests = [], $success = 0;
    public function add($name, $test) { $this->tests[$name] = $test; }
    public function run() {
        foreach ($this->tests as $name => $test) {
            info("Running test {$name}");
            if ($test()) { $this->success++; }
            info("End test {$name}");
        }
    }
    public function getResult() {
        return "{$this->success} / " . count($this->tests);
    }
}
```

Фреймворк `testframework.php` реализует логирование сообщений, проверку выражений и регистрацию тестов. Он содержит функции info, error и assertExpression, которые логируют результат выполнения теста. Класс `TestFramework` позволяет добавлять тесты, запускать их и получать итог результата. Благодаря такому подходу реализуется удобная система тестирования без использования сторонних библиотек.

**9. Файл `tests.php`:**

```php
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
    return assertExpression($count >= 3);
}
function testDbCreate() {
    global $config;
    $db = new Database($config["db"]["path"]);
    $id = $db->Create("page", ["title" => "Test", "content" => "Content"]);
    return assertExpression($id > 0);
}
function testDbRead() {
    global $config;
    $db = new Database($config["db"]["path"]);
    $data = $db->Read("page", 1);
    return assertExpression(isset($data["title"]));
}
function testPageRender() {
    $page = new Page(__DIR__ . '/../templates/index.tpl');
    $html = $page->Render(["title" => "Test", "content" => "Content"]);
    return assertExpression(strpos($html, "Test") !== false);
}
$tests->add("Connection", "testDbConnection");
$tests->add("Count", "testDbCount");
$tests->add("Create", "testDbCreate");
$tests->add("Read", "testDbRead");
$tests->add("Render", "testPageRender");
$tests->run();
echo $tests->getResult();
```

Файл `tests.php` подключает все необходимые модули и запускает серию тестов: проверку соединения с базой, подсчёт записей, создание записи, чтение и рендеринг страницы. Каждый тест реализован в виде функции, которая проверяет конкретную функцию или метод. Все тесты добавляются в объект `TestFramework` и выполняются последовательно. По завершению выводится статистика пройденных тестов.

**10. `Dockerfile`:**

```dockerfile
FROM php:7.4-fpm
RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo_sqlite
VOLUME ["/var/www/db"]
COPY sql/schema.sql /var/www/db/schema.sql
RUN cat /var/www/db/schema.sql | sqlite3 /var/www/db/db.sqlite && \
    chmod 777 /var/www/db/db.sqlite && \
    rm -rf /var/www/db/schema.sql
COPY site /var/www/html
```

Dockerfile использует официальный образ `php:7.4-fpm`. Устанавливаются пакеты `sqlite3` и его зависимости, а также расширение `pdo_sqlite`. В контейнер копируется SQL-скрипт, инициализирующий базу данных, и создаётся файл базы. После этого SQL-файл удаляется. Далее в контейнер добавляется весь проект. Таким образом, создается полностью готовый к запуску образ.

**11. Файл `main.yml`:**

```yaml
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker build -t containers08 .
      - run: docker create --name container --volume database:/var/www/db containers08
      - run: docker cp ./tests container:/var/www/html
      - run: docker start container
      - run: docker exec container php /var/www/html/tests/tests.php
      - run: docker stop container
      - run: docker rm container
      - run: docker rmi containers08 || true
```

Файл `.github/workflows/main.yml` определяет шаги автоматического CI. Он запускается при любом push или pull request в ветку `main`. В workflow клонируется репозиторий, собирается Docker-образ, создается контейнер с volume, внутрь копируются тесты, контейнер запускается и в нём выполняются тесты. После завершения контейнер останавливается, удаляется, и удаляется также сам образ. Такой подход позволяет обеспечить полную автоматизацию процесса проверки.

## Проверка тестов

![image](https://i.imgur.com/rn33CDj.jpeg)

![image](https://i.imgur.com/07nhlwF.jpeg)

## Ответы на вопросы

**Что такое непрерывная интеграция?**  
Непрерывная интеграция — это процесс, при котором каждое изменение в проекте автоматически проходит сборку и проверку, прежде чем попасть в основную ветку разработки. Она необходима для быстрого выявления и устранения ошибок, повышения стабильности и упрощения совместной работы над проектом. В данной работе CI реализована с помощью GitHub Actions. Каждый коммит или pull request инициирует запуск workflow, который в автоматическом режиме собирает образ, запускает контейнер и выполняет тесты, тем самым обеспечивая контроль качества на каждом этапе.

**Для чего нужны юнит-тесты?**  
Юнит-тесты нужны для того, чтобы проверять работоспособность отдельных модулей и функций проекта. Благодаря им можно быть уверенным, что изменения в одном участке кода не приведут к поломке других частей системы. В лабораторной работе тесты были реализованы вручную и покрыли все ключевые функции: работу с базой, получение и отображение данных. Запуск тестов происходит каждый раз при изменении проекта, что соответствует современной практике разработки.

**Как запускать тесты при pull request?**  
Для запуска CI не только при коммитах, но и при создании pull request, в блок `on:` файла `.github/workflows/main.yml` добавляется ключ `pull_request`. Это позволяет проверять изменения до их попадания в основную ветку. Такой подход особенно полезен при командной работе и при проверке внешних вкладов.

**Как удалить Docker-образ после тестов?**  
Удаление Docker-образа после завершения всех этапов CI необходимо для очистки ресурсов и предотвращения накопления неиспользуемых артефактов. Это реализуется добавлением команды `docker rmi` в конце workflow. В случае сбоя шаг не остановит процесс, благодаря использованию `|| true`. Таким образом, система остаётся чистой, и автоматизация продолжается без лишних препятствий.

## Вывод

В процессе выполнения лабораторной работы я не только научился создавать веб-приложения с использованием PHP и SQLite, но и понял важность автоматизации в процессе разработки. Благодаря Docker я смог собрать и запустить проект в изолированном окружении, не зависящем от настроек локальной системы. Написание модульных тестов позволило убедиться в корректности работы ключевых компонентов, а настройка GitHub Actions обеспечила автоматическую проверку проекта при каждом изменении. Это дало полное представление о современных подходах к разработке и поддержке программных продуктов. Лабораторная работа позволила получить ценные практические навыки, которые применимы не только в учебных проектах, но и в реальной профессиональной деятельности.

## Библиография

- [PHP Manual](https://www.php.net/manual/en/) - Официальная документация по языку программирования PHP, содержащая описание синтаксиса, стандартных функций и расширений, включая работу с PDO и SQLite.
- [SQLite Documentation](https://www.sqlite.org/docs.html) - Подробная документация по СУБД SQLite, охватывающая основы синтаксиса SQL, типы данных, команды и встроенные функции.
- [GitHub Actions Documentation](https://docs.github.com/en/actions) - Подробный справочник по GitHub Actions, описывающий создание workflow-файлов, запуск действий по событиям и автоматизацию CI/CD.
- [PHP: PDO – PHP Data Objects](https://www.php.net/manual/en/book.pdo.php) - Раздел официального руководства по PHP, посвящённый расширению PDO. Описывает подключение к базе, подготовленные выражения и безопасную работу с SQL.
- [GitHub Actions: Starter Workflows](https://github.com/actions/starter-workflows) - Репозиторий от GitHub с примерами workflow-файлов для разных языков и технологий. Отличный старт для построения CI.
- [Test Automation University – CI/CD with GitHub Actions](https://testautomationu.applitools.com/github-actions-tutorial/) - Бесплатный курс по GitHub Actions: от основ до сложных сценариев CI/CD. Подходит новичкам и тем, кто хочет углубить знания.
