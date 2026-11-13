
<?php
require_once 'config.php';
need_login();

$uid = user()['id'];
$tid = (int)($_GET['id'] ?? 0);

function task_text($tg, $ru) {
    global $current_language;
    return $current_language === 'ru' ? $ru : $tg;
}

function task_status_text($status) {
    $labels = [
        'todo' => task_text('Нақша гирифта шудааст', 'К запланированным'),
        'in_progress' => task_text('Дар иҷро', 'В работе'),
        'review' => task_text('Санҷиш', 'На проверке'),
        'done' => task_text('Иҷро шуд', 'Выполнено')
    ];

    return $labels[$status] ?? $status;
}

function task_priority_text($priority) {
    $labels = [
        1 => task_text('Паст', 'Низкий'),
        2 => task_text('Миёна', 'Средний'),
        3 => task_text('Баланд', 'Высокий'),
        4 => task_text('Фаврӣ', 'Критический')
    ];

    return $labels[$priority] ?? $priority;
}

$s = $pdo->prepare('SELECT t.*, u.name AS an FROM tasks t LEFT JOIN users u ON u.id=t.assignee_id WHERE t.id=?');
$s->execute([$tid]);
$task = $s->fetch();

if (!$task) {
    die(task_text('Вазифа ёфт нашуд', 'Задача не найдена'));
}

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
            $pdo->prepare('INSERT INTO comments(task_id,user_id,body) VALUES(?,?,?)')
                ->execute([$tid, $uid, $b]);

            flash('ok', task_text('Шарҳ илова шуд', 'Комментарий добавлен'));
        }
    }
    elseif ($a === 'edit' && $canEdit) {
        $t = trim($_POST['title'] ?? '');
        $pr = (int)($_POST['priority'] ?? 2);
        $pt = (int)($_POST['points'] ?? 1);

        if ($t === '' || !isset(PRIO[$pr]) || !in_array($pt, POINTS)) {
            flash('err', task_text('Маълумот нодуруст аст', 'Некорректные данные'));
        }
        elseif ($sprint && sprint_used($pdo, $sprint['id']) - $task['points'] + $pt > $sprint['capacity']) {
            flash('err', task_text(
                'Бо ин хол иқтидори спринт аз ҳад мегузарад',
                'С этими Story Points будет превышена вместимость спринта'
            ));
        }
        else {
            $pdo->prepare('UPDATE tasks SET title=?, description=?, priority=?, points=? WHERE id=?')
                ->execute([$t, trim($_POST['description'] ?? ''), $pr, $pt, $tid]);

            flash('ok', task_text('Нигоҳ дошта шуд', 'Изменения сохранены'));
        }
    }
    elseif ($a === 'take' && $role === 'member' && $active && !$task['assignee_id']) {
        $pdo->prepare('UPDATE tasks SET assignee_id=? WHERE id=?')->execute([$uid, $tid]);

        flash('ok', task_text('Шумо вазифаро гирифтед', 'Вы взяли задачу'));
    }
    elseif ($a === 'status' && $active && ($isAssignee || $role === 'scrum_master')) {
        $st = $_POST['status'] ?? '';

        if (isset(STATUS[$st])) {
            if ($st !== 'todo' && !$task['assignee_id']) {
                flash('err', task_text(
                    'Аввал вазифа бояд гирифта шавад',
                    'Сначала необходимо взять задачу'
                ));
            }
            else {
                $pdo->prepare('UPDATE tasks SET status=?, done_at=' . ($st === 'done' ? 'NOW()' : 'NULL') . ' WHERE id=?')
                    ->execute([$st, $tid]);

                flash('ok', task_text('Ҳолат иваз шуд', 'Статус изменён'));
            }
        }
    }
    elseif ($a === 'log_time' && $active && $isAssignee) {
        $m = (int)($_POST['minutes'] ?? 0);

        if ($m < 1 || $m > 1440) {
            flash('err', task_text(
                'Вақт аз 1 то 1440 дақиқа бошад',
                'Время должно быть от 1 до 1440 минут'
            ));
        }
        else {
            $pdo->prepare('UPDATE tasks SET spent_minutes = spent_minutes + ? WHERE id=?')
                ->execute([$m, $tid]);

            flash('ok', task_text('Вақт қайд шуд', 'Время записано'));
        }
    }
    else {
        flash('err', task_text(
            'Амал дар ин ҳолат имконнопазир аст',
            'Это действие сейчас недоступно'
        ));
    }

    go("task.php?id=$tid");
}

$cm = $pdo->prepare('SELECT c.*, u.name FROM comments c JOIN users u ON u.id=c.user_id WHERE c.task_id=? ORDER BY c.id');
$cm->execute([$tid]);
$comments = $cm->fetchAll();

$title = $task['title'];
include 'header.php';
?>

<p>
    <a href="project.php?id=<?= $pid ?>">
        ← <?= task_text('Ба лоиҳа', 'К проекту') ?>
    </a>

    <?php if ($sprint): ?>
        ·
        <a href="sprint.php?id=<?= $sprint['id'] ?>">
            <?= task_text('Спринт', 'Спринт') ?>: <?= e($sprint['name']) ?>
        </a>
    <?php endif; ?>
