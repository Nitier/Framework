<?php

declare(strict_types=1);

namespace Framework\Debug;

use Framework\Http\Message\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Lightweight HTML debugger overlay used while the framework runs in debug mode.
 *
 * The debugger augments HTML responses with a compact panel displaying request
 * metadata, routing details, request attributes and selected environment
 * values. This keeps diagnostics close to the browser without requiring a full
 * toolbar integration.
 */
class HtmlDebugger
{
    private const MARKER = '<!-- framework-debugger -->';

    /**
     * Attach the debug overlay to a response if it contains HTML content.
     *
     * @param array<string, mixed> $context Additional runtime diagnostics.
     */
    public function decorate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $context = []
    ): ResponseInterface {
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return $response;
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $html = $body->getContents();
        if ($html === '' || str_contains($html, self::MARKER)) {
            return $response;
        }

        $panel = $this->renderPanel($request, $context);
        $injected = $this->injectPanel($html, $panel);

        return $response->withBody(new Stream($injected));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPanel(ServerRequestInterface $request, array $context): string
    {
        $requestSection = [
            'Method' => $request->getMethod(),
            'URI' => (string) $request->getUri(),
            'Protocol' => $request->getProtocolVersion(),
        ];

        $routeName = $context['routeName'] ?? $request->getAttribute('routeName') ?? '—';
        if ($routeName instanceof \Stringable) {
            $routeName = (string) $routeName;
        } elseif (!is_string($routeName)) {
            $routeName = is_scalar($routeName) ? (string) $routeName : var_export($routeName, true);
        }

        $routeSection = [
            'Name' => $routeName,
            'Parameters' => $this->renderArray(
                $context['routeParameters'] ?? $request->getAttribute('routeParameters', [])
            ),
        ];

        $attributes = $context['attributes'] ?? $request->getAttributes();
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $environment = $context['environment'] ?? [];
        if (!is_array($environment)) {
            $environment = [];
        }

        $errors = $context['errors'] ?? [];
        if (!is_array($errors)) {
            $errors = [];
        }

        $sections = [
            'Request' => $this->renderDefinitionList($requestSection),
            'Route' => $this->renderDefinitionList($routeSection),
        ];

        $metrics = $context['metrics'] ?? [];
        if (is_array($metrics) && $metrics !== []) {
            /** @var array<string, string> $metrics */
            $sections['Performance'] = $this->renderDefinitionList($metrics);
        }

        $sections['Attributes'] = $this->renderArray($attributes, true);
        $sections['Headers'] = $this->renderArray($context['headers'] ?? [], true);
        $sections['Query Params'] = $this->renderArray($context['queryParams'] ?? [], true);
        $sections['Cookies'] = $this->renderArray($context['cookies'] ?? [], true);
        $sections['Parsed Body'] = $this->renderArray($context['parsedBody'] ?? [], true);
        $sections['Environment'] = $this->renderArray($environment, true);

        if ($errors !== []) {
            $sections['Errors'] = $this->renderArray($errors, true);
        }

        $sectionsHtml = '';
        foreach ($sections as $title => $content) {
            $sectionsHtml .= sprintf(
                '<section><h2>%s</h2>%s</section>',
                htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $content
            );
        }

        return <<<HTML
            <!-- framework-debugger -->
            <div id="framework-debugger" class="collapsed">
                <button type="button" class="debugger-toggle" aria-expanded="false">
                    Debug
                </button>
                <div class="debugger-panel" aria-hidden="true">
                    <header>
                        <h1>Framework Debugger</h1>
                        <p>Displayed because debug mode is enabled.</p>
                    </header>
                    <div class="debugger-content">$sectionsHtml</div>
                </div>
            </div>
            <style>
                #framework-debugger {
                    position: fixed;
                    bottom: 24px;
                    right: 24px;
                    z-index: 99999;
                    font-family: system-ui, sans-serif;
                }
                #framework-debugger .debugger-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 10px 16px;
                    border-radius: 999px;
                    border: none;
                    background: #1d4ed8;
                    color: #f8fafc;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.25);
                    transition: transform 0.2s ease;
                }
                #framework-debugger .debugger-toggle:hover {
                    transform: translateY(-1px);
                }
                #framework-debugger .debugger-panel {
                    width: 520px;
                    max-height: 420px;
                    overflow: auto;
                    padding: 24px 28px;
                    border-radius: 20px;
                    background: rgba(248, 250, 252, 0.96);
                    color: #0f172a;
                    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.25);
                    margin-top: 14px;
                }
                #framework-debugger.collapsed .debugger-panel {
                    display: none;
                }
                #framework-debugger header { margin-bottom: 16px; }
                #framework-debugger h1 {
                    margin: 0;
                    font-size: 1.15rem;
                    font-weight: 700;
                    color: #0f172a;
                }
                #framework-debugger p {
                    margin: 4px 0 0;
                    color: #475569;
                    font-size: 0.85rem;
                }
                #framework-debugger section { margin-bottom: 18px; }
                #framework-debugger section:last-child { margin-bottom: 0; }
                #framework-debugger section h2 {
                    margin: 0 0 8px;
                    font-size: 0.95rem;
                    color: #1d4ed8;
                }
                #framework-debugger table.debugger-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 0.85rem;
                    background: rgba(255, 255, 255, 0.9);
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    overflow: hidden;
                }
                #framework-debugger table.debugger-table th,
                #framework-debugger table.debugger-table td {
                    text-align: left;
                    padding: 8px 12px;
                    vertical-align: top;
                }
                #framework-debugger table.debugger-table thead {
                    background: #e2e8f0;
                    color: #0f172a;
                }
                #framework-debugger table.debugger-table tbody tr:nth-child(odd) {
                    background: rgba(241, 245, 249, 0.7);
                }
                #framework-debugger ul {
                    margin: 0;
                    padding-left: 18px;
                    font-size: 0.85rem;
                    color: #0f172a;
                }
                #framework-debugger li { margin-bottom: 4px; }
                #framework-debugger code {
                    background: rgba(148, 163, 184, 0.25);
                    padding: 1px 4px;
                    border-radius: 4px;
                    font-family: 'Fira Code', monospace;
                    color: #0f172a;
                }
            </style>
            <script>
                (function () {
                    var root = document.getElementById('framework-debugger');
                    if (!root) {
                        return;
                    }
                    var toggle = root.querySelector('.debugger-toggle');
                    var panel = root.querySelector('.debugger-panel');
                    if (!toggle || !panel) {
                        return;
                    }
                    toggle.addEventListener('click', function () {
                        var collapsed = root.classList.toggle('collapsed');
                        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                        panel.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
                    });
                })();
            </script>
        HTML;
    }

    private function injectPanel(string $html, string $panel): string
    {
        $pos = strripos($html, '</body>');
        if ($pos === false) {
            return $html . $panel;
        }

        return substr($html, 0, $pos) . $panel . substr($html, $pos);
    }

    /**
     * @param array<string, string> $values
     */
    private function renderDefinitionList(array $values): string
    {
        if ($values === []) {
            return '<p>—</p>';
        }

        $rows = [];
        foreach ($values as $key => $value) {
            $rows[] = sprintf(
                '<tr><th scope="row">%s</th><td>%s</td></tr>',
                htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return '<table class="debugger-table"><tbody>' . implode('', $rows) . '</tbody></table>';
    }

    /**
     * @param array<string, mixed>|mixed $value
     */
    private function renderArray(mixed $value, bool $expand = false, int $depth = 0): string
    {
        if (!is_array($value)) {
            $string = htmlspecialchars(
                $this->stringifyValue($value),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            return '<p>' . $string . '</p>';
        }

        if ($value === []) {
            return '[]';
        }

        if ($depth === 0 && $this->isAssociative($value)) {
            $rows = [];
            foreach ($value as $key => $item) {
                $rows[] = sprintf(
                    '<tr><th scope="row">%s</th><td>%s</td></tr>',
                    htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    $this->formatArrayValue($item, $depth + 1)
                );
            }

            return '<table class="debugger-table"><tbody>' . implode('', $rows) . '</tbody></table>';
        }

        $items = [];
        foreach ($value as $key => $item) {
            $items[] = sprintf(
                '<li><code>%s</code>: %s</li>',
                htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $this->formatArrayValue($item, $depth + 1)
            );
        }

        $class = $expand ? ' class="expanded"' : '';
        return '<ul' . $class . '>' . implode('', $items) . '</ul>';
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return var_export($value, true);
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * Render nested array or scalar as HTML-safe string.
     */
    private function formatArrayValue(mixed $value, int $depth): string
    {
        if (is_array($value)) {
            return $this->renderArray($value, true, $depth);
        }

        return htmlspecialchars($this->stringifyValue($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
