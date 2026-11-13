<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="tg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Scrum Planner') ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
  <a class="logo" href="projects.php">Scrum Planner</a>
  <nav>
    <?php if (user()): ?>
      <span><?= e(user()['name']) ?></span>
      <a href="projects.php">Лоиҳаҳо</a>
      <a href="logout.php">Баромад</a>
    <?php endif; ?>
  </nav>
</header>
<main class="wrap">
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f[0]) ?>"><?= e($f[1]) ?></div>
<?php endif; ?>
