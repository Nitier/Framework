# HTTP-запросы

`Framework\Http\Message\ServerRequest` реализует `Psr\Http\Message\ServerRequestInterface`. Этот документ описывает структуру и методы.

## Создание запросов

### Из глобальных переменных

```php
use Framework\Http\Message\ServerRequest;

$request = ServerRequest::fromGlobals();
```

Метод читает `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` и `$_FILES`, безопасно преобразуя их в PSR-структуру. При недоступности значений используются дефолты (`GET`, `/`, пустые массивы).

### Ручное создание

```php
$request = new ServerRequest(
    method: 'POST',
    uri: 'https://example.com/api',
    headers: ['Content-Type' => ['application/json']],
    body: '{"foo":"bar"}',
    version: '1.1',
    serverParams: ['REQUEST_METHOD' => 'POST']
);
```

## Атрибуты

Атрибуты используются для передачи данных вдоль пайплайна:

```php
$request = $request->withAttribute('userId', 42);
$userId = $request->getAttribute('userId');
```

Маршрутизатор добавляет:

- `route` — экземпляр `Route`.
- `routeName` — имя маршрута (если задано).
- `routeParameters` — ассоциативный массив параметров пути.
- каждый параметр пути (например, `{slug}`) как отдельный атрибут.

## Параметры запроса

- **Метод:** `$request->getMethod()`; изменить можно через `withMethod()`.
- **Запрос (target):** `$request->getRequestTarget()` возвращает `path?query`.
- **URI:** `$request->getUri()` (см. `Framework\Http\Message\Uri`).
- **Query-параметры:** `$request->getQueryParams()` / `withQueryParams()`.
- **Cookies:** `$request->getCookieParams()` / `withCookieParams()`.
- **Заголовки:** `getHeaders()`, `getHeader()`, `withHeader()`.

## Тело запроса

`getBody()` возвращает `StreamInterface`:

```php
$body = $request->getBody();
$body->rewind();
$content = $body->getContents();
```

Для собственных запросов можно передать строку, ресурс или поток (`Stream` создаст буфер автоматически).

## Файлы

`getUploadedFiles()` возвращает массив `UploadedFileInterface`. Фреймворк преобразует `$_FILES` в эти объекты с помощью `UploadedFile::createFromSpec()`.

```php
foreach ($request->getUploadedFiles() as $field => $file) {
    $file->moveTo('/tmp/' . $file->getClientFilename());
}
```

## Работа с JSON

Если `Content-Type` содержит `application/json`, `fromGlobals()` автоматически попытается декодировать тело в массив. Результат доступен через `getParsedBody()`.

```php
$data = $request->getParsedBody();
if (is_array($data)) {
    // работаем с ассоциативным массивом
}
```

## Расширение ServerRequest

- Переопределите `ServerRequest::fromGlobals()` в приложении, если нужна специфичная логика обработки входящих данных.
- Для тестов используйте конструктор напрямую или библиотеку `nyholm/psr7` при необходимости.

См. также: [ответы](responses.md), [маршрутизация](routing.md), [ядро](kernel.md).
