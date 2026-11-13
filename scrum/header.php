
<?php
require_once __DIR__ . '/config.php';

// Сохраняем текущую страницу и её параметры при переключении языка
$current_url = $_SERVER['REQUEST_URI'] ?? 'projects.php';
$parsed_url = parse_url($current_url);

$language_path = $parsed_url['path'] ?? 'projects.php';
$language_query = [];

if (!empty($parsed_url['query'])) {
    parse_str($parsed_url['query'], $language_query);
}

unset($language_query['lang']);

$tg_url = $language_path . '?' . http_build_query(array_merge($language_query, ['lang' => 'tg']));
$ru_url = $language_path . '?' . http_build_query(array_merge($language_query, ['lang' => 'ru']));
?>

<!DOCTYPE html>
<html lang="<?= e($current_language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($title ?? 'ASTAT SCRUM') ?></title>

    <script>
        const savedTheme = localStorage.getItem('astat-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="top">

    <a class="logo" href="projects.php">
        ASTAT SCRUM
    </a>

    <nav>

        <?php if (user()): ?>

            <span><?= e(user()['name']) ?></span>

            <a href="projects.php">
                <?= e(t('projects')) ?>
            </a>

            <a href="logout.php">
                <?= e(t('logout')) ?>
            </a>

        <?php endif; ?>

        <!-- LANGUAGE SWITCHER -->
        <div class="language-switcher">

            <a
                href="<?= e($tg_url) ?>"
                class="language-option <?= $current_language === 'tg' ? 'active' : '' ?>"
            >
                🇹🇯 Тоҷикӣ
            </a>

            <a
                href="<?= e($ru_url) ?>"
                class="language-option <?= $current_language === 'ru' ? 'active' : '' ?>"
            >
                🇷🇺 Русский
            </a>

        </div>

        <!-- THEME SWITCHER -->
        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="<?= e($current_language === 'ru' ? 'Переключить тему' : 'Иваз кардани мавзӯъ') ?>"
        >
            <span id="themeIcon">☀️</span>
            <span id="themeText"></span>
        </button>

    </nav>

</header>

<main class="wrap">

<?php if ($f = flash()): ?>
    <div class="flash <?= e($f[0]) ?>">
        <?= e($f[1]) ?>
    </div>
<?php endif; ?>

<script>
    const themeButton = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');

    const currentLanguage = <?= json_encode($current_language) ?>;

    const themeLabels = {
        tg: {
            light: 'Равшан',
            dark: 'Торик'
        },
        ru: {
            light: 'Светлая',
            dark: 'Тёмная'
        }
    };

    function updateThemeButton() {
        const theme = document.documentElement.getAttribute('data-theme');

        if (theme === 'dark') {
            themeIcon.textContent = '☀️';
            themeText.textContent = themeLabels[currentLanguage].light;
        } else {
            themeIcon.textContent = '🌙';
            themeText.textContent = themeLabels[currentLanguage].dark;
        }
    }

    updateThemeButton();

    themeButton.addEventListener('click', function () {
        const currentTheme =
            document.documentElement.getAttribute('data-theme');

        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', newTheme);

        localStorage.setItem('astat-theme', newTheme);

        updateThemeButton();
    });
</script>
