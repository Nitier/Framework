<?php

declare(strict_types=1);

namespace Framework\Http\Message;

class HtmlResponse extends Response
{
    public function __construct(string $html, int $status = 200, array $headers = [])
    {
        $headers = ['Content-Type' => 'text/html; charset=utf-8'] + $headers;
        parent::__construct($status, $headers, new Stream($html));
    }
}