</p>

<div class="card">
    <h1><?= e($task['title']) ?></h1>

    <p><?= nl2br(e($task['description'])) ?></p>

    <p>
        <span class="badge p-<?= $task['priority'] ?>">
            <?= e(task_priority_text($task['priority'])) ?>
        </span>

        <span class="badge">
            <?= (int)$task['points'] ?> <?= task_text('хол', 'SP') ?>
        </span>

        <span class="badge">
            <?= e(task_status_text($task['status'])) ?>
        </span>
    </p>

    <p>
        <?= task_text('Иҷрокунанда', 'Исполнитель') ?>:
        <b><?= $task['an'] ? e($task['an']) : '—' ?></b>

        ·

        <?= task_text('Вақти сарфшуда', 'Затраченное время') ?>:
        <b>
            <?= floor($task['spent_minutes'] / 60) ?>
            <?= task_text('соат', 'ч.') ?>

            <?= $task['spent_minutes'] % 60 ?>
            <?= task_text('дақ.', 'мин.') ?>
        </b>
    </p>

    <?php if ($task['done_at']): ?>
        <p>
            <?= task_text('Иҷро шуд', 'Выполнено') ?>:
            <?= e($task['done_at']) ?>
        </p>
    <?php endif; ?>

    <?php if (!$sprint): ?>
        <p>
            <small>
                <?= task_text(
                    'Вазифа дар backlog аст, ҳолат ва вақтро танҳо дар спринти ҷорӣ иваз кардан мумкин аст.',
                    'Задача находится в бэклоге. Изменять статус и учитывать время можно только в активном спринте.'
                ) ?>
            </small>
        </p>
    <?php endif; ?>

    <?php if ($role === 'member' && $active && !$task['assignee_id']): ?>
        <form method="post">
            <input type="hidden" name="action" value="take">

            <button class="btn">
                <?= task_text('Гирифтани вазифа', 'Взять задачу') ?>
            </button>
        </form>
    <?php endif; ?>

    <?php if ($active && ($isAssignee || $role === 'scrum_master')): ?>
        <form method="post" class="row">
            <input type="hidden" name="action" value="status">

            <label>
                <?= task_text('Ҳолат', 'Статус') ?>

                <select name="status">
                    <?php foreach (STATUS as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $task['status'] == $k ? 'selected' : '' ?>>
                            <?= e(task_status_text($k)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="btn">
                <?= task_text('Иваз кардан', 'Изменить') ?>
            </button>
        </form>
    <?php endif; ?>

    <?php if ($active && $isAssignee): ?>
        <form method="post" class="row">
            <input type="hidden" name="action" value="log_time">

            <label>
                <?= task_text('Вақти сарфшуда (дақиқа)', 'Затраченное время (минуты)') ?>

                <input type="number" name="minutes" min="1" max="1440" required>
            </label>

            <button class="btn">
                <?= task_text('Қайд кардан', 'Записать') ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
    <div class="card">
        <h2><?= task_text('Таҳрир', 'Редактирование') ?></h2>

        <form method="post">
            <input type="hidden" name="action" value="edit">

            <label>
                <?= task_text('Сарлавҳа', 'Название') ?>

                <input name="title" value="<?= e($task['title']) ?>" required>
            </label>

            <label>
                <?= task_text('Тавсиф', 'Описание') ?>

                <textarea name="description" rows="3"><?= e($task['description']) ?></textarea>
            </label>

            <div class="row">
                <label>
                    <?= task_text('Афзалият', 'Приоритет') ?>

                    <select name="priority">
                        <?php foreach (PRIO as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $task['priority'] == $k ? 'selected' : '' ?>>
                                <?= e(task_priority_text($k)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <?= task_text('Холҳо', 'Story Points') ?>

                    <select name="points">
                        <?php foreach (POINTS as $p): ?>
                            <option value="<?= $p ?>" <?= $task['points'] == $p ? 'selected' : '' ?>>
                                <?= $p ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <button class="btn">
                <?= task_text('Нигоҳ доштан', 'Сохранить') ?>
            </button>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <h2>
        <?= task_text('Шарҳҳо', 'Комментарии') ?>
        (<?= count($comments) ?>)
    </h2>

    <?php foreach ($comments as $c): ?>
        <div class="comment">
            <b><?= e($c['name']) ?></b>

            <small><?= e($c['created_at']) ?></small>

            <p><?= nl2br(e($c['body'])) ?></p>
        </div>
    <?php endforeach; ?>

    <form method="post">
        <input type="hidden" name="action" value="comment">

        <label>
            <?= task_text('Шарҳи нав', 'Новый комментарий') ?>

            <textarea name="body" rows="3" required></textarea>
        </label>

        <button class="btn">
            <?= task_text('Фиристодан', 'Отправить') ?>
        </button>
    </form>
</div>

<?php include 'footer.php'; ?>
