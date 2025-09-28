<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Framework\Http\Message\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'message' => 'Welcome to the test application',
            'requestId' => $request->getAttribute('requestId'),
        ]);
    }

    public function greet(ServerRequestInterface $request, array $attributes = []): ResponseInterface
    {
        $name = $request->getAttribute('name') ?? $attributes['name'] ?? 'Guest';
        $routeName = $request->getAttribute('routeName');

        return new JsonResponse([
            'message' => sprintf('Hello, %s!', ucfirst((string) $name)),
            'route' => $routeName,
            'requestId' => $request->getAttribute('requestId'),
        ]);
    }
}
