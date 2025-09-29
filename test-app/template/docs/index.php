<?php
/** @var string $title */
/** @var array<int, array{slug: string, title: string, path: string}> $items */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f8fb; color: #1f2933; }
        header { background: #1f2937; color: #fff; padding: 24px 32px; }
        main { max-width: 960px; margin: 32px auto; background: #fff; padding: 32px; border-radius: 16px; box-shadow: 0 15px 45px rgba(15, 23, 42, 0.12); }
        h1 { margin-top: 0; }
        ul { list-style: none; padding: 0; margin: 24px 0; }
        li + li { margin-top: 12px; }
        a { color: #1d4ed8; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<header>
    <h1><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
</header>
<main>
    <p>Выберите интересующую страницу документации:</p>
    <ul>
        <?php foreach ($items as $item): ?>
            <li><a href="<?= htmlspecialchars($item['path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></li>
        <?php endforeach; ?>
    </ul>
</main>
</body>
</html>
