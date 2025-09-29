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
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 48px 24px;
            background: radial-gradient(circle at top, #dce8ff 0%, #eff6ff 55%, #e0f2fe 100%);
            color: #0f172a;
        }
        .ui-shell {
            width: min(860px, 100%);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 32px;
            box-shadow: 0 35px 80px rgba(15, 23, 42, 0.16);
            overflow: hidden;
            position: relative;
        }
        .ui-shell::before {
            content: "";
            position: absolute;
            inset: -120px auto auto -120px;
            width: 260px;
            height: 260px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.14), rgba(14, 165, 233, 0.18));
            border-radius: 50%;
            filter: blur(0.5px);
        }
        .ui-shell::after {
            content: "";
            position: absolute;
            inset: auto -120px -140px auto;
            width: 220px;
            height: 220px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(129, 140, 248, 0.08));
            border-radius: 50%;
        }
        .ui-inner {
            position: relative;
            z-index: 1;
            padding: 48px 52px;
        }
        .ui-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
        }
        .ui-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            border-radius: 28px;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            color: #f8fafc;
            font-size: 2rem;
            font-weight: 700;
            box-shadow: 0 20px 45px rgba(37, 99, 235, 0.35);
        }
        .ui-badge--compact {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            font-size: 1.35rem;
            box-shadow: 0 16px 36px rgba(37, 99, 235, 0.3);
        }
        .ui-title {
            margin: 0;
            font-size: 2.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .ui-subtitle {
            margin: 12px 0 0;
            font-size: 1.1rem;
            line-height: 1.6;
            color: #1f2937;
        }
        .ui-content {
            font-size: 1rem;
            line-height: 1.6;
            color: #1f2937;
        }
        .ui-content p {
            margin: 0 0 18px;
        }
        .ui-list {
            margin: 12px 0 0;
            padding-left: 22px;
            color: #334155;
        }
        .ui-footnote {
            margin-top: 28px;
            font-size: 0.95rem;
            color: #475569;
        }
        @media (max-width: 720px) {
            body {
                padding: 24px;
            }
            .ui-inner {
                padding: 32px;
            }
            .ui-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .ui-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
<div class="ui-shell">
    <div class="ui-inner">
        <header class="ui-header">
            <div class="ui-badge ui-badge--compact">App</div>
            <div>
                <h1 class="ui-title"><?= htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                <p class="ui-subtitle"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
        </header>
        <div class="ui-content">
            <?php foreach ($paragraphs as $paragraph): ?>
                <p><?= htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
            <ul class="ui-list">
                <?php foreach ($highlights as $item): ?>
                    <li><?= htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="ui-footnote">
                <?= htmlspecialchars($footer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
        </div>
    </div>
</div>
</body>
</html>
