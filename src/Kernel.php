<?php

declare(strict_types=1);

namespace Framework;

use DI\Container;
use DI\ContainerBuilder;
use Framework\Debug\HtmlDebugger;
use Framework\General\Environment;
use Framework\General\Mode;
use Framework\General\Path;
use Framework\Http\Message\HtmlResponse;
use Framework\Http\Message\ServerRequest;
use Framework\Http\Middleware\MiddlewarePipeline;
use Framework\Http\ResponseFactory;
use Framework\Http\ResponseEmitter;
use Framework\Http\Routing\HandlerResolver;
use Framework\Http\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class Kernel
{
    /** @var Container The dependency injection container instance */
    private Container $container;
    /** @var array<mixed> */
    private array $settings = [];

    public const ERROR_HANDLER_NOT_FOUND = 'kernel.http.error.not_found';
    public const ERROR_HANDLER_METHOD_NOT_ALLOWED = 'kernel.http.error.method_not_allowed';
    public const ERROR_HANDLER_EXCEPTION = 'kernel.http.error.exception';
    /**
     * Kernel constructor.
     * Initializes the kernel by setting up paths and building the container.
     */
    public function __construct()
    {
        $kernelPath = new Path(dirname(__DIR__));
        $this->loadDefinitions($kernelPath->get('config'));
    }
    public function loadApplication(string $rootPath): self
    {
        $path = new Path($rootPath);
        $this->loadDefinitions($path->get('config'));
        $this->settings[Path::class] = $path;
        $this->buildContainer($this->settings);
        $this->get(Environment::class);
        return $this;
    }

    /**
     * Loads and processes configuration files from the config directory.
     * @param string $path path to definitions directory
     * @return void
     */
    private function loadDefinitions(string $path): void
    {
        // Load the configuration directory path
        if (!is_dir($path)) {
            return;
        }
        // Load all .php files in the config directory
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $this->settings = array_replace_recursive(
                $this->settings,
                (array) require $file->getRealPath()
            );
        }
    }

    /**
     * Builds the dependency injection container with the provided definitions.
     * Registers the Kernel instance as a singleton in the container.
     * @param array<mixed> $definitions Container service definitions
     * @return void
     */
    protected function buildContainer(array $definitions = []): void
    {
        // Register the Kernel as a singleton
        $definitions[Kernel::class] = $this;
        // Build the container
        $this->container = (new ContainerBuilder())
            ->addDefinitions($definitions)
            ->build();
    }

    /**
     * Returns an entry of the container by its name.
     *
     * @param string $key Identifier
     * @param mixed $default Default value to return if the key does not exist
     * @return mixed The instance or value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check if the key exists in the container
        if (!$this->container->has($key)) {
            return $default;
        }
        // Get the value directly from the container
        return $this->container->get($key);
    }

    /**
     * Sets a value or service in the container.
     *
     * @param string $key Identifier
     * @param mixed $value Instance or value
     * @throws \InvalidArgumentException If the key is invalid
     */
    public function set(string $key, mixed $value): void
    {
        // Set the value in the container
        $this->container->set($key, $value);
    }


    /**
     * Returns true if the container can return an entry for the given identifier. Returns false otherwise.
     *
     * @param string $key Service identifier to check
     * @return bool True if the service exists, false otherwise
     */
    public function has(string $key): bool
    {
        // Check service existence for simple key
        return $this->container->has($key);
    }

    public function handle(?ServerRequestInterface $request = null, bool $emit = true): ResponseInterface
    {
        $requestStart = microtime(true);

        if (!isset($this->container)) {
            $this->buildContainer($this->settings);
        }

        /** @var Router|null $router */
        $router = $this->get(Router::class);
        if (!$router instanceof Router) {
            throw new \RuntimeException('Router service is not configured.');
        }

        if ($request === null) {
            $resolved = $this->get(ServerRequestInterface::class);
            if ($resolved instanceof ServerRequestInterface) {
                $request = $resolved;
            }
        }

        if (!$request instanceof ServerRequestInterface) {
            $request = ServerRequest::fromGlobals();
        }

        $result = $router->match($request);
        if (!$result->isSuccess()) {
            if ($result->isMethodNotAllowed()) {
                $response = $this->resolveErrorResponse(
                    self::ERROR_HANDLER_METHOD_NOT_ALLOWED,
                    [$request, $result->getAllowedMethods()],
                    static function (ServerRequestInterface $req, array $allowedMethods): ResponseInterface {
                        $response = ResponseFactory::from('Method Not Allowed', 405);
                        if ($allowedMethods !== []) {
                            $response = $response->withHeader('Allow', implode(', ', $allowedMethods));
                        }
                        return $response;
                    }
                );

                $response = $this->decorateNotFoundResponse($request, $response, $requestStart);

                return $this->finalize($response, $emit);
            }

            $response = $this->resolveErrorResponse(
                self::ERROR_HANDLER_NOT_FOUND,
                [$request],
                static fn(ServerRequestInterface $req): ResponseInterface => ResponseFactory::from('Not Found', 404)
            );

            $response = $this->decorateNotFoundResponse($request, $response, $requestStart);

            return $this->finalize($response, $emit);
        }

        $match = $result->getMatch();
        if ($match === null) {
            $response = $this->resolveErrorResponse(
                self::ERROR_HANDLER_NOT_FOUND,
                [$request],
                static fn(ServerRequestInterface $req): ResponseInterface => ResponseFactory::from('Not Found', 404)
            );

            $response = $this->decorateNotFoundResponse($request, $response, $requestStart);

            return $this->finalize($response, $emit);
        }

        $route = $match->getRoute();
        $parameters = $match->getParameters();
        foreach ($parameters as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $request = $request
            ->withAttribute('route', $route)
            ->withAttribute('routeParameters', $parameters)
            ->withAttribute('routeName', $route->getName());

        /** @var HandlerResolver $handlerResolver */
        $handlerResolver = $this->get(HandlerResolver::class);
        $handler = $handlerResolver->resolve($route->getHandler());

        $middlewarePipeline = new MiddlewarePipeline(
            $this->container,
            array_merge($router->getGlobalMiddleware(), $route->getMiddleware()),
            $handler
        );

        /** @var Mode|null $mode */
        $mode = $this->get(Mode::class);
        $caughtException = null;

        try {
            $response = $middlewarePipeline->handle($request);
        } catch (Throwable $exception) {
            $caughtException = $exception;
            if ($mode instanceof Mode && $mode->isDebug()) {
                if (!$this->expectsHtml($request)) {
                    throw $exception;
                }

                $response = $this->resolveErrorResponse(
                    self::ERROR_HANDLER_EXCEPTION,
                    [$request, $exception],
                    fn(ServerRequestInterface $req, Throwable $e): ResponseInterface => new HtmlResponse(
                        $this->renderExceptionDocument($e),
                        500
                    )
                );

                if (!$this->isHtmlResponse($response)) {
                    $response = new HtmlResponse(
                        $this->renderExceptionDocument($exception),
                        $response->getStatusCode()
                    );
                }
            } else {
                $response = $this->resolveErrorResponse(
                    self::ERROR_HANDLER_EXCEPTION,
                    [$request, $exception],
                    static function (ServerRequestInterface $req, Throwable $e): ResponseInterface {
                        return ResponseFactory::from('Internal Server Error', 500);
                    }
                );
            }
        }

        $durationMs = (microtime(true) - $requestStart) * 1000;

        if ($mode instanceof Mode && $mode->isDebug()) {
            $context = $this->buildDebugContext($request, $caughtException, $durationMs);
            $response = $this->decorateWithDebugger($request, $response, $context);
        }

        return $this->finalize($response, $emit);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function resolveErrorResponse(string $identifier, array $arguments, callable $default): ResponseInterface
    {
        $handler = $this->get($identifier);
        if ($handler instanceof ResponseInterface) {
            return $handler;
        }

        if (is_callable($handler)) {
            $response = $handler(...$arguments);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        $response = $default(...$arguments);
        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException('Error handler must return an instance of ResponseInterface.');
        }

        return $response;
    }

    private function decorateNotFoundResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        float $requestStart
    ): ResponseInterface {
        /** @var Mode|null $mode */
        $mode = $this->get(Mode::class);
        if (!$mode instanceof Mode || !$mode->isDebug()) {
            return $response;
        }

        if (!$this->expectsHtml($request)) {
            return $response;
        }

        $durationMs = (microtime(true) - $requestStart) * 1000;
        $context = $this->buildDebugContext($request, null, $durationMs);
        return $this->decorateWithDebugger($request, $response, $context);
    }

    /**
     * Attach the HTML debugger overlay to the outgoing response.
     *
     * @param array<string, mixed> $context
     */
    private function decorateWithDebugger(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $context
    ): ResponseInterface {
        $debugger = $this->get(HtmlDebugger::class);
        if (!$debugger instanceof HtmlDebugger) {
            $debugger = new HtmlDebugger();
        }

        return $debugger->decorate($request, $response, $context);
    }

    /**
     * Build contextual information for the debugger panel.
     *
     * @return array<string, mixed>
     */
    private function buildDebugContext(
        ServerRequestInterface $request,
        ?Throwable $exception = null,
        ?float $durationMs = null
    ): array {
        $routeParameters = $request->getAttribute('routeParameters', []);
        if (!is_array($routeParameters)) {
            $routeParameters = [];
        }

        $context = [
            'routeName' => $request->getAttribute('routeName'),
            'routeParameters' => $routeParameters,
            'attributes' => $this->normalizeAttributes($request->getAttributes()),
            'environment' => $this->collectEnvironmentSnapshot(),
            'metrics' => $this->buildMetrics($durationMs),
            'headers' => $this->normalizeHeaders($request->getHeaders()),
            'queryParams' => $this->normalizeContextValue($request->getQueryParams()),
            'cookies' => $this->normalizeContextValue($request->getCookieParams()),
            'parsedBody' => $this->normalizeContextValue($request->getParsedBody()),
        ];

        $errors = [];

        if ($exception !== null) {
            $errors['Exception'] = [
                'type' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'location' => sprintf('%s:%d', $exception->getFile(), $exception->getLine()),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $lastError = error_get_last();
        if (is_array($lastError)) {
            $errors['Last PHP Error'] = [
                'type' => $this->errorTypeToString((int) $lastError['type']),
                'message' => $lastError['message'],
                'location' => sprintf('%s:%s', $lastError['file'], $lastError['line']),
            ];
        }

        if ($errors !== []) {
            $context['errors'] = $errors;
        }

        return $context;
    }

    /**
     * @return array<string, string>
     */
    private function buildMetrics(?float $durationMs): array
    {
        $metrics = [];

        if ($durationMs !== null) {
            $metrics['Processing Time'] = sprintf('%.2f ms', max($durationMs, 0));
        }

        $memory = memory_get_peak_usage();
        $metrics['Peak Memory'] = sprintf('%.2f MB', $memory / 1024 / 1024);

        return $metrics;
    }

    /**
     * @param array<int|string, array<int|string, string|int|float>> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $normalized[(string) $name] = implode(', ', array_map('strval', $values));
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, ['route', 'routeName', 'routeParameters'], true)) {
                continue;
            }

            $normalized[(string) $key] = $this->normalizeContextValue($value);
        }

        return $normalized;
    }

    private function normalizeContextValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 3) {
            if (is_object($value)) {
                return sprintf('object(%s)', $value::class);
            }

            if (is_array($value)) {
                return '[...]';
            }

            return $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeContextValue($item, $depth + 1);
            }
            return $normalized;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return sprintf('object(%s)', $value::class);
        }

        if (is_resource($value)) {
            $type = get_resource_type($value) ?: 'resource';
            return sprintf('resource(%s)', $type);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectEnvironmentSnapshot(): array
    {
        $environment = $this->get(Environment::class);
        if ($environment instanceof Environment) {
            $snapshot = [];
            foreach ($environment->all() as $key => $value) {
                $snapshot[(string) $key] = $value;
            }

            return $snapshot;
        }

        return [];
    }

    private function errorTypeToString(int $type): string
    {
        return match ($type) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            2048 => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN',
        };
    }

    private function expectsHtml(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));
        if ($accept === '') {
            return true;
        }

        foreach (explode(',', $accept) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $mediaType = strtolower(trim(explode(';', $segment, 2)[0]));
            if (
                $mediaType === 'text/html' ||
                $mediaType === 'application/xhtml+xml' ||
                $mediaType === '*/*'
            ) {
                return true;
            }
        }

        return false;
    }

    private function isHtmlResponse(ResponseInterface $response): bool
    {
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType === '') {
            return false;
        }

        return str_contains($contentType, 'text/html');
    }

    private function renderExceptionDocument(Throwable $exception): string
    {
        $title = 'Unhandled Exception';
        $type = htmlspecialchars($exception::class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $messageValue = $exception->getMessage();
        $message = $messageValue !== '' ? $messageValue : '(no message)';
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $location = sprintf('%s:%d', $exception->getFile(), $exception->getLine());
        $location = htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $trace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>$title</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            margin: 0;
            background: #0f172a;
            color: #e2e8f0;
            display: grid;
            place-items: center;
            min-height: 100vh;
        }
        main {
            max-width: 760px;
            padding: 32px 36px;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.92);
            box-shadow: 0 25px 45px rgba(15, 23, 42, 0.35);
        }
        h1 {
            margin: 0 0 16px;
            font-size: 2rem;
        }
        p { margin: 0 0 12px; }
        pre {
            margin: 24px 0 0;
            background: rgba(15, 15, 35, 0.6);
            padding: 16px;
            border-radius: 12px;
            overflow: auto;
            line-height: 1.5;
        }
        .exception-type {
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #38bdf8;
            font-size: 0.85rem;
        }
        .exception-message { font-size: 1.1rem; }
        .exception-location { color: #94a3b8; font-size: 0.9rem; }
    </style>
</head>
<body>
<main>
    <h1>$title</h1>
    <p class="exception-type">$type</p>
    <p class="exception-message">$message</p>
    <p class="exception-location">$location</p>
    <pre>$trace</pre>
</main>
</body>
</html>
HTML;
    }

    private function finalize(ResponseInterface $response, bool $emit): ResponseInterface
    {
        if ($emit) {
            $emitter = $this->get(ResponseEmitter::class);
            if (!is_object($emitter) || !method_exists($emitter, 'emit')) {
                $emitter = new ResponseEmitter();
            }

            $emitter->emit($response);
        }

        return $response;
    }
}
