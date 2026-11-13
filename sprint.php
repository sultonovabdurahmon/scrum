
<?php
require_once 'config.php';
need_login();

$sid = (int)($_GET['id'] ?? 0);

/* =========================================
   TRANSLATIONS
   ========================================= */

function sprint_text($tg, $ru) {
    global $current_language;
    return $current_language === 'ru' ? $ru : $tg;
}

$sprint_status_names = [
    'planned' => sprint_text('Банақшагирифташуда', 'Запланирован'),
    'active'  => sprint_text('Ҷорӣ', 'Активный'),
    'closed'  => sprint_text('Анҷомёфта', 'Завершён'),
];

$status_names = [
    'todo'        => sprint_text('Барои иҷро', 'К выполнению'),
    'in_progress' => sprint_text('Дар иҷро', 'В работе'),
    'review'      => sprint_text('Дар санҷиш', 'На проверке'),
    'done'        => sprint_text('Иҷрошуда', 'Выполнено'),
];

$priority_names = [
    1 => sprint_text('Паст', 'Низкий'),
    2 => sprint_text('Миёна', 'Средний'),
    3 => sprint_text('Баланд', 'Высокий'),
    4 => sprint_text('Ҳатмӣ', 'Критический'),
];

/* =========================================
   LOAD SPRINT
   ========================================= */

$s = $pdo->prepare('SELECT * FROM sprints WHERE id=?');
$s->execute([$sid]);
$sprint = $s->fetch();

if (!$sprint) {
    die(sprint_text('Спринт ёфт нашуд', 'Спринт не найден'));
}

$pid = (int)$sprint['project_id'];
$role = need_member($pdo, $pid);
$isSM = $role === 'scrum_master';

/* =========================================
   BURNDOWN CHART
   ========================================= */

function burndown_svg($labels, $ideal, $actual, $total) {

    $W = 680;
    $H = 320;
    $L = 50;
    $R = 20;
    $T = 20;
    $B = 50;

    $n = count($labels);
    $max = max($total, 1);

    $X = function ($i) use ($n, $W, $L, $R) {
        return $L + ($W - $L - $R) * $i / max($n - 1, 1);
    };

    $Y = function ($v) use ($max, $H, $T, $B) {
        return $T + ($H - $T - $B) * (1 - $v / $max);
    };

    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="chart" xmlns="http://www.w3.org/2000/svg">';

    for ($k = 0; $k <= 5; $k++) {

        $v = $max * $k / 5;
        $y = round($Y($v), 1);

        $svg .= '<line x1="' . $L . '" y1="' . $y . '" x2="' . ($W - $R) . '" y2="' . $y . '" stroke="#929bb0"/>';

        $svg .= '<text x="' . ($L - 8) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="11" fill="#929bb0">' . round($v, 1) . '</text>';
    }

    $step = max(1, (int)ceil($n / 12));

    for ($i = 0; $i < $n; $i += $step) {

        $svg .= '<text x="' . round($X($i), 1) . '" y="' . ($H - $B + 18) . '" text-anchor="middle" font-size="11" fill="#929bb0">' . e($labels[$i]) . '</text>';
    }

    $pi = [];

    foreach ($ideal as $i => $v) {
        $pi[] = round($X($i), 1) . ',' . round($Y($v), 1);
    }

    $svg .= '<polyline fill="none" stroke="#929bb0" stroke-width="2" stroke-dasharray="6 4" points="' . implode(' ', $pi) . '"/>';

    $pa = [];
    $dots = '';

    foreach ($actual as $i => $v) {

        if ($v === null) {
            continue;
        }

        $x = round($X($i), 1);
        $y = round($Y($v), 1);

        $pa[] = $x . ',' . $y;

        $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="3.5" fill="#8b5cf6"/>';
    }

    if ($pa) {
        $svg .= '<polyline fill="none" stroke="#8b5cf6" stroke-width="2.5" points="' . implode(' ', $pa) . '"/>' . $dots;
    }

    $ideal_label = sprint_text('Идеалӣ', 'Идеальный план');
    $actual_label = sprint_text('Воқеӣ (холҳои боқимонда)', 'Фактически (оставшиеся очки)');

    $svg .= '<text x="' . ($L + 10) . '" y="' . ($H - 10) . '" font-size="12" fill="#929bb0">--- ' . e($ideal_label) . '</text>';

    $svg .= '<text x="' . ($L + 190) . '" y="' . ($H - 10) . '" font-size="12" fill="#8b5cf6">— ' . e($actual_label) . '</text>';

    return $svg . '</svg>';
}

