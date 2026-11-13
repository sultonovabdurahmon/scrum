
<?php
require_once 'config.php';
need_login();

$uid = user()['id'];
$pid = (int)($_GET['id'] ?? 0);

/* =========================================
   PAGE TRANSLATIONS
   ========================================= */

function project_text($tg, $ru) {
    global $current_language;
    return $current_language === 'ru' ? $ru : $tg;
}

$role_names = [
    'product_owner' => project_text('Соҳиби маҳсулот', 'Владелец продукта'),
    'scrum_master'  => project_text('Scrum-master', 'Scrum-мастер'),
    'member'        => project_text('Аъзои даста', 'Участник команды'),
];

$priority_names = [
    1 => project_text('Паст', 'Низкий'),
    2 => project_text('Миёна', 'Средний'),
    3 => project_text('Баланд', 'Высокий'),
    4 => project_text('Ҳатмӣ', 'Критический'),
];

$sprint_status_names = [
    'planned' => project_text('Банақшагирифташуда', 'Запланирован'),
    'active'  => project_text('Ҷорӣ', 'Активный'),
    'closed'  => project_text('Анҷомёфта', 'Завершён'),
];

/* =========================================
   LOAD PROJECT
   ========================================= */

$s = $pdo->prepare('SELECT * FROM projects WHERE id=?');
$s->execute([$pid]);
$project = $s->fetch();

if (!$project) {
    die(project_text('Лоиҳа ёфт нашуд', 'Проект не найден'));
}

$role = need_member($pdo, $pid);

/* =========================================
   POST ACTIONS
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $a = $_POST['action'] ?? '';

    // Добавление участника
    if ($a === 'add_member' && $project['owner_id'] == $uid) {

        $r = $_POST['role'] ?? '';

        $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $s->execute([trim($_POST['email'] ?? '')]);

        $mid = $s->fetchColumn();

        if (!$mid) {
            flash('err', project_text(
                'Корбар бо ин email ёфт нашуд',
                'Пользователь с таким email не найден'
            ));
        } elseif (!isset(ROLES[$r])) {
            flash('err', project_text(
                'Нақши нодуруст',
                'Неверная роль'
            ));
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)'
                )->execute([$pid, $mid, $r]);

                flash('ok', project_text(
                    'Аъзо илова шуд',
                    'Участник добавлен'
                ));

            } catch (PDOException $ex) {
                flash('err', project_text(
                    'Ин корбар аллакай аъзо аст',
                    'Этот пользователь уже состоит в проекте'
                ));
            }
        }
    }

    // Добавление задачи в Backlog
    elseif ($a === 'add_task' && $role === 'product_owner') {

        $task_title = trim($_POST['title'] ?? '');
        $pr = (int)($_POST['priority'] ?? 2);
        $pt = (int)($_POST['points'] ?? 1);

        if (
            $task_title === '' ||
            !isset(PRIO[$pr]) ||
            !in_array($pt, POINTS)
        ) {
            flash('err', project_text(
                'Маълумот нодуруст аст',
                'Некорректные данные'
            ));
        } else {

            $pdo->prepare(
                'INSERT INTO tasks(project_id,title,description,priority,points,created_by)
                 VALUES(?,?,?,?,?,?)'
            )->execute([
                $pid,
                $task_title,
                trim($_POST['description'] ?? ''),
                $pr,
                $pt,
                $uid
            ]);

            flash('ok', project_text(
                'Вазифа ба backlog илова шуд',
                'Задача добавлена в Backlog'
            ));
        }
    }

    // Изменение приоритета
    elseif ($a === 'prio' && $role === 'product_owner') {

        $pr = (int)($_POST['priority'] ?? 2);

        if (isset(PRIO[$pr])) {
            $pdo->prepare(
                'UPDATE tasks SET priority=? WHERE id=? AND project_id=?'
            )->execute([
                $pr,
                (int)$_POST['task_id'],
                $pid
            ]);
        }
    }

    // Удаление задачи
    elseif ($a === 'delete_task' && $role === 'product_owner') {

        $pdo->prepare(
            'DELETE FROM tasks WHERE id=? AND project_id=? AND sprint_id IS NULL'
        )->execute([
            (int)$_POST['task_id'],
            $pid
        ]);

        flash('ok', project_text(
            'Вазифа нест карда шуд',
            'Задача удалена'
        ));
    }

    // Создание спринта
    elseif ($a === 'create_sprint' && $role === 'scrum_master') {

        $n = trim($_POST['name'] ?? '');
        $sd = $_POST['start_date'] ?? '';
        $ed = $_POST['end_date'] ?? '';
        $cap = (int)($_POST['capacity'] ?? 0);

        if ($n === '' || !$sd || !$ed || $ed <= $sd || $cap < 1) {

            flash('err', project_text(
                'Санаи анҷом бояд баъд аз оғоз бошад ва иқтидор > 0',
                'Дата окончания должна быть позже даты начала, а вместимость должна быть больше 0'
            ));

        } else {

            $pdo->prepare(
                'INSERT INTO sprints(project_id,name,goal,start_date,end_date,capacity)
                 VALUES(?,?,?,?,?,?)'
            )->execute([
                $pid,
                $n,
                trim($_POST['goal'] ?? ''),
                $sd,
                $ed,
                $cap
            ]);

            flash('ok', project_text(
                'Спринт сохта шуд',
                'Спринт создан'
            ));
        }
    }

    else {
        flash('err', project_text(
            'Шумо иҷозати ин амалро надоред',
            'У вас нет разрешения на это действие'
        ));
    }

    go("project.php?id=$pid");
}

/* =========================================
   LOAD MEMBERS
   ========================================= */

