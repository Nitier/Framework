<?php

declare(strict_types=1);

namespace Test\Http;

use Framework\Http\Message\ServerRequest;
use Framework\Http\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testRouteMatchingSuccess(): void
    {
        $router = new Router();
        $router->get('/hello/{name}', static function (): void {
        })->name('hello');

        $request = new ServerRequest('GET', 'http://example.com/hello/world');
        $result = $router->match($request);

        self::assertTrue($result->isSuccess());
        $match = $result->getMatch();
        self::assertNotNull($match);
        self::assertSame('hello', $match->getRoute()->getName());
        self::assertSame(['name' => 'world'], $match->getParameters());
    }

    public function testMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/resource', static function (): void {
        });

        $request = new ServerRequest('POST', 'http://example.com/resource');
        $result = $router->match($request);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isMethodNotAllowed());
        self::assertSame(['GET'], $result->getAllowedMethods());
    }

    public function testRouteNotFound(): void
    {
        $router = new Router();
        $request = new ServerRequest('GET', 'http://example.com/unknown');
        $result = $router->match($request);

        self::assertFalse($result->isSuccess());
        self::assertFalse($result->isMethodNotAllowed());
        self::assertSame([], $result->getAllowedMethods());
    }
}
