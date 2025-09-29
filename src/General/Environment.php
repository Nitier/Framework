<?php

declare(strict_types=1);

namespace Framework\General;

/**
 * Service responsible for loading environment variables into the container.
 *
 * Besides parsing `.env` files, the service mirrors process environment values
 * (`getenv()`) so that the entire runtime configuration can be accessed through
 * the kernel. This avoids scattering direct superglobal access across the
 * framework and makes the environment state easier to inspect in debug mode.
 */
class Environment extends Entity
{
    /**
     * Container key used to store the full environment snapshot in debug mode.
     */
    public const string ENVIRONMENT = 'kernel.environment';

    /**
     * Load environment variables from one or multiple files.
     *
     * Later files override earlier ones. Missing or unreadable files are
     * skipped, allowing optional configuration overrides (e.g. `.env.local`).
     * @param string|array<string>|null $filePaths
     */
    public function load(null|string|array $filePaths = null): void
    {
        if ($filePaths === null) {
            $filePaths = $this->path()->get('.env');
        }
        $files = is_array($filePaths) ? $filePaths : [$filePaths];
        foreach ($files as $filePath) {
            if (!file_exists(filename: $filePath) || !is_readable($filePath)) {
                continue;
            }

            if (
                !($lines = file(
                    $filePath,
                    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                ))
            ) {
                continue;
            }

            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2) + [null, null];
                if ($key === null || $value === null) {
                    continue;
                }
                $key = trim($key);
                $value = $this->cleanValue($value);

                if (!empty($key)) {
                    $this->set($key, $this->castValue((string) $value));
                }
            }
        }
        foreach (getenv() as $key => $value) {
            $this->set($key, $value);
        }
    }
    /**
     * Retrieve a single environment value from the container.
     * @param string $key
     * @param mixed $default
     * @return mixed

     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->kernel()->has($key)) {
            return $default;
        }
        $value = $this->kernel()->get($key);
        return $this->castValue($value);
    }

    /**
     * Persist an environment entry in the container, serialising complex values
     * when required. In debug mode the value is also recorded in a dedicated
     * snapshot for diagnostic purposes.
     */
    public function set(string $key, mixed $value): void
    {
        if (empty($key)) {
            return;
        }

        if (is_object($value)) {
            $value = serialize($value);
        } elseif (is_array($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }
        $this->kernel()->set($key, $value);
        // If debug mode is enabled, save the environment variable to the kernel
        if ($this->mode()->isDebug()) {
            /** @var array<mixed> $env */
            $env = $this->kernel()->get(self::ENVIRONMENT) ?? [];
            $env[$key] = $value;
            $this->kernel()->set(self::ENVIRONMENT, $env);
        }
    }

    /**
     * Return every environment entry that was explicitly stored through
     * {@see set()} (only populated in debug mode).
     *
     * @return array<mixed>
     */
    public function all(): array
    {
        $variables = [];
        $array = $this->kernel->get(self::ENVIRONMENT) ?? [];
        if (!is_array($array)) {
            return $variables;
        }
        foreach ($array as $key => $value) {
            $variables[$key] = $this->castValue($value);
        }
        return $variables;
    }

    /**
     * Strip surrounding single/double quotes and whitespace from a raw value.
     */
    private function cleanValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Convert a raw string into the most appropriate PHP type.
     */
    private function castValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (strtolower($value) === 'null') {
            return null;
        }

        if (strtolower($value) === 'true') {
            return true;
        }

        if (strtolower($value) === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if ($result = $this->jsonParse($value)) {
            return $result;
        }

        if ($this->isSerialized($value)) {
            return unserialize($value);
        }

        return $value;
    }

    /**
     * Attempt JSON decoding while silencing conversion errors.
     */
    private function jsonParse(string $value): mixed
    {
        $result = json_decode($value, true, 512);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
        return false;
    }

    /**
     * Heuristic check to determine whether a string contains serialised data.
     */
    private function isSerialized(string $value): bool
    {
        return (bool) preg_match('/^O:\d+:"[\w\\\]+":\d+:{.*}$/', $value);
    }
}
