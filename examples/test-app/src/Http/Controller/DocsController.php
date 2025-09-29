<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Service\MarkdownRenderer;
use App\Service\TemplateRenderer;
use Framework\General\Path;
use Framework\Http\Message\HtmlResponse;
use Framework\Http\Message\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DocsController
{
    private TemplateRenderer $templates;
    private MarkdownRenderer $markdown;
    private string $docsDirectory;
    /** @var array<string, string> */
    private array $documents;

    public function __construct(TemplateRenderer $templates, MarkdownRenderer $markdown, Path $path)
    {
        $this->templates = $templates;
        $this->markdown = $markdown;
        $this->docsDirectory = $path->get('..', '..', 'docs');
        $this->documents = $this->discoverDocuments();
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $items = [];
        foreach ($this->documents as $slug => $file) {
            $items[] = [
                'slug' => $slug,
                'title' => $this->extractTitle($file) ?? ucfirst($slug),
                'path' => '/docs/' . $slug,
            ];
        }

        $html = $this->templates->render('docs/index', [
            'title' => 'Документация фреймворка',
            'items' => $items,
        ]);

        return new HtmlResponse($html);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $slugAttr = $request->getAttribute('slug');
        $slug = is_string($slugAttr) ? $slugAttr : '';
        $slug = rtrim($slug,'.md');
        if ($slug === '' || !isset($this->documents[$slug])) {
            $html = $this->templates->render('docs/article', [
                'title' => 'Документ не найден',
                'content' => '<p>Запрошенный документ не существует. <a href="/docs">Вернуться к списку</a>.</p>',
            ]);

            return new HtmlResponse($html, 404);
        }

        $file = $this->documents[$slug];
        $content = file_get_contents($file) ?: '';
        $title = $this->extractTitle($file) ?? ucfirst($slug);
        $html = $this->templates->render('docs/article', [
            'title' => $title,
            'content' => $this->markdown->toHtml($content),
        ]);

        return new HtmlResponse($html);
    }

    /**
     * @return array<string, string>
     */
    private function discoverDocuments(): array
    {
        $pattern = $this->docsDirectory . DIRECTORY_SEPARATOR . '*.md';
        $files = glob($pattern) ?: [];
        $documents = [];
        foreach ($files as $file) {
            $slug = basename($file, '.md');
            $documents[$slug] = $file;
        }

        ksort($documents);
        return $documents;
    }

    private function extractTitle(string $file): ?string
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }
        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, '#')) {
                fclose($handle);
                return ltrim($trimmed, '# ');
            }
        }

        fclose($handle);
        return null;
    }
}