$members = $pdo->prepare(
    'SELECT u.name,u.email,pm.role
     FROM project_members pm
     JOIN users u ON u.id=pm.user_id
     WHERE pm.project_id=?
     ORDER BY pm.id'
);

$members->execute([$pid]);
$members = $members->fetchAll();

/* =========================================
   LOAD BACKLOG
   ========================================= */

$backlog = $pdo->prepare(
    'SELECT * FROM tasks
     WHERE project_id=? AND sprint_id IS NULL
     ORDER BY priority DESC, id'
);

$backlog->execute([$pid]);
$backlog = $backlog->fetchAll();

/* =========================================
   LOAD SPRINTS
   ========================================= */

$sprints = $pdo->prepare(
    'SELECT * FROM sprints
     WHERE project_id=?
     ORDER BY start_date DESC, id DESC'
);

$sprints->execute([$pid]);
$sprints = $sprints->fetchAll();

$title = $project['name'];

include 'header.php';
?>

<!-- =====================================
     PROJECT HEADER
     ===================================== -->

<h1><?= e($project['name']) ?></h1>

<p>
    <?= e($project['description']) ?>
    ·
    <?= project_text('Нақши шумо:', 'Ваша роль:') ?>
    <b><?= e($role_names[$role] ?? $role) ?></b>
</p>

<!-- =====================================
     SPRINTS
     ===================================== -->

<div class="card">

    <h2><?= project_text('Спринтҳо', 'Спринты') ?></h2>

    <table>
        <tr>
            <th><?= project_text('Ном', 'Название') ?></th>
            <th><?= project_text('Сана', 'Даты') ?></th>
            <th><?= project_text('Ҳолат', 'Статус') ?></th>
            <th><?= project_text('Холҳо / Иқтидор', 'Очки / Вместимость') ?></th>
            <th></th>
        </tr>

        <?php foreach ($sprints as $sp): ?>
            <tr>

                <td>
                    <?= e($sp['name']) ?>
                    <br>
                    <small><?= e($sp['goal']) ?></small>
                </td>

                <td>
                    <?= e($sp['start_date']) ?>
                    —
                    <?= e($sp['end_date']) ?>
                </td>

                <td>
                    <span class="badge s-<?= e($sp['status']) ?>">
                        <?= e($sprint_status_names[$sp['status']] ?? $sp['status']) ?>
                    </span>
                </td>

                <td>
                    <?= sprint_used($pdo, $sp['id']) ?>
                    /
                    <?= (int)$sp['capacity'] ?>
                </td>

                <td>
                    <a class="btn small" href="sprint.php?id=<?= (int)$sp['id'] ?>">
                        <?= project_text('Кушодан', 'Открыть') ?>
                    </a>
                </td>

            </tr>
        <?php endforeach; ?>

        <?php if (!$sprints): ?>
            <tr>
                <td colspan="5">
                    <?= project_text('Спринт нест', 'Спринтов пока нет') ?>
                </td>
            </tr>
        <?php endif; ?>

    </table>

    <?php if ($role === 'scrum_master'): ?>

        <h3><?= project_text('Спринти нав', 'Новый спринт') ?></h3>

        <form method="post" class="row">

            <input type="hidden" name="action" value="create_sprint">

            <label>
                <?= project_text('Ном', 'Название') ?>
                <input
                    name="name"
                    required
                    placeholder="<?= project_text('Спринт 1', 'Спринт 1') ?>"
                >
            </label>

            <label>
                <?= project_text('Мақсад', 'Цель') ?>
                <input name="goal">
            </label>

            <label>
                <?= project_text('Оғоз', 'Начало') ?>
                <input type="date" name="start_date" required>
            </label>

            <label>
                <?= project_text('Анҷом', 'Окончание') ?>
                <input type="date" name="end_date" required>
            </label>

            <label>
                <?= project_text('Иқтидори даста (холҳо)', 'Вместимость команды (очки)') ?>
                <input type="number" name="capacity" min="1" required>
            </label>

            <button class="btn">
                <?= project_text('Сохтан', 'Создать') ?>
            </button>

        </form>

    <?php endif; ?>

</div>

<!-- =====================================
     BACKLOG
     ===================================== -->

