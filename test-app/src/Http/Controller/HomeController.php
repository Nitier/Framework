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

    /**
     * @param array<string, mixed> $attributes
     */
    public function greet(ServerRequestInterface $request, array $attributes = []): ResponseInterface
    {
        $rawName = $request->getAttribute('name');
        if (!is_string($rawName) || $rawName === '') {
            $fallback = $attributes['name'] ?? 'Guest';
            $rawName = is_string($fallback) ? $fallback : 'Guest';
        }
        $name = ucfirst($rawName);
        $routeName = $request->getAttribute('routeName');

        return new JsonResponse([
            'message' => sprintf('Hello, %s!', $name),
            'route' => $routeName,
            'requestId' => $request->getAttribute('requestId'),
        ]);
    }
}
