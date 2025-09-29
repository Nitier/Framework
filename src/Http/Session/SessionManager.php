<?php

declare(strict_types=1);

namespace Framework\Http\Session;

use RuntimeException;

class SessionManager
{
    private bool $autoStart;

    public function __construct(bool $autoStart = false)
    {
        $this->autoStart = $autoStart;
        if ($autoStart) {
            $this->start();
        }
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        if (headers_sent() && php_sapi_name() !== 'cli') {
            throw new RuntimeException('Cannot start session because headers have already been sent.');
        }

        if (!session_start()) {
            throw new RuntimeException('Unable to start the session.');
        }
    }

    public function commit(): void
    {
        if ($this->isStarted()) {
            session_write_close();
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function id(): string
    {
        if (!$this->isStarted() && $this->autoStart) {
            $this->start();
        }

        return $this->isStarted() ? (string) session_id() : '';
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        session_regenerate_id($deleteOldSession);
    }

    public function invalidate(bool $regenerateId = true): void
    {
        $this->ensureStarted();

        $_SESSION = [];
        session_unset();
        session_destroy();

        if ($regenerateId) {
            $this->start();
            session_regenerate_id(true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->ensureStarted();
        /** @var array<string, mixed> $data */
        $data = $_SESSION;
        return $data;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return array_key_exists($key, $_SESSION);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    private function ensureStarted(): void
    {
        if ($this->isStarted()) {
            return;
        }

        $this->start();
    }
}
