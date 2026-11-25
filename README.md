# PHP Framework

Легковесная основа MVC на PHP 8.4+, RedBeanPHP и React. Код приложения находится в `app/`, ядро устанавливается через Composer. Composer загружает пространства имён `App\` и `Core\` по PSR-4.

## Composer-пакеты

| Пакет | Назначение |
| --- | --- |
| `vcorexplatform/framework` | Шаблон приложения с React-лендингом и проверкой PHP API |
| `vcorexplatform/core` | MVC, DI, маршрутизация, конфигурация и модели RedBeanPHP |
| `vcorexplatform/session` | `Core\Http\Session`, PHP-сессии и cookie |
| `vcorexplatform/http-client` | `Core\Http\HttpClient`, Guzzle и фильтрация исходящих запросов |

Composer скачивает ядро и модули из Packagist. Сессии и HTTP-клиент включены в шаблон по умолчанию. Регистрация сервисов находится в bootstrap/services.php.

## Запуск

Нужны PHP 8.4+ с расширениями pdo_mysql и session, Composer и Node.js 24.

```sh
composer create-project vcorexplatform/framework new_project
cd new_project
npm ci
npm run build
php bin/framework serve
```

Откройте [стартовую страницу](http://127.0.0.1:18080/). Она показывает статус PHP API и команды быстрого старта. Для работы стартовой страницы база данных не требуется.

Сервер слушает только 127.0.0.1:18080. Для остановки нажмите Ctrl+C.

Для разработки интерфейса запустите npm run dev рядом с PHP-сервером и откройте [Vite](http://127.0.0.1:15173/assets/). Vite проксирует /api на порт 18080.

## Маршруты шаблона

| Метод и путь | Результат |
| --- | --- |
| GET / | React-страница быстрого старта |
| GET /api/health | Ответ 200 с JSON `{"status":"ok"}` |

Для проверки API откройте [GET /api/health](http://127.0.0.1:18080/api/health). Неизвестный путь возвращает 404. Для известного пути с неподдерживаемым методом возвращается 405.

## Расширение ядра

В `routes/api.php` и `routes/web.php` регистрируются обработчики. В параметр `{id}` маршрутизатор передаёт строку. Контейнер внедряет `Request` в методы и зависимости по типу в конструкторы. Интерфейсы связываются с реализациями через фабрики `Container::singleton()`. Класс middleware реализует `Core\Http\Middleware` и может изменить ответ или вернуть его досрочно.

Если приложению нужна БД, скопируйте .env.example в .env, заведите базу MySQL или MariaDB и заполните параметры DB_*. Создайте схему приложения. Модели наследуют Core\Model и задают TABLE и FILLABLE. Контейнер внедряет Database. Соединение открывается при первом обращении к модели.

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

## Валидация

В контроллере используйте `$request->validate($rules)` для JSON и форм и `$request->validateQuery($rules)` для query. Результат содержит только поля из правил.

```php
$data = $request->validate(['email' => 'required|string|max:254|email']);
$query = $request->validateQuery(['page' => 'integer|min:1']);
```

Правила включают `required`, `nullable`, `string`, `integer`, `boolean`, `array`, `email`, `min:N` и `max:N`. Указывайте тип перед ограничением. Ошибки возвращаются со статусом 422 и объектом `errors`. Строки измеряются в символах Unicode.

## CLI и миграции

```sh
php bin/framework make:controller ExampleController
php bin/framework migrate:status
php bin/framework migrate
```

Помещайте по одному SQL-выражению в каждый файл в `database/migrations/`. Пример имени файла — `202611250100_create_products.sql`. Для миграций задайте подключение к MySQL или MariaDB и пользователя с правами DDL. Команда проверяет контрольные суммы и пропускает уже применённые файлы. Если выполнение прервалось, состояние `dirty` блокирует следующий запуск. Сверьте и исправьте фактическую схему перед исправлением записи журнала. [Правила DDL в MySQL](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html) · [Правила DDL в MariaDB](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit)

## Авторизация

Установите пакет в корне проекта:

```sh
composer require vcorexplatform/auth:^1.0
php bin/framework migrate
```

Укажите параметры БД, предоставьте процессу PHP права записи в `storage/cache/` и `storage/sessions/`. Для production нужен HTTPS. При нескольких экземплярах приложения храните сессии и счётчики попыток в общих каталогах с поддержкой блокировок. Идентификатор клиента берётся из `REMOTE_ADDR`. Настройте его на уровне доверенного прокси, если приложение развёрнуто за ним.

| Метод и путь | Вход и результат |
| --- | --- |
| GET /api/auth/csrf | Выдаёт `csrf_token` |
| POST /api/auth/register | `email`, `password`. Возвращает аккаунт и код восстановления |
| POST /api/auth/login | `email`, `password`. Создаёт авторизованную сессию и выдаёт новый `csrf_token` |
| GET /api/auth/me | Возвращает аккаунт или 401 |
| POST /api/auth/logout | Завершает сессию |
| POST /api/auth/forgot-password | `email`. Запрашивает доставку временного токена при наличии транспорта |
| POST /api/auth/reset-password | `email`, `token`, новый `password`. Возвращает новый код восстановления |

Для каждого POST передавайте cookie и заголовок `X-CSRF-Token`. После входа используйте токен из ответа. Пароль при создании и смене должен занимать 12–72 байта. Покажите пользователю код восстановления из поля `recovery_code`, чтобы он мог сохранить его. При смене пароля код заменяется, предыдущие сессии отзываются.

Для доставки токена по почте реализуйте `VCoreX\Auth\ResetDelivery::enqueue(string $email, string $token)` и передайте объект третьим аргументом в фабрике Auth в `bootstrap/services.php`. Используйте доверенный HTTPS-адрес приложения для ссылки. Срок действия токена — 15 минут. При отсутствии транспорта восстановление работает по сохранённому коду. Чтобы защитить собственные маршруты, добавьте `VCoreX\Auth\RequireAuth::class` в middleware группы. [Безопасность сессий PHP](https://www.php.net/manual/en/features.session.security.management.php)

## HTTP-тестирование

```php
$http = new Core\Testing\HttpClient(Core\Application::boot($root));
$http->getJson('/api/health')->assertStatus(200)->assertJson(['status' => 'ok']);
```

Для сессий можно привязать `Core\Http\ArraySession` к `Core\Http\SessionStore::class` в тестовом контейнере. Запустите `composer test` для проверки валидации и HTTP-клиента. После установки Auth и миграций в отдельной тестовой БД запустите `php tests/auth.php`.


## Развёртывание

В `deploy/` лежат примеры конфигурации Nginx и Apache. Подставьте абсолютный путь проекта вместо `/srv/framework` и адрес PHP-FPM или FastCGI вместо `127.0.0.1:9000`. Для Apache включите модули `rewrite`, `proxy`, `proxy_fcgi`, `authz_core` и `mime`. Корень веб-сервера — `public/`. Процесс PHP должен иметь право записи в `storage/logs/`.

Установите `APP_ENV=production` и `APP_DEBUG=false`. В production RedBeanPHP всегда работает с замороженной схемой. Схему обновляйте от отдельной учётной записи с правами DDL. Журнал исключений записывается в `storage/logs/app.log`.

## Проверки

```sh
composer test
npm run build
```

Тест проверяет запуск установленного ядра, маршрут /api/health и разрешение установленных сервисов через контейнер.
