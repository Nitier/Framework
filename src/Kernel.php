<?php

declare(strict_types=1);

namespace Framework;

use DI\Container;
use DI\ContainerBuilder;
use Framework\General\Environment;
use Framework\General\Mode;
use Framework\General\Path;
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

                return $this->finalize($response, $emit);
            }

            $response = $this->resolveErrorResponse(
                self::ERROR_HANDLER_NOT_FOUND,
                [$request],
                static fn(ServerRequestInterface $req): ResponseInterface => ResponseFactory::from('Not Found', 404)
            );

            return $this->finalize($response, $emit);
        }

        $match = $result->getMatch();
        if ($match === null) {
            $response = $this->resolveErrorResponse(
                self::ERROR_HANDLER_NOT_FOUND,
                [$request],
                static fn(ServerRequestInterface $req): ResponseInterface => ResponseFactory::from('Not Found', 404)
            );

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

        try {
            $response = $middlewarePipeline->handle($request);
        } catch (Throwable $exception) {
            /** @var Mode|null $mode */
            $mode = $this->get(Mode::class);
            if ($mode !== null && $mode->isDebug()) {
                throw $exception;
            }

            $response = $this->resolveErrorResponse(
                self::ERROR_HANDLER_EXCEPTION,
                [$request, $exception],
                static function (ServerRequestInterface $req, Throwable $e): ResponseInterface {
                    return ResponseFactory::from('Internal Server Error', 500);
                }
            );
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
