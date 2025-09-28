<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Http\Message\EmptyResponse;
use Framework\Http\Message\JsonResponse;
use Framework\Http\Message\Response;
use Framework\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory
{
    public static function from(mixed $content, int $status = 200): ResponseInterface
    {
        if ($content instanceof ResponseInterface) {
            return $content;
        }

        if ($content === null) {
            return new EmptyResponse($status >= 200 && $status < 300 ? 204 : $status);
        }

        if (is_array($content) || is_object($content)) {
            return new JsonResponse($content, $status);
        }

        if ($content instanceof \Stringable) {
            $content = (string) $content;
        }

        return new Response($status, [], new Stream((string) $content));
    }
}
