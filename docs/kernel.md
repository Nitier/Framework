# Ядро и жизненный цикл

`Framework\Kernel` отвечает за загрузку конфигурации, инициализацию контейнера и обработку HTTP-запросов. Этот документ описывает полный процесс.

## Bootstrap

```php
use Framework\Kernel;

$kernel = new Kernel();
$kernel->loadApplication(__DIR__ . '/../test-app');
$response = $kernel->handle(); // автоматически эмитирует ответ
```

### `loadApplication()`

1. Строит объект `Path` для каталога приложения.
2. Загружает все `*.php` из `config/` и `test-app/config/` (см. `Kernel::loadDefinitions()`), объединяя массивы конфигураций.
3. Создаёт контейнер PHP-DI и регистрирует в нём сам `Kernel`.
4. Создаёт сервис окружения (`Environment`), чтобы загрузить `.env` и настроить режим взаимодействия (`Mode`).

## Метод `handle()`

```php
public function handle(?ServerRequestInterface $request = null, bool $emit = true): ResponseInterface
```

1. **Проверка контейнера.** При первом запуске пересобирает контейнер, если он ещё не инициализирован.
2. **Получение маршрутизатора.** `Router` извлекается из контейнера; без него выбрасывается исключение.
3. **Получение запроса.** Если `$request` не передан, пытаемся взять `ServerRequestInterface` из контейнера, иначе создаём через `ServerRequest::fromGlobals()`.
4. **Сопоставление маршрута.** `Router::match()` возвращает `RouteResult`.
   - **405 Method Not Allowed.** Вызов `resolveErrorResponse()` с ключом `Kernel::ERROR_HANDLER_METHOD_NOT_ALLOWED`.
   - **404 Not Found.** Аналогично, но с ключом `Kernel::ERROR_HANDLER_NOT_FOUND`.
5. **Подготовка запроса.** Атрибуты маршрута (`route`, `routeName`, `routeParameters`) добавляются к запросу. Значения параметров URI также передаются через `$request->withAttribute()`.
6. **Разрешение обработчика.** `HandlerResolver` преобразует строковые, массивные или callable-описания в `RequestHandlerInterface`.
7. **Пайплайн middleware.** Создаётся `MiddlewarePipeline`, объединяющий глобальные и маршрутные middleware, после чего вызывается `handle()`.
8. **Критические ошибки.** Исключение перехватывается; если `Mode::isDebug()` возвращает `true`, исключение пробрасывается. В остальных случаях вызывается обработчик `Kernel::ERROR_HANDLER_EXCEPTION`.
9. **Эмиссия ответа.** `finalize()` по умолчанию эмитирует ответ (`ResponseEmitter`) и возвращает его. Установите `$emit = false`, если хотите только получить `ResponseInterface` (например, в тестах).

## Настройки ошибок

Ошибки обрабатываются через DI. Можно привязать `ResponseInterface` или callable:

- `Kernel::ERROR_HANDLER_NOT_FOUND`
- `Kernel::ERROR_HANDLER_METHOD_NOT_ALLOWED`
- `Kernel::ERROR_HANDLER_EXCEPTION`

Пример:

```php
use Framework\Http\Message\HtmlResponse;
use Framework\Kernel;
use function DI\value;

return [
    Kernel::ERROR_HANDLER_NOT_FOUND => value(function () {
        return new HtmlResponse('<h1>404</h1><p>Страница не найдена</p>', 404);
    }),
];
```

## Работа с окружением и режимами

`Framework\General\Environment` загружает `.env` (по умолчанию в корне приложения) и проксирует данные в контейнер.

`Framework\General\Mode` хранит флаги, в частности `kernel.mode.debug`. В тестах мы устанавливаем его на `false`, чтобы убедиться в корректности обработки исключений.

## Советы по расширению

- **Собственные сервисы.** Добавляйте файлы в `test-app/config/`, возвращающие массив с ключами для контейнера.
- **Собственный `ResponseEmitter`.** Зарегистрируйте альтернативный сервис в контейнере (`ResponseEmitter::class => function () {...}`).
- **Смена способа загрузки маршрутов.** Добавьте новый элемент в `Router::ROUTE_BUILDERS`. Все определения выполняются при создании маршрутизатора.

## Поток выполнения для `/docs`

1. Маршрут `/docs/{slug?}` регистрируется в `test-app/config/http.php`.
2. `DocsController` загружает Markdown-файлы из `docs/`, конвертирует их в HTML и отдаёт через `HtmlResponse`.
3. Это демонстрация того, как можно расширить приложение без изменения ядра.

Дополнительные сведения об обработке запросов см. в документах [`routing.md`](routing.md), [`middleware.md`](middleware.md) и [`responses.md`](responses.md).