/* =========================================
   POST ACTIONS
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $a = $_POST['action'] ?? '';

    if (!$isSM) {

        flash('err', sprint_text(
            'Танҳо Scrum-master метавонад спринтро идора кунад',
            'Только Scrum-мастер может управлять спринтом'
        ));

    }

    // Добавление задачи в спринт
    elseif ($a === 'add_task' && $sprint['status'] !== 'closed') {

        $q = $pdo->prepare(
            'SELECT * FROM tasks WHERE id=? AND project_id=? AND sprint_id IS NULL'
        );

        $q->execute([
            (int)($_POST['task_id'] ?? 0),
            $pid
        ]);

        $task = $q->fetch();

        if (!$task) {

            flash('err', sprint_text(
                'Вазифа ёфт нашуд',
                'Задача не найдена'
            ));

        } else {

            $used = sprint_used($pdo, $sid);

            if ($used + $task['points'] > $sprint['capacity']) {

                flash('err', sprint_text(
                    "Иқтидор намерасад: дар спринт $used аз {$sprint['capacity']} хол банд аст, вазифа {$task['points']} хол мегирад",
                    "Недостаточно вместимости: занято $used из {$sprint['capacity']} очков, задача требует {$task['points']} очков"
                ));

            } else {

                $pdo->prepare(
                    'UPDATE tasks SET sprint_id=?, status="todo", assignee_id=NULL, done_at=NULL WHERE id=?'
                )->execute([
                    $sid,
                    $task['id']
                ]);

                flash('ok', sprint_text(
                    'Вазифа ба спринт илова шуд',
                    'Задача добавлена в спринт'
                ));
            }
        }
    }

    // Возврат задачи в Backlog
    elseif ($a === 'remove_task' && $sprint['status'] === 'planned') {

        $pdo->prepare(
            'UPDATE tasks SET sprint_id=NULL WHERE id=? AND sprint_id=?'
        )->execute([
            (int)$_POST['task_id'],
            $sid
        ]);

        flash('ok', sprint_text(
            'Вазифа ба backlog баргашт',
            'Задача возвращена в Backlog'
        ));
    }

    // Изменение вместимости
    elseif ($a === 'set_capacity' && $sprint['status'] === 'planned') {

        $cap = (int)($_POST['capacity'] ?? 0);

        if ($cap < sprint_used($pdo, $sid)) {

            flash('err', sprint_text(
                'Иқтидор аз холҳои дар спринт бандшуда камтар шуда наметавонад',
                'Вместимость не может быть меньше уже занятых очков'
            ));

        } else {

            $pdo->prepare(
                'UPDATE sprints SET capacity=? WHERE id=?'
            )->execute([
                $cap,
                $sid
            ]);

            flash('ok', sprint_text(
                'Иқтидор нав шуд',
                'Вместимость обновлена'
            ));
        }
    }

    // Запуск спринта
    elseif ($a === 'start' && $sprint['status'] === 'planned') {

        $c = $pdo->prepare(
            'SELECT COUNT(*) FROM sprints WHERE project_id=? AND status="active"'
        );

        $c->execute([$pid]);

        if ($c->fetchColumn() > 0) {

            flash('err', sprint_text(
                'Дар лоиҳа аллакай спринти ҷорӣ ҳаст',
                'В проекте уже есть активный спринт'
            ));

        } elseif (sprint_used($pdo, $sid) == 0) {

            flash('err', sprint_text(
                'Аввал ба спринт вазифа илова кунед',
                'Сначала добавьте задачи в спринт'
            ));

        } else {

            $pdo->prepare(
                'UPDATE sprints SET status="active" WHERE id=?'
            )->execute([$sid]);

            flash('ok', sprint_text(
                'Спринт оғоз шуд',
                'Спринт запущен'
            ));
        }
    }

    // Завершение спринта
    elseif ($a === 'finish' && $sprint['status'] === 'active') {

        $total = sprint_used($pdo, $sid);

        $pdo->prepare(
            'UPDATE sprints SET status="closed", final_points=? WHERE id=?'
        )->execute([
            $total,
            $sid
        ]);

        // Незавершённые задачи возвращаются в Backlog
        $pdo->prepare(
            'UPDATE tasks SET sprint_id=NULL, status="todo", assignee_id=NULL, done_at=NULL WHERE sprint_id=? AND status<>"done"'
        )->execute([$sid]);

        flash('ok', sprint_text(
            'Спринт анҷом ёфт; вазифаҳои иҷронашуда ба backlog баргаштанд',
            'Спринт завершён; невыполненные задачи возвращены в Backlog'
        ));
    }

    else {

        flash('err', sprint_text(
            'Амал дар ин ҳолат имконнопазир аст',
            'Это действие сейчас невозможно'
        ));
    }

    go("sprint.php?id=$sid");
}

/* =========================================
   LOAD TASKS
   ========================================= */

