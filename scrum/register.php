<?php
require_once 'config.php';
if (user()) go('projects.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        $err = 'Ном, email-и дуруст ва парол (камаш 6 аломат) лозим аст';
    } else {
        $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $s->execute([$email]);
        if ($s->fetch()) {
            $err = 'Ин email аллакай сабт шудааст';
        } else {
            $pdo->prepare('INSERT INTO users(name,email,password) VALUES(?,?,?)')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $_SESSION['user'] = ['id' => $pdo->lastInsertId(), 'name' => $name];
            go('projects.php');
        }
    }
}
$title = 'Сабти ном';
include 'header.php';
?>
<div class="card narrow">
  <h2>Сабти ном</h2>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <label>Ном <input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
    <label>Email <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Парол <input type="password" name="password" required minlength="6"></label>
    <button class="btn">Сабт шудан</button>
  </form>
  <p>Ҳисоб доред? <a href="login.php">Ворид шудан</a></p>
</div>
<?php include 'footer.php'; ?>
