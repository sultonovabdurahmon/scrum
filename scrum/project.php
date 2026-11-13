<?php
require_once 'config.php';
need_login();
$uid = user()['id'];
$pid = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare('SELECT * FROM projects WHERE id=?');
$s->execute([$pid]);
$project = $s->fetch();
if (!$project) die('Лоиҳа ёфт нашуд');
$role = need_member($pdo, $pid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';

    // Илова кардани аъзо (танҳо созандаи лоиҳа)
    if ($a === 'add_member' && $project['owner_id'] == $uid) {
        $r = $_POST['role'] ?? '';
        $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $s->execute([trim($_POST['email'] ?? '')]);
        $mid = $s->fetchColumn();
        if (!$mid) flash('err', 'Корбар бо ин email ёфт нашуд');
        elseif (!isset(ROLES[$r])) flash('err', 'Нақши нодуруст');
        else {
            try {
                $pdo->prepare('INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)')->execute([$pid, $mid, $r]);
                flash('ok', 'Аъзо илова шуд');
            } catch (PDOException $ex) { flash('err', 'Ин корбар аллакай аъзо аст'); }
        }
    }

    // Backlog: танҳо Соҳиби маҳсулот
    elseif ($a === 'add_task' && $role === 'product_owner') {
        $t = trim($_POST['title'] ?? '');
        $pr = (int)($_POST['priority'] ?? 2);
        $pt = (int)($_POST['points'] ?? 1);
        if ($t === '' || !isset(PRIO[$pr]) || !in_array($pt, POINTS)) flash('err', 'Маълумот нодуруст аст');
        else {
            $pdo->prepare('INSERT INTO tasks(project_id,title,description,priority,points,created_by) VALUES(?,?,?,?,?,?)')
                ->execute([$pid, $t, trim($_POST['description'] ?? ''), $pr, $pt, $uid]);
            flash('ok', 'Вазифа ба backlog илова шуд');
        }
    }
    elseif ($a === 'prio' && $role === 'product_owner') {
        $pr = (int)($_POST['priority'] ?? 2);
        if (isset(PRIO[$pr]))
            $pdo->prepare('UPDATE tasks SET priority=? WHERE id=? AND project_id=?')->execute([$pr, (int)$_POST['task_id'], $pid]);
    }
    elseif ($a === 'delete_task' && $role === 'product_owner') {
        $pdo->prepare('DELETE FROM tasks WHERE id=? AND project_id=? AND sprint_id IS NULL')->execute([(int)$_POST['task_id'], $pid]);
        flash('ok', 'Вазифа нест карда шуд');
    }

    // Спринт: танҳо Scrum-master
    elseif ($a === 'create_sprint' && $role === 'scrum_master') {
        $n = trim($_POST['name'] ?? '');
        $sd = $_POST['start_date'] ?? '';
        $ed = $_POST['end_date'] ?? '';
        $cap = (int)($_POST['capacity'] ?? 0);
        if ($n === '' || !$sd || !$ed || $ed <= $sd || $cap < 1) flash('err', 'Санаи анҷом бояд баъд аз оғоз бошад ва иқтидор > 0');
        else {
            $pdo->prepare('INSERT INTO sprints(project_id,name,goal,start_date,end_date,capacity) VALUES(?,?,?,?,?,?)')
                ->execute([$pid, $n, trim($_POST['goal'] ?? ''), $sd, $ed, $cap]);
            flash('ok', 'Спринт сохта шуд');
        }
    }
    else flash('err', 'Шумо иҷозати ин амалро надоред');

    go("project.php?id=$pid");
}

$members = $pdo->prepare('SELECT u.name,u.email,pm.role FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY pm.id');
$members->execute([$pid]);
$members = $members->fetchAll();

$backlog = $pdo->prepare('SELECT * FROM tasks WHERE project_id=? AND sprint_id IS NULL ORDER BY priority DESC, id');
$backlog->execute([$pid]);
$backlog = $backlog->fetchAll();

$sprints = $pdo->prepare('SELECT * FROM sprints WHERE project_id=? ORDER BY start_date DESC, id DESC');
$sprints->execute([$pid]);
$sprints = $sprints->fetchAll();

$title = $project['name'];
include 'header.php';
?>
<h1><?= e($project['name']) ?></h1>
<p><?= e($project['description']) ?> · Нақши шумо: <b><?= e(ROLES[$role]) ?></b></p>

