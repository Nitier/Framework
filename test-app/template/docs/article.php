<?php
/** @var string $title */
/** @var string $content */
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
        a { color: #1d4ed8; }
        pre { background: #0f172a; color: #f8fafc; padding: 16px; border-radius: 12px; overflow: auto; }
        code { font-family: 'Fira Code', monospace; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>
<header>
    <h1><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
</header>
<main>
    <div><?= $content ?></div>
    <p><a href="/docs">← Вернуться к списку документов</a></p>
</main>
</body>
</html>
