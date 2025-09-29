# Сессии и cookies

Фреймворк предоставляет утилиты для работы с сессиями и куками, чтобы избежать прямой зависимости от суперглобальных переменных.

## SessionManager

Класс `Framework\Http\Session\SessionManager` инкапсулирует стандартные функции `session_*`.

```php
use Framework\Http\Session\SessionManager;

$session = new SessionManager();
$session->start();
$session->set('user_id', 42);

$id = $session->id();
$token = $session->pull('csrf_token');
$session->invalidate();
```

Главные методы:

- `start()` / `commit()` — открыть и завершить сессию.
- `get()`, `set()`, `has()`, `remove()`, `pull()`, `clear()`.
- `regenerate()` и `invalidate()` для смены идентификатора и сброса данных.

### Middleware для автоматического старта

`Framework\Http\Session\StartSessionMiddleware` гарантирует запуск сессии перед обработкой запроса и коммит после него:

```php
use Framework\Http\Session\StartSessionMiddleware;
use Framework\Http\Session\SessionManager;

return [
    Router::GLOBAL_MIDDLEWARE => value([
        new StartSessionMiddleware(new SessionManager()),
    ]),
];
```

Вы можете внедрить `SessionManager` через контейнер и, например, сохранять указатель пользователя или flash-сообщения.

## CookieJar

`Framework\Http\Cookie\CookieJar` упрощает составление заголовков `Set-Cookie`. Куки накапливаются и применяются к ответу через middleware `CookieQueueMiddleware`.

```php
use Framework\Http\Cookie\CookieJar;
use Framework\Http\Cookie\CookieQueueMiddleware;

$jar = new CookieJar();
$jar->queue('token', 'abc123', [
    'httpOnly' => true,
    'sameSite' => 'Lax',
]);

$middleware = new CookieQueueMiddleware($jar);
$response = $middleware->process($request, $handler);
```

Поддерживаются опции `expires`, `maxAge`, `path`, `domain`, `secure`, `httpOnly`, `sameSite`. Для удаления куки вызовите `expire('name')` — будет отправлен заголовок с прошедшей датой и `Max-Age=0`.

Эти инструменты не навязывают стратегию управления состоянием, но позволяют единообразно работать с сессией и куками во всех приложениях, построенных на фреймворке.
