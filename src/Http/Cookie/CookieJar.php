<?php

declare(strict_types=1);

namespace Framework\Http\Cookie;

use DateInterval;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;

class CookieJar
{
    /** @var array<int, string> */
    private array $queue = [];

    /**
     * @param array<string, mixed> $options
     */
    public function queue(string $name, string $value, array $options = []): void
    {
        $this->queue[] = $this->buildCookie($name, $value, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function expire(string $name, array $options = []): void
    {
        $options['expires'] = time() - 3600;
        $options['maxAge'] = 0;
        $this->queue[] = $this->buildCookie($name, '', $options);
    }

    public function clearQueue(): void
    {
        $this->queue = [];
    }

    /**
     * @return array<int, string>
     */
    public function queued(): array
    {
        return $this->queue;
    }

    public function apply(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->queue as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
        }

        $this->clearQueue();
        return $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildCookie(string $name, string $value, array $options): string
    {
        $cookie = rawurlencode($name) . '=' . rawurlencode($value);

        if (isset($options['expires'])) {
            $cookie .= '; Expires=' . $this->formatExpires($options['expires']);
        }

        if (isset($options['maxAge']) && is_numeric($options['maxAge'])) {
            $cookie .= '; Max-Age=' . (int) $options['maxAge'];
        }

        if (isset($options['path']) && is_string($options['path'])) {
            $cookie .= '; Path=' . $options['path'];
        }

        if (isset($options['domain']) && is_string($options['domain'])) {
            $cookie .= '; Domain=' . $options['domain'];
        }

        if (!empty($options['secure'])) {
            $cookie .= '; Secure';
        }

        if (!empty($options['httpOnly'])) {
            $cookie .= '; HttpOnly';
        }

        if (isset($options['sameSite']) && is_string($options['sameSite'])) {
            $sameSite = ucfirst(strtolower($options['sameSite']));
            if (in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
                $cookie .= '; SameSite=' . $sameSite;
            }
        }

        return $cookie;
    }

    private function formatExpires(mixed $expires): string
    {
        if ($expires instanceof DateTimeInterface) {
            return $expires->format('D, d M Y H:i:s \G\M\T');
        }

        if ($expires instanceof DateInterval) {
            $date = (new \DateTimeImmutable())->add($expires);
            return $date->format('D, d M Y H:i:s \G\M\T');
        }

        if (is_numeric($expires)) {
            $timestamp = (int) $expires;
        } elseif (is_string($expires)) {
            $timestamp = strtotime($expires);
            if ($timestamp === false) {
                $timestamp = time();
            }
        } else {
            $timestamp = time();
        }

        return gmdate('D, d M Y H:i:s \G\M\T', $timestamp);
    }
}
