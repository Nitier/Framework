<?php

declare(strict_types=1);

namespace Test\Http;

use DI\ContainerBuilder;
use Framework\Http\Message\ServerRequest;
use Framework\Http\Middleware\CallableRequestHandler;
use Framework\Http\Middleware\MiddlewarePipeline;
use Framework\Http\ResponseFactory;
use PHPUnit\Framework\TestCase;

class MiddlewarePipelineTest extends TestCase
{
    public function testMiddlewareExecutionOrder(): void
    {
        $container = (new ContainerBuilder())->build();
        $log = [];

        $middlewareOne = function ($request, $handler) use (&$log) {
            $log[] = 'middleware-one-before';
            $response = $handler->handle($request->withAttribute('one', true));
            $log[] = 'middleware-one-after';
            return $response->withHeader('X-One', 'handled');
        };

        $middlewareTwo = function ($request, $handler) use (&$log) {
            $log[] = 'middleware-two-before';
            $response = $handler->handle($request->withAttribute('two', true));
            $log[] = 'middleware-two-after';
            return $response->withHeader('X-Two', 'handled');
        };

        $finalHandler = new CallableRequestHandler(function ($request, array $attributes) use (&$log) {
            $log[] = 'handler';
            return ResponseFactory::from([
                'attributes' => array_keys($request->getAttributes()),
                'received' => array_keys($attributes),
            ]);
        });

        $pipeline = new MiddlewarePipeline($container, [$middlewareOne, $middlewareTwo], $finalHandler);
        $response = $pipeline->handle(new ServerRequest('GET', 'http://example.com/'));

        self::assertSame(
            [
                'middleware-one-before',
                'middleware-two-before',
                'handler',
                'middleware-two-after',
                'middleware-one-after',
            ],
            $log
        );

        self::assertSame('handled', $response->getHeaderLine('X-One'));
        self::assertSame('handled', $response->getHeaderLine('X-Two'));

        $body = $response->getBody();
        $body->rewind();
        $payload = json_decode($body->getContents(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains('one', $payload['attributes']);
        self::assertContains('two', $payload['attributes']);
        self::assertContains('one', $payload['received']);
        self::assertContains('two', $payload['received']);
    }
}
