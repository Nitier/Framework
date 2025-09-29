<?php

declare(strict_types=1);

namespace Test\Http\Cookie;

use Framework\Http\Cookie\CookieJar;
use Framework\Http\Cookie\CookieQueueMiddleware;
use Framework\Http\Message\TextResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Framework\Http\Message\ServerRequest;

class CookieJarTest extends TestCase
{
    public function testCookiesAreQueuedAndApplied(): void
    {
        $jar = new CookieJar();
        $jar->queue('token', 'abc123', [
            'path' => '/',
            'httpOnly' => true,
            'sameSite' => 'Lax',
        ]);

        $middleware = new CookieQueueMiddleware($jar);
        $response = $middleware->process(new ServerRequest('GET', 'https://example.com'), new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        });

        self::assertStringContainsString('token=abc123', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Path=/', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('SameSite=Lax', $response->getHeaderLine('Set-Cookie'));
    }

    public function testExpireQueuesPastCookie(): void
    {
        $jar = new CookieJar();
        $jar->expire('remember');
        $response = $jar->apply(new TextResponse('done'));

        $header = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('remember=', $header);
        self::assertStringContainsString('Max-Age=0', $header);
    }
}
