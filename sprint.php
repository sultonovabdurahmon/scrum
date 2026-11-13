<?php
require_once 'config.php';
need_login();
$sid = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare('SELECT * FROM sprints WHERE id=?');
$s->execute([$sid]);
$sprint = $s->fetch();
if (!$sprint) die('Спринт ёфт нашуд');
$pid = (int)$sprint['project_id'];
$role = need_member($pdo, $pid);
$isSM = $role === 'scrum_master';

function burndown_svg($labels, $ideal, $actual, $total) {
    $W = 680; $H = 320; $L = 50; $R = 20; $T = 20; $B = 50;
    $n = count($labels);
    $max = max($total, 1);
    $X = function ($i) use ($n, $W, $L, $R) { return $L + ($W - $L - $R) * $i / max($n - 1, 1); };
    $Y = function ($v) use ($max, $H, $T, $B) { return $T + ($H - $T - $B) * (1 - $v / $max); };
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="chart" xmlns="http://www.w3.org/2000/svg">';
    for ($k = 0; $k <= 5; $k++) {
        $v = $max * $k / 5;
        $y = round($Y($v), 1);
        $svg .= '<line x1="' . $L . '" y1="' . $y . '" x2="' . ($W - $R) . '" y2="' . $y . '" stroke="#e5e7eb"/>';
        $svg .= '<text x="' . ($L - 8) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="11" fill="#6b7280">' . round($v, 1) . '</text>';
    }
    $step = max(1, (int)ceil($n / 12));
    for ($i = 0; $i < $n; $i += $step) {
        $svg .= '<text x="' . round($X($i), 1) . '" y="' . ($H - $B + 18) . '" text-anchor="middle" font-size="11" fill="#6b7280">' . e($labels[$i]) . '</text>';
    }
    $pi = [];
    foreach ($ideal as $i => $v) $pi[] = round($X($i), 1) . ',' . round($Y($v), 1);
    $svg .= '<polyline fill="none" stroke="#9ca3af" stroke-width="2" stroke-dasharray="6 4" points="' . implode(' ', $pi) . '"/>';
    $pa = []; $dots = '';
    foreach ($actual as $i => $v) {
        if ($v === null) continue;
        $x = round($X($i), 1); $y = round($Y($v), 1);
        $pa[] = $x . ',' . $y;
        $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="3.5" fill="#2563eb"/>';
    }
    if ($pa) $svg .= '<polyline fill="none" stroke="#2563eb" stroke-width="2.5" points="' . implode(' ', $pa) . '"/>' . $dots;
    $svg .= '<text x="' . ($L + 10) . '" y="' . ($H - 10) . '" font-size="12" fill="#9ca3af">--- Ideal</text>';
    $svg .= '<text x="' . ($L + 90) . '" y="' . ($H - 10) . '" font-size="12" fill="#2563eb">— Воқеӣ (холҳои боқимонда)</text>';
    return $svg . '</svg>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if (!$isSM) {
        flash('err', 'Танҳо Scrum-master метавонад спринтро идора кунад');
    }

    // Илова кардани вазифа ба спринт — бо санҷиши иқтидор
    elseif ($a === 'add_task' && $sprint['status'] !== 'closed') {
        $q = $pdo->prepare('SELECT * FROM tasks WHERE id=? AND project_id=? AND sprint_id IS NULL');
        $q->execute([(int)($_POST['task_id'] ?? 0), $pid]);
        $t = $q->fetch();
        if (!$t) flash('err', 'Вазифа ёфт нашуд');
        else {
            $used = sprint_used($pdo, $sid);
            if ($used + $t['points'] > $sprint['capacity'])
                flash('err', "Иқтидор намерасад: дар спринт $used аз {$sprint['capacity']} хол банд аст, вазифа {$t['points']} хол мегирад");
            else {
                $pdo->prepare('UPDATE tasks SET sprint_id=?, status="todo", assignee_id=NULL, done_at=NULL WHERE id=?')->execute([$sid, $t['id']]);
                flash('ok', 'Вазифа ба спринт илова шуд');
            }
        }
    }
    elseif ($a === 'remove_task' && $sprint['status'] === 'planned') {
        $pdo->prepare('UPDATE tasks SET sprint_id=NULL WHERE id=? AND sprint_id=?')->execute([(int)$_POST['task_id'], $sid]);
        flash('ok', 'Вазифа ба backlog баргашт');
    }
    elseif ($a === 'set_capacity' && $sprint['status'] === 'planned') {
        $cap = (int)($_POST['capacity'] ?? 0);
        if ($cap < sprint_used($pdo, $sid)) flash('err', 'Иқтидор аз холҳои дар спринт бандшуда камтар шуда наметавонад');
        else {
            $pdo->prepare('UPDATE sprints SET capacity=? WHERE id=?')->execute([$cap, $sid]);
            flash('ok', 'Иқтидор нав шуд');
        }
    }
    elseif ($a === 'start' && $sprint['status'] === 'planned') {
        $c = $pdo->prepare('SELECT COUNT(*) FROM sprints WHERE project_id=? AND status="active"');
        $c->execute([$pid]);
        if ($c->fetchColumn() > 0) flash('err', 'Дар лоиҳа аллакай спринти ҷорӣ ҳаст');
        elseif (sprint_used($pdo, $sid) == 0) flash('err', 'Аввал ба спринт вазифа илова кунед');
        else {
            $pdo->prepare('UPDATE sprints SET status="active" WHERE id=?')->execute([$sid]);
            flash('ok', 'Спринт оғоз шуд');
        }
    }
    elseif ($a === 'finish' && $sprint['status'] === 'active') {
        $total = sprint_used($pdo, $sid);
        $pdo->prepare('UPDATE sprints SET status="closed", final_points=? WHERE id=?')->execute([$total, $sid]);
        // вазифаҳои иҷронашуда ба backlog бармегарданд
        $pdo->prepare('UPDATE tasks SET sprint_id=NULL, status="todo", assignee_id=NULL, done_at=NULL WHERE sprint_id=? AND status<>"done"')->execute([$sid]);
        flash('ok', 'Спринт анҷом ёфт; вазифаҳои иҷронашуда ба backlog баргаштанд');
    }
    else flash('err', 'Амал дар ин ҳолат имконнопазир аст');

    go("sprint.php?id=$sid");
}

