<?php

declare(strict_types=1);

namespace Test\Http;

use Framework\General\Mode;
use Framework\Http\Message\ServerRequest;
use Framework\Http\ResponseFactory;
use Framework\Http\Routing\Router;
use Framework\Kernel;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Psr\Http\Message\ServerRequestInterface;

class KernelHandleTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new Kernel();
        $appPath = dirname(__DIR__, 2) . '/examples/test-app';
        $this->kernel->loadApplication($appPath);
        $this->kernel->set(Mode::DEBUG, false);
    }

    public function testHandleReturnsJsonResponseFromController(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/hello/jane');
        $response = $this->kernel->handle($request, false);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test-request', $response->getHeaderLine('X-Request-Id'));
        self::assertSame('applied', $response->getHeaderLine('X-Route-Middleware'));

        $body = $response->getBody();
        $body->rewind();
        /** @var array<string, mixed> $data */
        $data = json_decode($body->getContents(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Hello, Jane!', $data['message']);
        self::assertSame('hello', $data['route']);
        self::assertSame('test-request', $data['requestId']);
    }

    public function testHandleReturnsNotFoundWhenRouteMissing(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/unknown');
        $response = $this->kernel->handle($request, false);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $body = $response->getBody();
        $body->rewind();
        $markup = $body->getContents();
        self::assertStringContainsString('Page Not Found', $markup);
        self::assertStringContainsString('Return to homepage', $markup);
    }

    public function testHandleReturnsMethodNotAllowed(): void
    {
        $request = new ServerRequest('POST', 'http://example.com/hello/jane');
        $response = $this->kernel->handle($request, false);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('Allow'));
        $body = $response->getBody();
        $body->rewind();
        self::assertSame('Method Not Allowed', $body->getContents());
    }

    public function testHandleEmitsResponseByDefault(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/hello/jane');
        ob_start();
        $response = $this->kernel->handle($request);
        $output = ob_get_clean();
        if ($output === false) {
            $output = '';
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($output);
        /** @var array<string, mixed> $data */
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Hello, Jane!', $data['message']);
    }

    public function testCustomNotFoundHandlerOverridesDefault(): void
    {
        $this->kernel->set(
            Kernel::ERROR_HANDLER_NOT_FOUND,
            static fn(ServerRequestInterface $request) => ResponseFactory::from('Custom 404', 404)
        );

        $request = new ServerRequest('GET', 'http://example.com/unknown');
        $response = $this->kernel->handle($request, false);

        self::assertSame(404, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        self::assertSame('Custom 404', $body->getContents());
    }

    public function testCustomExceptionHandlerOverridesDefault(): void
    {
        /** @var Router $router */
        $router = $this->kernel->get(Router::class);
        $router->get('/fail', static function (): void {
            throw new RuntimeException('boom');
        });

        $this->kernel->set(Kernel::ERROR_HANDLER_EXCEPTION, ResponseFactory::from('Custom 500', 500));

        $request = new ServerRequest('GET', 'http://example.com/fail');
        $response = $this->kernel->handle($request, false);

        self::assertSame(500, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        self::assertSame('Custom 500', $body->getContents());
    }

    public function testHtmlTemplateRouteRendersPage(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/about');
        $response = $this->kernel->handle($request, false);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $body = $response->getBody();
        $body->rewind();
        $markup = $body->getContents();

        self::assertStringContainsString('class="ui-title"', $markup);
        self::assertStringContainsString('About this Mini Framework', $markup);
        self::assertStringContainsString('examples/test-app/template/about.php', $markup);
    }

    public function testDebuggerDecoratesHtmlResponsesInDebugMode(): void
    {
        $this->kernel->set(Mode::DEBUG, true);

        $request = (new ServerRequest('GET', 'http://example.com/about'))
            ->withHeader('Accept', 'text/html');

        $response = $this->kernel->handle($request, false);

        self::assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        $markup = $body->getContents();

        self::assertStringContainsString('framework-debugger', $markup);
        self::assertStringContainsString('Displayed because debug mode is enabled.', $markup);
        self::assertStringContainsString('Processing Time', $markup);
        self::assertStringContainsString('Performance', $markup);
    }

    public function testDebuggerCapturesExceptionsForHtmlRequests(): void
    {
        $this->kernel->set(Mode::DEBUG, true);

        /** @var Router $router */
        $router = $this->kernel->get(Router::class);
        $router->get('/fail-debug', static function (): void {
            throw new RuntimeException('boom');
        })->name('fail.debug');

        $request = (new ServerRequest('GET', 'http://example.com/fail-debug'))
            ->withHeader('Accept', 'text/html');

        $response = $this->kernel->handle($request, false);

        self::assertSame(500, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        $markup = $body->getContents();

        self::assertStringContainsString('framework-debugger', $markup);
        self::assertStringContainsString('Unhandled Exception', $markup);
        self::assertStringContainsString('boom', $markup);
        self::assertStringContainsString('Debug', $markup);
    }

    public function testDebugModeRethrowsExceptionsForNonHtmlRequests(): void
    {
        $this->kernel->set(Mode::DEBUG, true);

        /** @var Router $router */
        $router = $this->kernel->get(Router::class);
        $router->get('/fail-json', static function (): void {
            throw new RuntimeException('json boom');
        });

        $request = (new ServerRequest('GET', 'http://example.com/fail-json'))
            ->withHeader('Accept', 'application/json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('json boom');

        $this->kernel->handle($request, false);
    }
}
