<?php
require_once 'config.php';
need_login();
$uid = user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? 'scrum_master';
    if ($name === '' || !isset(ROLES[$role])) {
        flash('err', 'Номи лоиҳаро ворид кунед');
    } else {
        $pdo->prepare('INSERT INTO projects(name,description,owner_id) VALUES(?,?,?)')
            ->execute([$name, trim($_POST['description'] ?? ''), $uid]);
        $pid = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)')
            ->execute([$pid, $uid, $role]);
        flash('ok', 'Лоиҳа сохта шуд');
        go("project.php?id=$pid");
    }
    go('projects.php');
}

$s = $pdo->prepare('SELECT p.*, pm.role,
    (SELECT COUNT(*) FROM project_members WHERE project_id=p.id) AS members,
    (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS tasks
  FROM projects p JOIN project_members pm ON pm.project_id=p.id AND pm.user_id=?
  ORDER BY p.id DESC');
$s->execute([$uid]);
$projects = $s->fetchAll();

$title = 'Лоиҳаҳо';
include 'header.php';
?>
<h1>Лоиҳаҳои ман</h1>
<div class="grid">
  <?php foreach ($projects as $p): ?>
    <a class="card proj" href="project.php?id=<?= $p['id'] ?>">
      <h3><?= e($p['name']) ?></h3>
      <p><?= e($p['description']) ?></p>
      <small>Нақши шумо: <b><?= e(ROLES[$p['role']]) ?></b> · Аъзоён: <?= $p['members'] ?> · Вазифаҳо: <?= $p['tasks'] ?></small>
    </a>
  <?php endforeach; ?>
  <?php if (!$projects): ?><p>Ҳоло лоиҳа нест. Якумро созед.</p><?php endif; ?>
</div>

<div class="card">
  <h2>Лоиҳаи нав</h2>
  <form method="post" class="row">
    <label>Ном <input name="name" required></label>
    <label>Тавсиф <input name="description"></label>
    <label>Нақши ман
      <select name="role">
        <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
    </label>
    <button class="btn">Сохтан</button>
  </form>
</div>
<?php include 'footer.php'; ?>
