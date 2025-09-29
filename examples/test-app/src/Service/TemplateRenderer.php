<?php

declare(strict_types=1);

namespace App\Service;

use Framework\General\Path;
use RuntimeException;

class TemplateRenderer
{
    private string $basePath;

    public function __construct(Path $path, ?string $baseTemplateDir = null)
    {
        $this->basePath = $baseTemplateDir !== null
            ? rtrim($baseTemplateDir, DIRECTORY_SEPARATOR)
            : $path->get('template');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $template, array $parameters = []): string
    {
        $file = $this->resolveTemplatePath($template);
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Template "%s" not found.', $template));
        }

        extract($parameters, EXTR_SKIP);
        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        include $file;
        return (string) ob_get_clean();
    }

    private function resolveTemplatePath(string $template): string
    {
        $template = str_replace(['\\', '..'], ['/', ''], $template);
        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($template, DIRECTORY_SEPARATOR);
    }
}
