<?php

declare(strict_types=1);

namespace Test\Http;

use Framework\Http\Message\EmptyResponse;
use Framework\Http\Message\HtmlResponse;
use Framework\Http\Message\JsonResponse;
use PHPUnit\Framework\TestCase;

class ResponseClassesTest extends TestCase
{
    public function testJsonResponseEncodesPayload(): void
    {
        $response = new JsonResponse(['foo' => 'bar']);
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $body = $response->getBody();
        $body->rewind();
        self::assertSame('{"foo":"bar"}', $body->getContents());
    }

    public function testHtmlResponseAddsHeader(): void
    {
        $response = new HtmlResponse('<p>Hello</p>');
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $body = $response->getBody();
        $body->rewind();
        self::assertSame('<p>Hello</p>', $body->getContents());
    }

    public function testEmptyResponseDefaultsTo204(): void
    {
        $response = new EmptyResponse();
        self::assertSame(204, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        self::assertSame('', $body->getContents());
    }
}