$tasksQ = $pdo->prepare(
    'SELECT t.*, u.name AS an
     FROM tasks t
     LEFT JOIN users u ON u.id=t.assignee_id
     WHERE t.sprint_id=?
     ORDER BY t.priority DESC, t.id'
);

$tasksQ->execute([$sid]);
$tasks = $tasksQ->fetchAll();

$used = array_sum(array_column($tasks, 'points'));
$free = $sprint['capacity'] - $used;

/* =========================================
   LOAD BACKLOG
   ========================================= */

$bl = $pdo->prepare(
    'SELECT * FROM tasks
     WHERE project_id=? AND sprint_id IS NULL
     ORDER BY priority DESC, id'
);

$bl->execute([$pid]);
$backlog = $bl->fetchAll();

/* =========================================
   BURNDOWN DATA
   ========================================= */

$showChart = $sprint['status'] !== 'planned';

if ($showChart) {

    $total = $sprint['status'] === 'closed'
        ? (int)$sprint['final_points']
        : $used;

    $start = new DateTime($sprint['start_date']);
    $end = new DateTime($sprint['end_date']);

    $n = min(max($start->diff($end)->days + 1, 2), 60);

    $today = date('Y-m-d');

    $labels = [];
    $ideal = [];
    $actual = [];

    for ($i = 0; $i < $n; $i++) {

        $d = (clone $start)->modify("+$i day");
        $ds = $d->format('Y-m-d');

        $labels[] = $d->format('d.m');

        $ideal[] = round($total * (1 - $i / ($n - 1)), 2);

        if ($sprint['status'] === 'closed' || $ds <= $today) {

            $doneSum = 0;

            foreach ($tasks as $task) {

                if (
                    $task['status'] === 'done' &&
                    $task['done_at'] &&
                    substr($task['done_at'], 0, 10) <= $ds
                ) {
                    $doneSum += $task['points'];
                }
            }

            $actual[] = $total - $doneSum;

        } else {

            $actual[] = null;
        }
    }
}

/* =========================================
   PAGE
   ========================================= */

$title = $sprint['name'];
include 'header.php';
?>

<p>
    <a href="project.php?id=<?= $pid ?>">
        ← <?= sprint_text('Ба лоиҳа', 'К проекту') ?>
    </a>
</p>

<h1>
    <?= e($sprint['name']) ?>

    <span class="badge s-<?= e($sprint['status']) ?>">
        <?= e($sprint_status_names[$sprint['status']] ?? $sprint['status']) ?>
    </span>
</h1>

<p>
    <?= e($sprint['goal']) ?>
    <br>
    <?= e($sprint['start_date']) ?>
    —
    <?= e($sprint['end_date']) ?>
</p>

<!-- =====================================
     TEAM CAPACITY
     ===================================== -->

<div class="card">

    <h2><?= sprint_text('Иқтидори даста', 'Вместимость команды') ?></h2>

    <div class="bar">
        <div style="width:<?= $sprint['capacity'] > 0 ? min(100, round($used / $sprint['capacity'] * 100)) : 0 ?>%"></div>
    </div>

    <p>
        <b><?= $used ?></b>
        <?= sprint_text('аз', 'из') ?>
        <b><?= (int)$sprint['capacity'] ?></b>
        <?= sprint_text('хол банд аст', 'очков занято') ?>

        ·

        <?= sprint_text('озод:', 'свободно:') ?>
        <b><?= max($free, 0) ?></b>
    </p>

    <?php if ($isSM && $sprint['status'] !== 'closed'): ?>

        <div class="row">

            <?php if ($sprint['status'] === 'planned'): ?>

                <form method="post" class="row">

                    <input type="hidden" name="action" value="set_capacity">

                    <label>
                        <?= sprint_text('Иқтидор', 'Вместимость') ?>

                        <input
                            type="number"
                            name="capacity"
                            min="<?= $used ?>"
                            value="<?= (int)$sprint['capacity'] ?>"
                        >
                    </label>

                    <button class="btn small">
                        <?= sprint_text('Нав кардан', 'Обновить') ?>
                    </button>

                </form>

                <form method="post">

                    <input type="hidden" name="action" value="start">

                    <button class="btn">
                        <?= sprint_text('Оғози спринт', 'Запустить спринт') ?>
                    </button>

                </form>

            <?php else: ?>

                <form
                    method="post"
                    onsubmit="return confirm('<?= sprint_text('Спринт анҷом дода шавад?', 'Завершить спринт?') ?>')"
                >

                    <input type="hidden" name="action" value="finish">

                    <button class="btn danger">
                        <?= sprint_text('Анҷоми спринт', 'Завершить спринт') ?>
                    </button>

                </form>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<!-- =====================================
     ADD TASKS FROM BACKLOG
     ===================================== -->