<div class="card">
  <h2>Спринтҳо</h2>
  <table>
    <tr><th>Ном</th><th>Сана</th><th>Ҳолат</th><th>Холҳо / Иқтидор</th><th></th></tr>
    <?php foreach ($sprints as $sp): ?>
      <tr>
        <td><?= e($sp['name']) ?><br><small><?= e($sp['goal']) ?></small></td>
        <td><?= e($sp['start_date']) ?> — <?= e($sp['end_date']) ?></td>
        <td><span class="badge s-<?= $sp['status'] ?>"><?= e(SPRINT_STATUS[$sp['status']]) ?></span></td>
        <td><?= sprint_used($pdo, $sp['id']) ?> / <?= (int)$sp['capacity'] ?></td>
        <td><a class="btn small" href="sprint.php?id=<?= $sp['id'] ?>">Кушодан</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$sprints): ?><tr><td colspan="5">Спринт нест</td></tr><?php endif; ?>
  </table>

  <?php if ($role === 'scrum_master'): ?>
  <h3>Спринти нав</h3>
  <form method="post" class="row">
    <input type="hidden" name="action" value="create_sprint">
    <label>Ном <input name="name" required placeholder="Спринт 1"></label>
    <label>Мақсад <input name="goal"></label>
    <label>Оғоз <input type="date" name="start_date" required></label>
    <label>Анҷом <input type="date" name="end_date" required></label>
    <label>Иқтидори даста (холҳо) <input type="number" name="capacity" min="1" required></label>
    <button class="btn">Сохтан</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Backlog (рӯйхати вазифаҳо)</h2>
  <table>
    <tr><th>Афзалият</th><th>Вазифа</th><th>Холҳо</th><th></th></tr>
    <?php foreach ($backlog as $t): ?>
      <tr>
        <td>
          <?php if ($role === 'product_owner'): ?>
            <form method="post">
              <input type="hidden" name="action" value="prio">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <select name="priority" onchange="this.form.submit()">
                <?php foreach (PRIO as $k => $v): ?><option value="<?= $k ?>" <?= $t['priority'] == $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
              </select>
            </form>
          <?php else: ?>
            <span class="badge p-<?= $t['priority'] ?>"><?= e(PRIO[$t['priority']]) ?></span>
          <?php endif; ?>
        </td>
        <td><a href="task.php?id=<?= $t['id'] ?>"><?= e($t['title']) ?></a></td>
        <td><?= (int)$t['points'] ?></td>
        <td>
          <?php if ($role === 'product_owner'): ?>
            <form method="post" onsubmit="return confirm('Нест карда шавад?')">
              <input type="hidden" name="action" value="delete_task">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <button class="btn small danger">Нест кардан</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$backlog): ?><tr><td colspan="4">Backlog холӣ аст</td></tr><?php endif; ?>
  </table>

  <?php if ($role === 'product_owner'): ?>
  <h3>Вазифаи нав</h3>
  <form method="post" class="row">
    <input type="hidden" name="action" value="add_task">
    <label>Сарлавҳа <input name="title" required></label>
    <label>Тавсиф <input name="description"></label>
    <label>Афзалият
      <select name="priority"><?php foreach (PRIO as $k => $v): ?><option value="<?= $k ?>" <?= $k == 2 ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    </label>
    <label>Холҳо (story points)
      <select name="points"><?php foreach (POINTS as $p): ?><option><?= $p ?></option><?php endforeach; ?></select>
    </label>
    <button class="btn">Илова</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Аъзои даста</h2>
  <table>
    <tr><th>Ном</th><th>Email</th><th>Нақш</th></tr>
    <?php foreach ($members as $m): ?>
      <tr><td><?= e($m['name']) ?></td><td><?= e($m['email']) ?></td><td><?= e(ROLES[$m['role']]) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($project['owner_id'] == $uid): ?>
  <h3>Иловаи аъзо</h3>
  <form method="post" class="row">
    <input type="hidden" name="action" value="add_member">
    <label>Email-и корбар (бояд сабт шуда бошад) <input type="email" name="email" required></label>
    <label>Нақш
      <select name="role"><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $k == 'member' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    </label>
    <button class="btn">Илова</button>
  </form>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
