
<?php
require_once 'config.php';

if (user()) {
    go('projects.php');
}

function login_text($tg, $ru) {
    global $current_language;
    return $current_language === 'ru' ? $ru : $tg;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = $pdo->prepare('SELECT * FROM users WHERE email=?');
    $s->execute([trim($_POST['email'] ?? '')]);
    $u = $s->fetch();

    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        $_SESSION['user'] = [
            'id' => $u['id'],
            'name' => $u['name']
        ];

        go('projects.php');
    }

    $err = login_text(
        'Email ё парол нодуруст аст',
        'Неверный email или пароль'
    );
}

$title = login_text('Воридшавӣ', 'Вход');
include 'header.php';
?>

<div class="card narrow">
    <h2><?= login_text('Воридшавӣ', 'Вход в аккаунт') ?></h2>

    <?php if ($err): ?>
        <div class="flash err"><?= e($err) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>
            Email
            <input type="email" name="email" required>
        </label>

        <label>
            <?= login_text('Парол', 'Пароль') ?>
            <input type="password" name="password" required>
        </label>

        <button class="btn">
            <?= login_text('Ворид шудан', 'Войти') ?>
        </button>
    </form>

    <p>
        <?= login_text('Ҳисоб надоред?', 'Нет аккаунта?') ?>
        <a href="register.php">
            <?= login_text('Сабти ном', 'Зарегистрироваться') ?>
        </a>
    </p>
</div>

<?php include 'footer.php'; ?>
