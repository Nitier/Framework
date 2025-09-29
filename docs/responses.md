# Ответы HTTP

Фреймворк предоставляет несколько способов создавать `ResponseInterface`:

## Готовые классы

- **`Framework\Http\Message\JsonResponse`** — сериализует данные в JSON и устанавливает заголовок `Content-Type: application/json; charset=utf-8`.
- **`Framework\Http\Message\HtmlResponse`** — принимает HTML-строку, задаёт `Content-Type: text/html; charset=utf-8`.
- **`Framework\Http\Message\EmptyResponse`** — отвечает статусом без тела (по умолчанию 204). Можно передать другой код, например `new EmptyResponse(304)`.
- **`Framework\Http\Message\RedirectResponse`** — устанавливает заголовок `Location` и проверяет, что статус находится в диапазоне 3xx.
- **`Framework\Http\Message\TextResponse`** — отдаёт текст с заголовком `Content-Type: text/plain; charset=utf-8`.

### Пример

```php
use Framework\Http\Message\JsonResponse;

return new JsonResponse([
    'message' => 'ok',
    'timestamp' => time(),
]);
```

```php
use Framework\Http\Message\HtmlResponse;

$html = $templates->render('about', [...]);
return new HtmlResponse($html);
```

## `ResponseFactory`

`ResponseFactory::from($content, $status = 200)` — удобная обёртка:

- `ResponseInterface` → возвращает как есть.
- `null` → `EmptyResponse` (204 по умолчанию).
- `array|object` → `JsonResponse`.
- `Stringable|string|number` → базовый `Response` с текстовым телом.

## Базовый класс `Response`

Наследует `Message` и реализует `ResponseInterface`. Предоставляет статические методы:

- `Response::json()` — аналог `JsonResponse`.
- `Response::empty()` — аналог `EmptyResponse`.

Используйте специализированные классы, если нужен контроль над типом ответа; в остальных случаях достаточно `ResponseFactory`. Все ответы совместимы с PSR-7.

## Заголовки и статус

Любой `ResponseInterface` иммутабелен. Чтобы добавить заголовок или изменить статус, используйте методы:

```php
$response = (new HtmlResponse('<p>ok</p>'))
    ->withStatus(201)
    ->withHeader('X-Custom', 'value');
```

## Ответы в middleware

Middleware может завершать обработку, возвращая готовый `ResponseInterface`. Рекомендуется использовать `ResponseFactory::from()` или специализированные ответы, чтобы не забывать про заголовки и кодировку.

## Эмиссия ответа

`Kernel::handle()` по умолчанию вызовет `ResponseEmitter`, который пройдёт по заголовкам и телу. Если нужно самостоятельно управлять выводом (например, в тестах), передайте `false` вторым аргументом и обработайте ответ вручную.

См. также: [ядро](kernel.md), [middleware](middleware.md).
