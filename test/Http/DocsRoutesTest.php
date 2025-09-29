<?php

declare(strict_types=1);

namespace Test\Http;

use Framework\Kernel;
use PHPUnit\Framework\TestCase;
use Framework\Http\Message\ServerRequest;

class DocsRoutesTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new Kernel();
        $appPath = dirname(__DIR__, 2) . '/examples/test-app';
        $this->kernel->loadApplication($appPath);
    }

    public function testDocsIndexReturnsHtmlList(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/docs');
        $response = $this->kernel->handle($request, false);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();
        self::assertStringContainsString('Документация фреймворка', $html);
        self::assertStringContainsString('/docs/overview', $html);
    }

    public function testDocsArticleRendersMarkdown(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/docs/overview');
        $response = $this->kernel->handle($request, false);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();
        self::assertStringContainsString('<h1>Обзор фреймворка</h1>', $html);
        self::assertStringContainsString('PSR-7', $html);
    }
}