<div class="card">

    <h2>Backlog (<?= project_text('рӯйхати вазифаҳо', 'список задач') ?>)</h2>

    <table>

        <tr>
            <th><?= project_text('Афзалият', 'Приоритет') ?></th>
            <th><?= project_text('Вазифа', 'Задача') ?></th>
            <th><?= project_text('Холҳо', 'Очки') ?></th>
            <th></th>
        </tr>

        <?php foreach ($backlog as $task): ?>

            <tr>

                <td>

                    <?php if ($role === 'product_owner'): ?>

                        <form method="post">

                            <input type="hidden" name="action" value="prio">

                            <input
                                type="hidden"
                                name="task_id"
                                value="<?= (int)$task['id'] ?>"
                            >

                            <select name="priority" onchange="this.form.submit()">

                                <?php foreach ($priority_names as $k => $v): ?>

                                    <option
                                        value="<?= $k ?>"
                                        <?= $task['priority'] == $k ? 'selected' : '' ?>
                                    >
                                        <?= e($v) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </form>

                    <?php else: ?>

                        <span class="badge p-<?= (int)$task['priority'] ?>">
                            <?= e($priority_names[$task['priority']] ?? '') ?>
                        </span>

                    <?php endif; ?>

                </td>

                <td>
                    <a href="task.php?id=<?= (int)$task['id'] ?>">
                        <?= e($task['title']) ?>
                    </a>
                </td>

                <td><?= (int)$task['points'] ?></td>

                <td>

                    <?php if ($role === 'product_owner'): ?>

                        <form
                            method="post"
                            onsubmit="return confirm('<?= project_text('Нест карда шавад?', 'Удалить задачу?') ?>')"
                        >

                            <input type="hidden" name="action" value="delete_task">

                            <input
                                type="hidden"
                                name="task_id"
                                value="<?= (int)$task['id'] ?>"
                            >

                            <button class="btn small danger">
                                <?= project_text('Нест кардан', 'Удалить') ?>
                            </button>

                        </form>

                    <?php endif; ?>

                </td>

            </tr>

        <?php endforeach; ?>

        <?php if (!$backlog): ?>

            <tr>
                <td colspan="4">
                    <?= project_text('Backlog холӣ аст', 'Backlog пуст') ?>
                </td>
            </tr>

        <?php endif; ?>

    </table>

    <?php if ($role === 'product_owner'): ?>

        <h3><?= project_text('Вазифаи нав', 'Новая задача') ?></h3>

        <form method="post" class="row">

            <input type="hidden" name="action" value="add_task">

            <label>
                <?= project_text('Сарлавҳа', 'Название') ?>
                <input name="title" required>
            </label>

            <label>
                <?= project_text('Тавсиф', 'Описание') ?>
                <input name="description">
            </label>

            <label>
                <?= project_text('Афзалият', 'Приоритет') ?>

                <select name="priority">

                    <?php foreach ($priority_names as $k => $v): ?>

                        <option
                            value="<?= $k ?>"
                            <?= $k == 2 ? 'selected' : '' ?>
                        >
                            <?= e($v) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </label>

            <label>
                <?= project_text('Холҳо (story points)', 'Очки (Story Points)') ?>

                <select name="points">

                    <?php foreach (POINTS as $p): ?>
                        <option value="<?= $p ?>"><?= $p ?></option>
                    <?php endforeach; ?>

                </select>
            </label>

            <button class="btn">
                <?= project_text('Илова', 'Добавить') ?>
            </button>

        </form>

    <?php endif; ?>

</div>

<!-- =====================================
     TEAM MEMBERS
     ===================================== -->

<div class="card">

    <h2><?= project_text('Аъзои даста', 'Участники команды') ?></h2>

    <table>

        <tr>
            <th><?= project_text('Ном', 'Имя') ?></th>
            <th>Email</th>
            <th><?= project_text('Нақш', 'Роль') ?></th>
        </tr>

        <?php foreach ($members as $m): ?>

            <tr>

                <td><?= e($m['name']) ?></td>

                <td><?= e($m['email']) ?></td>

                <td>
                    <?= e($role_names[$m['role']] ?? $m['role']) ?>
                </td>

            </tr>

        <?php endforeach; ?>

    </table>

    <?php if ($project['owner_id'] == $uid): ?>

        <h3><?= project_text('Иловаи аъзо', 'Добавить участника') ?></h3>

        <form method="post" class="row">

            <input type="hidden" name="action" value="add_member">

            <label>
                <?= project_text(
                    'Email-и корбар (бояд сабт шуда бошад)',
                    'Email пользователя (он должен быть зарегистрирован)'
                ) ?>

                <input type="email" name="email" required>
            </label>

            <label>
                <?= project_text('Нақш', 'Роль') ?>

                <select name="role">

                    <?php foreach ($role_names as $k => $v): ?>

                        <option
                            value="<?= e($k) ?>"
                            <?= $k === 'member' ? 'selected' : '' ?>
                        >
                            <?= e($v) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </label>

            <button class="btn">
                <?= project_text('Илова', 'Добавить') ?>
            </button>

        </form>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
