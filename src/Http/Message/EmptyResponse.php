<?php

declare(strict_types=1);

namespace Framework\Http\Message;

class EmptyResponse extends Response
{
    public function __construct(int $status = 204, array $headers = [])
    {
        parent::__construct($status, $headers, new Stream(''));
    }
}
