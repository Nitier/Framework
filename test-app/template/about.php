<?php
/** @var string $title */
/** @var string $heading */
/** @var array<int, string> $paragraphs */
/** @var array<int, string> $highlights */
/** @var string $footer */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        body {
            margin: 0;
            background: #f6f8fb;
            color: #1f2933;
            display: grid;
            place-items: center;
            min-height: 100vh;
        }
        main {
            max-width: 640px;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 40px;
            box-shadow: 0 15px 45px rgba(15, 23, 42, 0.12);
        }
        h1 {
            margin: 0 0 16px;
            font-size: 2.2rem;
        }
        p {
            margin: 0 0 20px;
            line-height: 1.6;
        }
        ul {
            padding-left: 20px;
            margin: 0;
        }
        .meta {
            margin-top: 24px;
            font-size: 0.9rem;
            color: #52606d;
        }
    </style>
</head>
<body>
<main>
    <h1><?= htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
    <?php foreach ($paragraphs as $paragraph): ?>
        <p><?= htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php endforeach; ?>
    <ul>
        <?php foreach ($highlights as $item): ?>
            <li><?= htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="meta">
        <?= htmlspecialchars($footer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>
</main>
</body>
</html>
