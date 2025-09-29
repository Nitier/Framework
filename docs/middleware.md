# Middleware

Middleware реализуют паттерн «цепочка ответственности» и соответствуют спецификации PSR-15. Ниже — руководство по настройке и написанию middleware.

## MiddlewarePipeline

`Framework\Http\Middleware\MiddlewarePipeline` собирает массив middleware и финальный обработчик:

- принимает контейнер PHP-DI для разрешения строковых определений;
- внедряет глобальные middleware (`Router::GLOBAL_MIDDLEWARE`) и маршрутные;
- каждый middleware должен возвращать `ResponseInterface`, вызывая `$next->handle()` для продолжения цепочки.

## Определение middleware

### Глобальные middleware

```php
use Framework\Http\Routing\Router;
use function DI\value;

return [
    Router::GLOBAL_MIDDLEWARE => value([
        RequestIdMiddleware::class,
    ]),
];
```

Все запросы пройдут через `RequestIdMiddleware`, который добавляет `requestId` в атрибуты запроса и заголовок ответа.

### Маршрутные middleware

```php
$router->get('/hello/{name}', [HomeController::class, 'greet'], [RouteAttributeMiddleware::class]);
```

Middleware выполнится только для указанного маршрута.

### Групповые middleware

```php
$router->group([
    'prefix' => 'admin',
    'middleware' => [EnsureAuthenticated::class, LogUserAction::class],
], static function (Router $router): void {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});
```

Все маршруты `/admin/*` получают middleware авторизации и логирования.

## Форматы описания middleware

Фреймворк поддерживает несколько форматов:

- **Экземпляр** `MiddlewareInterface` — напрямую используется в цепочке.
- **Callable** `(ServerRequestInterface $request, RequestHandlerInterface $next)`: вам возвращается ответ, вы можете модифицировать запрос/ответ.
- **Строка `Class@method` или `Class::method`** — через контейнер создаётся объект и вызывается метод.
- **Массив `[ClassName::class, 'method']`** — аналогично строковому формату.

`MiddlewarePipeline::resolveMiddleware()` приводит все форматы к `MiddlewareInterface` при помощи обёртки `CallableMiddleware`.

## Пример middleware

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TimingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $started = microtime(true);
        $response = $handler->handle($request);
        $elapsed = number_format((microtime(true) - $started) * 1000, 2);

        return $response->withHeader('X-Response-Time', $elapsed . 'ms');
    }
}
```

Добавьте его в глобальный список или к конкретному маршруту.

## Тестирование middleware

`MiddlewarePipelineTest` демонстрирует проверку порядка выполнения middleware и модификацию атрибутов запроса/ответа. Используйте `$pipeline->handle(new ServerRequest(...))` с отключением эмиссии (`Kernel::handle($request, false)`) для интеграционных тестов.

## Практические советы

- Возвращайте новый `ResponseInterface`. Желаемый способ — использовать `JsonResponse`, `HtmlResponse` или `ResponseFactory::from()`.
- Middleware должен быть чистым по отношению к контейнеру: зависимости внедряйте через конструктор.
- Если middleware зависит от сервисов (например, логгера), зарегистрируйте его в контейнере, затем передайте класс в конфигурации.

Дополнительная информация: [маршрутизация](routing.md), [ответы](responses.md), [ядро](kernel.md).
