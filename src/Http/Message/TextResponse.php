<?php

declare(strict_types=1);

namespace Framework\Http\Message;

class TextResponse extends Response
{
    public function __construct(string $text, int $status = 200, array $headers = [])
    {
        $headers = ['Content-Type' => 'text/plain; charset=utf-8'] + $headers;
        parent::__construct($status, $headers, new Stream($text));
    }
}
