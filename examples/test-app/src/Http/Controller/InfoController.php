<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Framework\Http\Routing\Attribute\Route;
use App\Service\TemplateRenderer;
use Framework\Http\Message\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class InfoController
{
    private TemplateRenderer $templates;

    public function __construct(TemplateRenderer $templates)
    {
        $this->templates = $templates;
    }

    #[Route(methods: ['GET'], path: '/about', name: 'about')]
    public function about(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->templates->render('about', [
            'title' => 'About the Demo Application',
            'heading' => 'About this Mini Framework',
            'paragraphs' => [
                'Этот пример показывает, как можно подключить HTML-шаблоны без изменения ядра фреймворка.',
                'Контроллер получает TemplateRenderer через DI и отрисовывает PHP-шаблон, собирая данные в массив.',
            ],
            'highlights' => [
                'PSR-7/PSR-15 стек для HTTP',
                'Гибкая система маршрутов, включая атрибуты',
                'Поддержка middleware на глобальном и маршрутном уровне',
            ],
            'footer' => 'Шаблон находится в examples/test-app/template/about.php',
        ]);

        return new HtmlResponse($html);
    }
}
