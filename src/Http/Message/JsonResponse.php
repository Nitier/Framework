<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use JsonException;

class JsonResponse extends Response
{
    /**
     * @param array<string, string|array<int, string>> $headers
     */
    public function __construct(
        mixed $data,
        int $status = 200,
        array $headers = [],
        int $flags = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) {
        /** @var array<string, string|array<int, string>> $headers */
        $headers = ['Content-Type' => 'application/json; charset=utf-8'] + $headers;

        try {
            $json = json_encode($data, $flags);
        } catch (JsonException $exception) {
            $json = json_encode([
                'error' => 'JSON encoding failed',
                'message' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            $status = 500;
        }

        if (!is_string($json)) {
            $json = '';
        }

        parent::__construct($status, $headers, new Stream($json));
    }
}
