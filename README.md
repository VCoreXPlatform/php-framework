# PHP Framework

Легковесная основа MVC на PHP 8.4+, RedBeanPHP и React. Код приложения находится в `app/`, ядро — в `packages/core/src/`. Composer загружает пространства имён `App\` и `Core\` по PSR-4.

## Composer-пакеты

| Пакет | Назначение |
| --- | --- |
| `vcorexplatform/framework` | Шаблон приложения с PHP, React и демо API |
| `vcorexplatform/core` | MVC, DI, маршрутизация, конфигурация и модели RedBeanPHP |
| `vcorexplatform/session` | `Core\Http\Session`, PHP-сессии и cookie |
| `vcorexplatform/http-client` | `Core\Http\HttpClient`, Guzzle и фильтрация исходящих запросов |

В распакованном шаблоне достаточно выполнить `composer install`. Composer устанавливает библиотеки из `packages/*` в `vendor/starter/`. В шаблоне сессии и HTTP-клиент включены по умолчанию. Если компонент не нужен, его можно удалить через `composer remove vcorexplatform/session` и вернуть через `composer require vcorexplatform/session:^1.0`. То же действует для `vcorexplatform/http-client`. Регистрация сервисов находится в `bootstrap/services.php`.

Чтобы из локального шаблона создать соседнюю папку `new_project`, выполните в PowerShell из каталога шаблона:

```powershell
$repo = @{
    type = 'path'
    url = (Get-Location).Path.Replace('\', '/')
    options = @{ symlink = $false; versions = @{ 'vcorexplatform/framework' = '1.0.0' } }
} | ConvertTo-Json -Depth 5 -Compress
composer create-project "--repository=$repo" vcorexplatform/framework ../new_project 1.0.0
```

После регистрации имени и публикации шаблона в Packagist создание проекта сводится к `composer create-project vcorexplatform/framework new_project`. Для отдельной публикации библиотек используется содержимое соответствующих каталогов `packages/` и версия из Git-тега. После публикации библиотек можно убрать локальный `path`-репозиторий в шаблоне и заново сформировать его `composer.lock` для установки из реестра. Для установки опубликованных библиотек в существующее приложение используется `composer require vcorexplatform/session vcorexplatform/http-client`. [Команды Composer](https://getcomposer.org/doc/03-cli.md#create-project)


## Запуск

Нужны PHP 8.4+ с `pdo_mysql`, Composer, Node.js 24 и MySQL либо MariaDB. Выполните из корня проекта.

```sh
composer install
npm ci
npm run build
```

Скопируйте `.env.example` в `.env` (PowerShell: `Copy-Item .env.example .env`). Создайте пустую базу и пользователя с доступом к ней. Укажите параметры подключения в `.env` и импортируйте `database/schema.sql` через клиент MySQL или MariaDB.

```sh
php -S 127.0.0.1:18080 -t public deploy/dev-router.php
```

Откройте `http://127.0.0.1:18080/notes`. Страница показывает состояние PHP API и позволяет просматривать, создавать, изменять и удалять заметки. Для разработки интерфейса запустите `npm run dev` рядом с PHP-сервером и откройте `http://127.0.0.1:15173/assets/`. Vite проксирует `/api` на порт 18080.

## API

| Метод и путь | Результат |
| --- | --- |
| `GET /api/health` | Проверка PHP без подключения к БД |
| `GET /api/notes?page=1&per_page=20` | Список и параметры пагинации |
| `POST /api/notes` | Создание, статус 201 и заголовок Location |
| `GET /api/notes/{id}` | Чтение |
| `PUT /api/notes/{id}` | Замена полей |
| `PATCH /api/notes/{id}` | Изменение переданных полей |
| `DELETE /api/notes/{id}` | Удаление, статус 204 |

На запись можно передавать JSON-объект с полями `title` и `body`. Ошибки возвращаются в формате `{"error":"..."}`. Неизвестный путь API возвращает 404, неподдерживаемый метод известного пути — 405 с заголовком Allow. PHP отдаёт React-страницу по `/` и `/notes`.

## Расширение ядра

В `routes/api.php` и `routes/web.php` регистрируются обработчики. В параметр `{id}` маршрутизатор передаёт строку. Контейнер внедряет `Request` в методы и зависимости по типу в конструкторы. Интерфейсы связываются с реализациями через фабрики `Container::singleton()`. Класс middleware реализует `Core\Http\Middleware` и может изменить ответ или вернуть его досрочно.

Модели наследуют `Core\Model` и задают `TABLE` и `FILLABLE`. Контейнер внедряет в них `Database`. Пример использования внедрённых `Note $notes` и `Database $db`:

```php
$note = $notes->create(['title' => 'Черновик', 'body' => 'Текст']);
$notes->update((int) $note['id'], ['body' => 'Новая версия']);
$page = $notes->where('title', 'Черновик')->orderBy('id', 'desc')->paginate(1, 10);

$db->transaction(function () use ($notes, $note): void {
    $notes->delete((int) $note['id']);
});
```

Присваиваются только поля из `FILLABLE`, ID защищён. Фильтры используют параметры SQL. Транзакции работают с замороженной схемой и таблицами InnoDB. При исключении выполняется rollback. Вложенные транзакции отклоняются.

### Защита запросов

Используйте модели и Query для доступа к данным. Контроллер передаёт значения как аргументы, а движок связывает их с параметрами SQL. Движок проверяет идентификаторы таблиц и полей, список полей модели, направление сортировки и границы пагинации. PDO и RedBeanPHP остаются закрытыми внутри слоя БД. Выключены эмуляция подготовленных выражений, выполнение нескольких SQL-выражений за вызов и LOCAL INFILE.

Для production выдавайте пользователю приложения только SELECT, INSERT, UPDATE и DELETE на нужные таблицы.

## Исходящие HTTP-запросы

Контейнер создаёт клиент при первом обращении к нему. В контроллере или сервисе объявите зависимость:

```php
use Core\Http\HttpClient;

public function __construct(private readonly HttpClient $http)
{
}
```

В коде выбирайте доверенный URL каждого сервиса через forBaseUri():

```php
$catalog = $this->http->forBaseUri('https://catalog.example.com/v1/');
$orders = $this->http->forBaseUri('https://orders.example.com/api/');

$items = $catalog->get('/items', ['page' => 2])->json();
$created = $orders->post('/orders', ['item_id' => 42]);
$deleted = $orders->delete('/orders/17');
```

get, delete, head и options принимают путь и массив query-параметров. post, put и patch принимают путь, массив для JSON и необязательный третий аргумент с query-параметрами. Клиент сохраняет префикс выбранного сервиса, проверяет сегменты пути, кодирует параметры и ограничивает размеры query и JSON. Абсолютные URL во входном пути и автоматические редиректы запрещены. [Параметры запросов Guzzle](https://docs.guzzlephp.org/en/stable/request-options.html)

Передавайте в forBaseUri() адреса из кода приложения. Для входных данных пользователя используйте аргументы пути, query и JSON.

Методы возвращают PSR-7 ResponseInterface, в том числе для статусов 3xx, 4xx и 5xx. Сетевые ошибки и истечение таймаута приходят как исключения Guzzle. Метод json() декодирует JSON-объекты в ассоциативные массивы, возвращает null для пустого тела и выбрасывает JsonException при некорректном JSON.

## Сессии

Внедрите Core\Http\Session в контроллер:

```php
use Core\Http\Response;
use Core\Http\Session;

public function __construct(private readonly Session $session)
{
}

public function rememberTheme(): Response
{
    $this->session->set('theme', 'dark');
    return $this->json(['theme' => $this->session->get('theme', 'light')]);
}
```

set(), get(), has() и remove() сами запускают сессию при первом обращении. Для явного запуска вызовите start() до отправки заголовков. close() сохраняет данные и освобождает блокировку файла до конца запроса. destroy() удаляет сессию и отправляет браузеру просроченную cookie.

В браузере хранится только идентификатор FRAMEWORKSID. Cookie выставляется с HttpOnly и SameSite=Lax, а в production и при прямом HTTPS также с Secure. Production требует HTTPS для работы cookie. Данные сохраняются в storage/sessions/, поэтому процесс PHP должен иметь право записи в этот каталог. Для работы через обычный HTTP локально используйте APP_ENV=development. [Параметры безопасности сессий PHP](https://www.php.net/manual/en/session.security.ini.php)

## Развёртывание

В `deploy/` лежат примеры конфигурации Nginx и Apache. Подставьте абсолютный путь проекта вместо `/srv/framework` и адрес PHP-FPM или FastCGI вместо `127.0.0.1:9000`. Для Apache включите модули `rewrite`, `proxy`, `proxy_fcgi`, `authz_core` и `mime`. Корень веб-сервера — `public/`. Процесс PHP должен иметь право записи в `storage/logs/`.

Установите `APP_ENV=production` и `APP_DEBUG=false`. В production RedBeanPHP всегда работает с замороженной схемой. Схему обновляйте от отдельной учётной записи с правами DDL. Журнал исключений записывается в `storage/logs/app.log`. Демонстрационный API заметок доступен без авторизации, поэтому для общедоступного приложения удалите демомаршруты или ограничьте к ним доступ.

## Проверки

```sh
composer test
npm run build
```

Тест проверяет запуск установленного ядра, маршрут /api/health и разрешение установленных сервисов через контейнер.
