# Маршрутизация

`Framework\Http\Routing\Router` управляет таблицей маршрутов, сопоставлением входящих запросов и запуском middleware. Ниже — полный набор возможностей.

## Основные понятия

- **Маршрут** (`Route`) связывает HTTP-методы, путь, обработчик и список middleware.
- **Match** (`RouteMatch`) содержит найденный маршрут и параметры URI.
- **Result** (`RouteResult`) сообщает о результате сопоставления: успех, 404 или 405.

## Регистрация маршрутов

Маршруты добавляются через контейнер: ключ `Router::ROUTE_BUILDERS` возвращает массив колбэков. Каждый колбэк получает `Router` и (опционально) контейнер, и может добавлять маршруты.

```php
use Framework\Http\Routing\Router;
use function DI\value;

return [
    Router::ROUTE_BUILDERS => value([
        static function (Router $router): void {
            $router->get('/', HomeController::class . '::index');
        },
    ]),
];
```

### Методы-помощники

- `get($path, $handler, $middleware = [])`
- `post(...)`
- `put(...)`
- `patch(...)`
- `delete(...)`
- `options(...)`
- `any($path, ...)` — регистрирует маршрут для `GET/POST/PUT/PATCH/DELETE/OPTIONS`.

`$handler` может быть callable, строкой `Class::method`, строкой `Class@method` или массивом `[ClassName::class, 'method']`. Разрешением занимается `HandlerResolver`.

### Параметры маршрута

```
/router/{slug}
/router/{id:\d+}/edit
```

- `{slug}` — параметр без ограничений (до следующего `/`).
- `{id:\d+}` — параметр с регулярным выражением (только цифры).
- Значения параметров добавляются как атрибуты запроса (`$request->getAttribute('id')`).

### Группы

```php
$router->group(
    ['prefix' => 'admin', 'middleware' => AdminAuthMiddleware::class],
    static function (Router $router): void {
        $router->get('/dashboard', [DashboardController::class, 'index']);
    }
);
```

- `prefix` добавляется к пути (`/admin/dashboard`).
- `middleware` — строка, callable или массив `[Class::class, 'method']`. Можно передать массив middleware — все они будут добавлены к маршрутам внутри группы.

## Маршруты через атрибуты

Фреймворк предоставляет атрибут `Framework\Http\Routing\Attribute\Route`. Это пример из `InfoController`:

```php
use Framework\Http\Routing\Attribute\Route;

class InfoController
{
    #[Route(methods: ['GET'], path: '/about', name: 'about')]
    public function about(ServerRequestInterface $request): ResponseInterface
    {
        // ...
    }
}
```

Чтобы активировать атрибуты, укажите контроллеры в конфигурации, используя ключ `Router::ATTRIBUTE_CONTROLLERS`:

```php
use Framework\Http\Routing\Router;
use function DI\value;

return [
    Router::ATTRIBUTE_CONTROLLERS => value([
        InfoController::class,
    ]),
];
```

## Middleware уровни

- **Глобальные:** `Router::GLOBAL_MIDDLEWARE`. Выполняются для всех маршрутов.
- **Групповые:** передаются при регистрации группы.
- **Маршрутные:** передаются третьим аргументом в `add()`/`get()`/...

Middleware могут быть:

- экземплярами `Psr\Http\Server\MiddlewareInterface`,
- колбэками с сигнатурой `(ServerRequestInterface $request, RequestHandlerInterface $next)`,
- строками `Class@method` или `Class::method`,
- массивом `[ClassName::class, 'method']`.

Подробнее см. `docs/middleware.md`.

## Результаты сопоставления

`Router::match()` возвращает `RouteResult`:

- `isSuccess()` — маршрут найден.
- `isMethodNotAllowed()` — путь найден, но HTTP-метод не разрешён.
- `getAllowedMethods()` — список допустимых методов (если 405).
- `getMatch()` → `RouteMatch` (для успешного сопоставления).

При успехе ядро добавляет атрибуты:

- `route` — экземпляр `Route`.
- `routeName` — имя (если задано).
- `routeParameters` — ассоциативный массив параметров URI.

## Поток для `/docs`

1. В `test-app/config/http.php` создаётся группа `/docs`.
2. `/docs/` — список Markdown-файлов из `docs/` сгенерированный `DocsController`.
3. `/docs/{slug}` — конвертирует Markdown в HTML и отдаёт через `HtmlResponse`.

Этот механизм демонстрирует возможность подключать собственные контроллеры и шаблонизаторы без изменения ядра.

## Рекомендации

- Собирайте маршруты по областям в отдельных функциях, затем подключайте их в `Router::ROUTE_BUILDERS`.
- Используйте имена маршрутов (`Route::name()`), чтобы ссылаться на них в шаблонах и редиректах.
- Старайтесь, чтобы маршруты были иммутабельны — не изменяйте `Route` после регистрации.

Дополнительные темы: middleware (`middleware.md`), структура ответов (`responses.md`), шаблонизация (`templates.md`).
