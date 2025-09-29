<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use InvalidArgumentException;

class RedirectResponse extends Response
{
    public function __construct(string $uri, int $status = 302, array $headers = [])
    {
        if ($status < 300 || $status >= 400) {
            throw new InvalidArgumentException('Redirect responses require a 3xx status code.');
        }

        $headers = ['Location' => $uri] + $headers;
        parent::__construct($status, $headers, new Stream(''));
    }
}
