<?php

declare(strict_types=1);

namespace Framework\Http\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class StartSessionMiddleware implements MiddlewareInterface
{
    private SessionManager $sessions;
    private bool $commit;

    public function __construct(SessionManager $sessions, bool $commit = true)
    {
        $this->sessions = $sessions;
        $this->commit = $commit;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->sessions->start();

        try {
            $response = $handler->handle($request);
        } finally {
            if ($this->commit) {
                $this->sessions->commit();
            }
        }

        return $response;
    }
}
