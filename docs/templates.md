# Шаблоны и документация

Примерное приложение использует два вспомогательных сервиса для работы с HTML и Markdown.

## TemplateRenderer

Расположен в `test-app/src/Service/TemplateRenderer.php`.

- Определяет базовый каталог шаблонов (по умолчанию `test-app/template`).
- Метод `render($template, array $parameters = [])` подключает PHP-файл, передавая параметры в локальную область видимости.
- Автоматически выполняет `ob_start()`/`ob_get_clean()` и выбрасывает исключение, если файл не найден.

Пример использования в `InfoController`:

```php
$html = $this->templates->render('about', [
    'title' => 'About the Demo Application',
    'heading' => 'About this Mini Framework',
    'paragraphs' => [...],
]);

return new HtmlResponse($html);
```

## MarkdownRenderer

Находится в `test-app/src/Service/MarkdownRenderer.php`. Конвертирует простой Markdown в HTML, поддерживает:

- заголовки `#`, `##` и т.д.;
- ненумерованные списки `- item`;
- блоки кода ```;
- параграфы.

Это не полнофункциональный парсер, но достаточно, чтобы показать встроенную документацию.

## Сайт документации `/docs`

1. **Контроллер.** `DocsController` собирает Markdown-файлы из `docs/`, читает содержимое и преобразует его в HTML через `MarkdownRenderer`.
2. **Маршруты.** В `test-app/config/http.php` добавляется группа `/docs`:
   - `/docs` — список доступных файлов (`docs/index.php`).
   - `/docs/{slug}` — отдельная статья (`docs/article.php`).
3. **Шаблоны.** Находятся в `test-app/template/docs/`.

Это демонстрирует, как можно расширить приложение без правок ядра и как подключать дополнительные сервисы через DI.

## Добавление собственного шаблонизатора

Если нужно использовать Twig, Blade или другой движок:

1. Установите зависимости (`composer require twig/twig`).
2. Зарегистрируйте сервис в конфигурации, например:

```php
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

return [
    Environment::class => static function () {
        $loader = new FilesystemLoader(__DIR__ . '/../template');
        return new Environment($loader);
    },
];
```

3. Обновите контроллеры так, чтобы они использовали ваш сервис вместо `TemplateRenderer`.

## Хранение ресурсных файлов

- HTML/PHP-шаблоны — в `test-app/template/`.
- Markdown-документация — в `docs/`.
- Статические файлы (css/js) можно положить в `public/` и ссылаться на них напрямую.

Дополнительно см. [`docs/responses.md`](responses.md) и [`docs/routing.md`](routing.md) для понимания, как интегрировать шаблоны с маршрутизацией и ответами.
