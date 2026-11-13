
<?php
require_once 'config.php';
need_login();

$uid = user()['id'];

/* =========================================
   TRANSLATIONS
   ========================================= */

function projects_text($tg, $ru) {
    global $current_language;
    return $current_language === 'ru' ? $ru : $tg;
}

$role_names = [
    'product_owner' => projects_text('Соҳиби маҳсулот', 'Владелец продукта'),
    'scrum_master'  => 'Scrum-master',
    'member'        => projects_text('Аъзои даста', 'Участник команды'),
];

/* =========================================
   CREATE PROJECT
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? 'scrum_master';

    if ($name === '' || !isset(ROLES[$role])) {

        flash('err', projects_text(
            'Номи лоиҳаро ворид кунед',
            'Введите название проекта'
        ));

    } else {

        $pdo->prepare(
            'INSERT INTO projects(name,description,owner_id) VALUES(?,?,?)'
        )->execute([
            $name,
            trim($_POST['description'] ?? ''),
            $uid
        ]);

        $pid = $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)'
        )->execute([
            $pid,
            $uid,
            $role
        ]);

        flash('ok', projects_text(
            'Лоиҳа сохта шуд',
            'Проект успешно создан'
        ));

        go("project.php?id=$pid");
    }

    go('projects.php');
}

/* =========================================
   LOAD PROJECTS
   ========================================= */

$s = $pdo->prepare(
    'SELECT p.*, pm.role,
        (SELECT COUNT(*) FROM project_members WHERE project_id=p.id) AS members,
        (SELECT COUNT(*) FROM tasks WHERE project_id=p.id) AS tasks
    FROM projects p
    JOIN project_members pm
        ON pm.project_id=p.id AND pm.user_id=?
    ORDER BY p.id DESC'
);

$s->execute([$uid]);
$projects = $s->fetchAll();

/* =========================================
   PAGE
   ========================================= */

$title = projects_text('Лоиҳаҳои ман', 'Мои проекты');

include 'header.php';
?>

<!-- =====================================
     PAGE HEADER
     ===================================== -->

<h1>
    <?= projects_text('Лоиҳаҳои ман', 'Мои проекты') ?>
</h1>

<!-- =====================================
     PROJECT CARDS
     ===================================== -->

<div class="grid">

    <?php foreach ($projects as $p): ?>

        <a class="card proj" href="project.php?id=<?= (int)$p['id'] ?>">

            <h3><?= e($p['name']) ?></h3>

            <p>
                <?= e($p['description']) ?>
            </p>

            <small>
                <?= projects_text('Нақши шумо:', 'Ваша роль:') ?>

                <b>
                    <?= e($role_names[$p['role']] ?? $p['role']) ?>
                </b>

                ·

                <?= projects_text('Аъзоён:', 'Участники:') ?>
                <?= (int)$p['members'] ?>

                ·

                <?= projects_text('Вазифаҳо:', 'Задачи:') ?>
                <?= (int)$p['tasks'] ?>
            </small>

        </a>

    <?php endforeach; ?>

    <?php if (!$projects): ?>

        <p>
            <?= projects_text(
                'Ҳоло лоиҳа нест. Якумро созед.',
                'Пока нет проектов. Создайте первый проект.'
            ) ?>
        </p>

    <?php endif; ?>

</div>

<!-- =====================================
     CREATE NEW PROJECT
     ===================================== -->

<div class="card">

    <h2>
        <?= projects_text('Лоиҳаи нав', 'Новый проект') ?>
    </h2>

    <form method="post" class="row">

        <label>
            <?= projects_text('Ном', 'Название') ?>

            <input
                type="text"
                name="name"
                required
                placeholder="<?= projects_text('Номи лоиҳа', 'Название проекта') ?>"
            >
        </label>

        <label>
            <?= projects_text('Тавсиф', 'Описание') ?>

            <input
                type="text"
                name="description"
                placeholder="<?= projects_text('Тавсифи лоиҳа', 'Описание проекта') ?>"
            >
        </label>

        <label>
            <?= projects_text('Нақши ман', 'Моя роль') ?>

            <select name="role">

                <?php foreach ($role_names as $k => $v): ?>

                    <option
                        value="<?= e($k) ?>"
                        <?= $k === 'scrum_master' ? 'selected' : '' ?>
                    >
                        <?= e($v) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </label>

        <button class="btn">
            <?= projects_text('Сохтан', 'Создать') ?>
        </button>

    </form>

</div>

<?php include 'footer.php'; ?>