$tasksQ = $pdo->prepare('SELECT t.*, u.name AS an FROM tasks t LEFT JOIN users u ON u.id=t.assignee_id WHERE t.sprint_id=? ORDER BY t.priority DESC, t.id');
$tasksQ->execute([$sid]);
$tasks = $tasksQ->fetchAll();

$used = array_sum(array_column($tasks, 'points'));
$free = $sprint['capacity'] - $used;

$bl = $pdo->prepare('SELECT * FROM tasks WHERE project_id=? AND sprint_id IS NULL ORDER BY priority DESC, id');
$bl->execute([$pid]);
$backlog = $bl->fetchAll();

// Burndown
$showChart = $sprint['status'] !== 'planned';
if ($showChart) {
    $total = $sprint['status'] === 'closed' ? (int)$sprint['final_points'] : $used;
    $start = new DateTime($sprint['start_date']);
    $end = new DateTime($sprint['end_date']);
    $n = min(max($start->diff($end)->days + 1, 2), 60);
    $today = date('Y-m-d');
    $labels = []; $ideal = []; $actual = [];
    for ($i = 0; $i < $n; $i++) {
        $d = (clone $start)->modify("+$i day");
        $ds = $d->format('Y-m-d');
        $labels[] = $d->format('d.m');
        $ideal[] = round($total * (1 - $i / ($n - 1)), 2);
        if ($sprint['status'] === 'closed' || $ds <= $today) {
            $doneSum = 0;
            foreach ($tasks as $t)
                if ($t['status'] === 'done' && $t['done_at'] && substr($t['done_at'], 0, 10) <= $ds) $doneSum += $t['points'];
            $actual[] = $total - $doneSum;
        } else $actual[] = null;
    }
}

