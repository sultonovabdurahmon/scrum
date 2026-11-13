
<?php
require_once 'config.php';

if (user()) {
    go('projects.php');
}

function register_text($tg, $ru) {
    global $current_language;
    return $current_language === 'ru' ? $ru : $tg;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        $err = register_text(
            'Ном, email-и дуруст ва парол (камаш 6 аломат) лозим аст',
            'Введите имя, корректный email и пароль (не менее 6 символов)'
        );
    } else {
        $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $s->execute([$email]);

        if ($s->fetch()) {
            $err = register_text(
                'Ин email аллакай сабт шудааст',
                'Этот email уже зарегистрирован'
            );
        } else {
            $pdo->prepare('INSERT INTO users(name,email,password) VALUES(?,?,?)')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);

            $_SESSION['user'] = [
                'id' => $pdo->lastInsertId(),
                'name' => $name
            ];

            go('projects.php');
        }
    }
}

$title = register_text('Сабти ном', 'Регистрация');
include 'header.php';
?>

<div class="card narrow">
    <h2><?= register_text('Сабти ном', 'Создать аккаунт') ?></h2>

    <?php if ($err): ?>
        <div class="flash err"><?= e($err) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>
            <?= register_text('Ном', 'Имя') ?>
            <input
                name="name"
                required
                value="<?= e($_POST['name'] ?? '') ?>"
            >
        </label>

        <label>
            Email
            <input
                type="email"
                name="email"
                required
                value="<?= e($_POST['email'] ?? '') ?>"
            >
        </label>

        <label>
            <?= register_text('Парол', 'Пароль') ?>
            <input
                type="password"
                name="password"
                required
                minlength="6"
            >
        </label>

        <button class="btn">
            <?= register_text('Сабт шудан', 'Зарегистрироваться') ?>
        </button>
    </form>

    <p>
        <?= register_text('Ҳисоб доред?', 'Уже есть аккаунт?') ?>

        <a href="login.php">
            <?= register_text('Ворид шудан', 'Войти') ?>
        </a>
    </p>
</div>

<?php include 'footer.php'; ?>