<?php if ($isSM && $sprint['status'] !== 'closed'): ?>

<div class="card">

    <h2><?= sprint_text('Илова аз backlog', 'Добавить из Backlog') ?></h2>

    <?php if ($backlog): ?>

        <form method="post" class="row">

            <input type="hidden" name="action" value="add_task">

            <label>
                <?= sprint_text('Вазифа', 'Задача') ?>

                <select name="task_id">

                    <?php foreach ($backlog as $task): ?>

                        <?php $fit = $task['points'] <= $free; ?>

                        <option
                            value="<?= (int)$task['id'] ?>"
                            <?= $fit ? '' : 'disabled' ?>
                        >
                            [<?= e($priority_names[$task['priority']] ?? '') ?>]
                            <?= e($task['title']) ?>
                            — <?= (int)$task['points'] ?>
                            <?= sprint_text('хол', 'очков') ?>
                            <?= $fit ? '' : sprint_text('(намеғунҷад)', '(не помещается)') ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </label>

            <button class="btn">
                <?= sprint_text('Илова ба спринт', 'Добавить в спринт') ?>
            </button>

        </form>

    <?php else: ?>

        <p><?= sprint_text('Backlog холӣ аст.', 'Backlog пуст.') ?></p>

    <?php endif; ?>

</div>

<?php endif; ?>

<!-- =====================================
     SPRINT BOARD
     ===================================== -->

<div class="card">

    <h2><?= sprint_text('Тахтаи спринт', 'Доска спринта') ?></h2>

    <div class="board">

        <?php foreach ($status_names as $k => $label): ?>

            <div class="col">

                <h3><?= e($label) ?></h3>

                <?php foreach ($tasks as $task): ?>

                    <?php if ($task['status'] !== $k) continue; ?>

                    <div class="task p-b<?= (int)$task['priority'] ?>">

                        <a href="task.php?id=<?= (int)$task['id'] ?>">
                            <?= e($task['title']) ?>
                        </a>

                        <small>
                            <?= (int)$task['points'] ?>
                            <?= sprint_text('хол', 'очков') ?>

                            ·

                            <?= $task['an']
                                ? e($task['an'])
                                : sprint_text('бе иҷрокунанда', 'без исполнителя') ?>

                            ·

                            <?= round($task['spent_minutes'] / 60, 1) ?>
                            <?= sprint_text('соат', 'часов') ?>
                        </small>

                        <?php if ($isSM && $sprint['status'] === 'planned'): ?>

                            <form method="post">

                                <input type="hidden" name="action" value="remove_task">

                                <input
                                    type="hidden"
                                    name="task_id"
                                    value="<?= (int)$task['id'] ?>"
                                >

                                <button class="btn small danger">
                                    <?= sprint_text('Баровардан', 'Убрать') ?>
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<!-- =====================================
     BURNDOWN CHART
     ===================================== -->

<div class="card">

    <h2>
        <?= sprint_text(
            'Диаграммаи боқимондаи кор (Burndown)',
            'Диаграмма оставшейся работы (Burndown)'
        ) ?>
    </h2>

    <?php if ($showChart): ?>

        <?= burndown_svg($labels, $ideal, $actual, $total) ?>

    <?php else: ?>

        <p>
            <?= sprint_text(
                'Диаграмма баъд аз оғози спринт пайдо мешавад.',
                'Диаграмма появится после запуска спринта.'
            ) ?>
        </p>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