$title = $sprint['name'];
include 'header.php';
?>
<p><a href="project.php?id=<?= $pid ?>">← Ба лоиҳа</a></p>
<h1><?= e($sprint['name']) ?> <span class="badge s-<?= $sprint['status'] ?>"><?= e(SPRINT_STATUS[$sprint['status']]) ?></span></h1>
<p><?= e($sprint['goal']) ?><br>
   <?= e($sprint['start_date']) ?> — <?= e($sprint['end_date']) ?></p>

<div class="card">
  <h2>Иқтидори даста</h2>
  <div class="bar"><div style="width:<?= $sprint['capacity'] > 0 ? min(100, round($used / $sprint['capacity'] * 100)) : 0 ?>%"></div></div>
  <p><b><?= $used ?></b> аз <b><?= (int)$sprint['capacity'] ?></b> хол банд аст · озод: <b><?= max($free, 0) ?></b></p>

  <?php if ($isSM && $sprint['status'] !== 'closed'): ?>
    <div class="row">
      <?php if ($sprint['status'] === 'planned'): ?>
        <form method="post" class="row">
          <input type="hidden" name="action" value="set_capacity">
          <label>Иқтидор <input type="number" name="capacity" min="1" value="<?= (int)$sprint['capacity'] ?>"></label>
          <button class="btn small">Нав кардан</button>
        </form>
        <form method="post"><input type="hidden" name="action" value="start"><button class="btn">Оғози спринт</button></form>
      <?php else: ?>
        <form method="post" onsubmit="return confirm('Спринт анҷом дода шавад?')"><input type="hidden" name="action" value="finish"><button class="btn danger">Анҷоми спринт</button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($isSM && $sprint['status'] !== 'closed'): ?>
<div class="card">
  <h2>Илова аз backlog</h2>
  <?php if ($backlog): ?>
  <form method="post" class="row">
    <input type="hidden" name="action" value="add_task">
    <label>Вазифа
      <select name="task_id">
        <?php foreach ($backlog as $t): $fit = $t['points'] <= $free; ?>
          <option value="<?= $t['id'] ?>" <?= $fit ? '' : 'disabled' ?>>[<?= e(PRIO[$t['priority']]) ?>] <?= e($t['title']) ?> — <?= (int)$t['points'] ?> хол<?= $fit ? '' : ' (намеғунҷад)' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn">Илова ба спринт</button>
  </form>
  <?php else: ?><p>Backlog холӣ аст.</p><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Тахтаи спринт</h2>
  <div class="board">
    <?php foreach (STATUS as $k => $label): ?>
      <div class="col">
        <h3><?= e($label) ?></h3>
        <?php foreach ($tasks as $t): if ($t['status'] !== $k) continue; ?>
          <div class="task p-b<?= $t['priority'] ?>">
            <a href="task.php?id=<?= $t['id'] ?>"><?= e($t['title']) ?></a>
            <small><?= (int)$t['points'] ?> хол · <?= $t['an'] ? e($t['an']) : 'бе иҷрокунанда' ?> · <?= round($t['spent_minutes'] / 60, 1) ?> соат</small>
            <?php if ($isSM && $sprint['status'] === 'planned'): ?>
              <form method="post"><input type="hidden" name="action" value="remove_task"><input type="hidden" name="task_id" value="<?= $t['id'] ?>"><button class="btn small danger">Баровардан</button></form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h2>Диаграммаи боқимондаи кор (Burndown)</h2>
  <?php if ($showChart): echo burndown_svg($labels, $ideal, $actual, $total); else: ?>
    <p>Диаграмма баъд аз оғози спринт пайдо мешавад.</p>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
