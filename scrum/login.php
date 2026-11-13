<?php
require_once 'config.php';
if (user()) go('projects.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = $pdo->prepare('SELECT * FROM users WHERE email=?');
    $s->execute([trim($_POST['email'] ?? '')]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name']];
        go('projects.php');
    }
    $err = 'Email ё парол нодуруст аст';
}
$title = 'Воридшавӣ';
include 'header.php';
?>
<div class="card narrow">
  <h2>Воридшавӣ</h2>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <label>Email <input type="email" name="email" required></label>
    <label>Парол <input type="password" name="password" required></label>
    <button class="btn">Ворид шудан</button>
  </form>
  <p>Ҳисоб надоред? <a href="register.php">Сабти ном</a></p>
</div>
<?php include 'footer.php'; ?>
