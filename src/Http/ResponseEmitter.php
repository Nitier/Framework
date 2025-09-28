<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ResponseInterface;

class ResponseEmitter
{
    public function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                $header = $this->formatHeaderName($name);
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $header, $value), false);
                }
            }
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        echo $body->getContents();
    }

    private function formatHeaderName(string $name): string
    {
        $name = strtolower($name);
        return implode('-', array_map(static fn(string $part): string => ucfirst($part), explode('-', $name)));
    }
}
