<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Http\Message\EmptyResponse;
use Framework\Http\Message\JsonResponse;
use Framework\Http\Message\Response;
use Framework\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * Factory that converts controller return values into PSR-7 responses.
 *
 * Controllers and middleware are allowed to return scalars, arrays, JSON
 * serialisable objects, raw streams or full `ResponseInterface` instances. This
 * factory handles the conversion logic in one place so the rest of the HTTP
 * pipeline can work with strict PSR-7 types.
 */
class ResponseFactory
{
    /**
     * Normalise arbitrary content into a `ResponseInterface`.
     *
     * @param mixed $content Value returned by a controller or middleware.
     * @param int   $status  Optional status code to apply to generated responses.
     */
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

        if (is_scalar($content)) {
            return new Response($status, [], new Stream((string) $content));
        }

        if (is_resource($content)) {
            return new Response($status, [], new Stream($content));
        }

        return new Response($status, [], new Stream(''));
    }
}
