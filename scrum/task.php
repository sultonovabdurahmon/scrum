<?php
require_once 'config.php';
need_login();
$uid = user()['id'];
$tid = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare('SELECT t.*, u.name AS an FROM tasks t LEFT JOIN users u ON u.id=t.assignee_id WHERE t.id=?');
$s->execute([$tid]);
$task = $s->fetch();
if (!$task) die('Вазифа ёфт нашуд');
$pid = (int)$task['project_id'];
$role = need_member($pdo, $pid);

$sprint = null;
if ($task['sprint_id']) {
    $q = $pdo->prepare('SELECT * FROM sprints WHERE id=?');
    $q->execute([$task['sprint_id']]);
    $sprint = $q->fetch();
}
$active = $sprint && $sprint['status'] === 'active';
$locked = $sprint && $sprint['status'] === 'closed';
$isAssignee = $task['assignee_id'] == $uid;
$canEdit = in_array($role, ['product_owner', 'scrum_master']) && !$locked;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';

    if ($a === 'comment') {
        $b = trim($_POST['body'] ?? '');
        if ($b !== '') {
            $pdo->prepare('INSERT INTO comments(task_id,user_id,body) VALUES(?,?,?)')->execute([$tid, $uid, $b]);
            flash('ok', 'Шарҳ илова шуд');
        }
    }
    elseif ($a === 'edit' && $canEdit) {
        $t = trim($_POST['title'] ?? '');
        $pr = (int)($_POST['priority'] ?? 2);
        $pt = (int)($_POST['points'] ?? 1);
        if ($t === '' || !isset(PRIO[$pr]) || !in_array($pt, POINTS)) flash('err', 'Маълумот нодуруст аст');
        elseif ($sprint && sprint_used($pdo, $sprint['id']) - $task['points'] + $pt > $sprint['capacity'])
            flash('err', 'Бо ин хол иқтидори спринт аз ҳад мегузарад');
        else {
            $pdo->prepare('UPDATE tasks SET title=?, description=?, priority=?, points=? WHERE id=?')
                ->execute([$t, trim($_POST['description'] ?? ''), $pr, $pt, $tid]);
            flash('ok', 'Нигоҳ дошта шуд');
        }
    }
    elseif ($a === 'take' && $role === 'member' && $active && !$task['assignee_id']) {
        $pdo->prepare('UPDATE tasks SET assignee_id=? WHERE id=?')->execute([$uid, $tid]);
        flash('ok', 'Шумо вазифаро гирифтед');
    }
    elseif ($a === 'status' && $active && ($isAssignee || $role === 'scrum_master')) {
        $st = $_POST['status'] ?? '';
        if (isset(STATUS[$st])) {
            if ($st !== 'todo' && !$task['assignee_id']) flash('err', 'Аввал вазифа бояд гирифта шавад');
            else {
                $pdo->prepare('UPDATE tasks SET status=?, done_at=' . ($st === 'done' ? 'NOW()' : 'NULL') . ' WHERE id=?')->execute([$st, $tid]);
                flash('ok', 'Ҳолат иваз шуд');
            }
        }
    }
    elseif ($a === 'log_time' && $active && $isAssignee) {
        $m = (int)($_POST['minutes'] ?? 0);
        if ($m < 1 || $m > 1440) flash('err', 'Вақт аз 1 то 1440 дақиқа бошад');
        else {
            $pdo->prepare('UPDATE tasks SET spent_minutes = spent_minutes + ? WHERE id=?')->execute([$m, $tid]);
            flash('ok', 'Вақт қайд шуд');
        }
    }
    else flash('err', 'Амал дар ин ҳолат имконнопазир аст');

    go("task.php?id=$tid");
}

$cm = $pdo->prepare('SELECT c.*, u.name FROM comments c JOIN users u ON u.id=c.user_id WHERE c.task_id=? ORDER BY c.id');
$cm->execute([$tid]);
$comments = $cm->fetchAll();

$title = $task['title'];
include 'header.php';
?>
<p>
  <a href="project.php?id=<?= $pid ?>">← Ба лоиҳа</a>
  <?php if ($sprint): ?> · <a href="sprint.php?id=<?= $sprint['id'] ?>">Спринт: <?= e($sprint['name']) ?></a><?php endif; ?>
</p>

<div class="card">
  <h1><?= e($task['title']) ?></h1>
  <p><?= nl2br(e($task['description'])) ?></p>
  <p>
    <span class="badge p-<?= $task['priority'] ?>"><?= e(PRIO[$task['priority']]) ?></span>
    <span class="badge"><?= (int)$task['points'] ?> хол</span>
    <span class="badge"><?= e(STATUS[$task['status']]) ?></span>
  </p>
  <p>Иҷрокунанда: <b><?= $task['an'] ? e($task['an']) : '—' ?></b> · Вақти сарфшуда: <b><?= floor($task['spent_minutes'] / 60) ?> соат <?= $task['spent_minutes'] % 60 ?> дақ.</b></p>
  <?php if ($task['done_at']): ?><p>Иҷро шуд: <?= e($task['done_at']) ?></p><?php endif; ?>
  <?php if (!$sprint): ?><p><small>Вазифа дар backlog аст, ҳолат ва вақтро танҳо дар спринти ҷорӣ иваз кардан мумкин аст.</small></p><?php endif; ?>

  <?php if ($role === 'member' && $active && !$task['assignee_id']): ?>
    <form method="post"><input type="hidden" name="action" value="take"><button class="btn">Гирифтани вазифа</button></form>
  <?php endif; ?>

  <?php if ($active && ($isAssignee || $role === 'scrum_master')): ?>
    <form method="post" class="row">
      <input type="hidden" name="action" value="status">
      <label>Ҳолат
        <select name="status"><?php foreach (STATUS as $k => $v): ?><option value="<?= $k ?>" <?= $task['status'] == $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <button class="btn">Иваз кардан</button>
    </form>
  <?php endif; ?>

  <?php if ($active && $isAssignee): ?>
    <form method="post" class="row">
      <input type="hidden" name="action" value="log_time">
      <label>Вақти сарфшуда (дақиқа) <input type="number" name="minutes" min="1" max="1440" required></label>
      <button class="btn">Қайд кардан</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<div class="card">
  <h2>Таҳрир</h2>
  <form method="post">
    <input type="hidden" name="action" value="edit">
    <label>Сарлавҳа <input name="title" value="<?= e($task['title']) ?>" required></label>
    <label>Тавсиф <textarea name="description" rows="3"><?= e($task['description']) ?></textarea></label>
    <div class="row">
      <label>Афзалият
        <select name="priority"><?php foreach (PRIO as $k => $v): ?><option value="<?= $k ?>" <?= $task['priority'] == $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label>Холҳо
        <select name="points"><?php foreach (POINTS as $p): ?><option <?= $task['points'] == $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select>
      </label>
    </div>
    <button class="btn">Нигоҳ доштан</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Шарҳҳо (<?= count($comments) ?>)</h2>
  <?php foreach ($comments as $c): ?>
    <div class="comment"><b><?= e($c['name']) ?></b> <small><?= e($c['created_at']) ?></small><p><?= nl2br(e($c['body'])) ?></p></div>
  <?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="action" value="comment">
    <label>Шарҳи нав <textarea name="body" rows="3" required></textarea></label>
    <button class="btn">Фиристодан</button>
  </form>
</div>
<?php include 'footer.php'; ?>
