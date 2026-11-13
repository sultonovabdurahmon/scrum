
<?php
session_start();

/* =========================================
   DATABASE CONNECTION
   ========================================= */

$pdo = new PDO(
    'mysql:host=localhost;dbname=scrum;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/* =========================================
   LANGUAGE SETTINGS
   ========================================= */

$supported_languages = ['tg', 'ru'];

// Смена языка через GET-параметр
if (isset($_GET['lang'])) {
    $requested_language = $_GET['lang'];

    if (in_array($requested_language, $supported_languages, true)) {
        $_SESSION['lang'] = $requested_language;
    }

    // Возвращаемся на предыдущую страницу без параметра lang
    $return_url = $_SERVER['HTTP_REFERER'] ?? 'projects.php';

    $return_url = preg_replace('/([?&])lang=(tg|ru)(&|$)/', '$1', $return_url);
    $return_url = rtrim($return_url, '?&');

    header('Location: ' . $return_url);
    exit;
}

// Язык по умолчанию
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'tg';
}

$current_language = $_SESSION['lang'];

/* =========================================
   TRANSLATIONS
   ========================================= */

$translations = [

    'tg' => [

        // General
        'app_name' => 'ASTAT SCRUM',
        'dashboard' => 'Панели идоракунӣ',
        'projects' => 'Лоиҳаҳо',
        'tasks' => 'Вазифаҳо',
        'backlog' => 'Бэклог',
        'sprints' => 'Спринтҳо',
        'team' => 'Даста',
        'settings' => 'Танзимот',
        'profile' => 'Профил',
        'logout' => 'Баромад',
        'login' => 'Воридшавӣ',
        'register' => 'Сабти ном',
        'language' => 'Забон',
        'save' => 'Сабт кардан',
        'cancel' => 'Бекор кардан',
        'delete' => 'Нест кардан',
        'edit' => 'Таҳрир кардан',
        'create' => 'Эҷод кардан',
        'add' => 'Илова кардан',
        'search' => 'Ҷустуҷӯ',
        'name' => 'Ном',
        'description' => 'Тавсиф',
        'status' => 'Ҳолат',
        'priority' => 'Афзалият',
        'date' => 'Сана',
        'actions' => 'Амалҳо',
        'details' => 'Маълумот',
        'no_data' => 'Маълумот нест',
        'welcome' => 'Хуш омадед',

        // Projects
        'my_projects' => 'Лоиҳаҳои ман',
        'new_project' => 'Лоиҳаи нав',
        'create_project' => 'Эҷоди лоиҳа',
        'project_name' => 'Номи лоиҳа',
        'project_description' => 'Тавсифи лоиҳа',
        'project_owner' => 'Соҳиби маҳсулот',
        'no_projects' => 'Ҳоло лоиҳа нест. Якумро созед.',
        'project_created' => 'Лоиҳа бомуваффақият сохта шуд',

        // Tasks
        'new_task' => 'Вазифаи нав',
        'task_name' => 'Номи вазифа',
        'task_description' => 'Тавсифи вазифа',
        'task_status' => 'Ҳолати вазифа',
        'task_priority' => 'Афзалияти вазифа',
        'story_points' => 'Нуқтаҳои Story',
        'assignee' => 'Иҷрокунанда',
        'comments' => 'Шарҳҳо',
        'add_comment' => 'Илова кардани шарҳ',

        // Task statuses
        'todo' => 'Барои иҷро',
        'in_progress' => 'Дар иҷро',
        'review' => 'Дар санҷиш',
        'done' => 'Иҷрошуда',

        // Priorities
        'priority_low' => 'Паст',
        'priority_medium' => 'Миёна',
        'priority_high' => 'Баланд',
        'priority_critical' => 'Ҳатмӣ',

        // Roles
        'role_product_owner' => 'Соҳиби маҳсулот',
        'role_scrum_master' => 'Scrum-master',
        'role_member' => 'Аъзои даста',

        // Sprints
        'new_sprint' => 'Спринти нав',
        'sprint_name' => 'Номи спринт',
        'start_date' => 'Санаи оғоз',
        'end_date' => 'Санаи анҷом',
        'sprint_planned' => 'Банақшагирифташуда',
        'sprint_active' => 'Ҷорӣ',
        'sprint_closed' => 'Анҷомёфта',
        'sprint_capacity' => 'Иқтидори спринт',

        // Messages
        'access_denied' => 'Шумо ба ин лоиҳа дастрасӣ надоред',
        'login_required' => 'Аввал ба система ворид шавед',
        'success' => 'Амалиёт бомуваффақият иҷро шуд',
        'error' => 'Хатогӣ рух дод',

    ],

    'ru' => [

        // General
        'app_name' => 'ASTAT SCRUM',
        'dashboard' => 'Панель управления',
        'projects' => 'Проекты',
        'tasks' => 'Задачи',
        'backlog' => 'Бэклог',
        'sprints' => 'Спринты',
        'team' => 'Команда',
        'settings' => 'Настройки',
        'profile' => 'Профиль',
        'logout' => 'Выйти',
        'login' => 'Войти',
        'register' => 'Регистрация',
        'language' => 'Язык',
        'save' => 'Сохранить',
        'cancel' => 'Отмена',
        'delete' => 'Удалить',
        'edit' => 'Редактировать',
        'create' => 'Создать',
        'add' => 'Добавить',
        'search' => 'Поиск',
        'name' => 'Название',
        'description' => 'Описание',
        'status' => 'Статус',
        'priority' => 'Приоритет',
        'date' => 'Дата',
        'actions' => 'Действия',
        'details' => 'Информация',
        'no_data' => 'Нет данных',
        'welcome' => 'Добро пожаловать',

        // Projects
        'my_projects' => 'Мои проекты',
        'new_project' => 'Новый проект',
        'create_project' => 'Создать проект',
        'project_name' => 'Название проекта',
        'project_description' => 'Описание проекта',
        'project_owner' => 'Владелец продукта',
        'no_projects' => 'Пока нет проектов. Создайте первый.',
        'project_created' => 'Проект успешно создан',

        // Tasks
        'new_task' => 'Новая задача',
        'task_name' => 'Название задачи',
        'task_description' => 'Описание задачи',
        'task_status' => 'Статус задачи',
        'task_priority' => 'Приоритет задачи',
        'story_points' => 'Story Points',
        'assignee' => 'Исполнитель',
        'comments' => 'Комментарии',
        'add_comment' => 'Добавить комментарий',

        // Task statuses
        'todo' => 'К выполнению',
        'in_progress' => 'В работе',
        'review' => 'На проверке',
        'done' => 'Выполнено',

        // Priorities
        'priority_low' => 'Низкий',
        'priority_medium' => 'Средний',
        'priority_high' => 'Высокий',
        'priority_critical' => 'Критический',

        // Roles
        'role_product_owner' => 'Владелец продукта',
        'role_scrum_master' => 'Scrum-мастер',
        'role_member' => 'Участник команды',

        // Sprints
        'new_sprint' => 'Новый спринт',
        'sprint_name' => 'Название спринта',
        'start_date' => 'Дата начала',
        'end_date' => 'Дата окончания',
        'sprint_planned' => 'Запланирован',
        'sprint_active' => 'Активный',
        'sprint_closed' => 'Завершён',
        'sprint_capacity' => 'Вместимость спринта',

        // Messages
        'access_denied' => 'У вас нет доступа к этому проекту',
        'login_required' => 'Сначала войдите в систему',
        'success' => 'Операция успешно выполнена',
        'error' => 'Произошла ошибка',

    ],

];

/* =========================================
   TRANSLATION FUNCTION
   ========================================= */

function t($key) {
    global $translations, $current_language;

    return $translations[$current_language][$key]
        ?? $translations['tg'][$key]
        ?? $key;
}

/* =========================================
   EXISTING PROJECT CONSTANTS
   ========================================= */

const ROLES = [
    'product_owner' => 'Соҳиби маҳсулот',
    'scrum_master'  => 'Scrum-master',
    'member'        => 'Аъзои даста',
];

const STATUS = [
    'todo'        => 'Барои иҷро',
    'in_progress' => 'Дар иҷро',
    'review'      => 'Дар санҷиш',
    'done'        => 'Иҷрошуда',
];

const PRIO = [
    1 => 'Паст',
    2 => 'Миёна',
    3 => 'Баланд',
    4 => 'Ҳатмӣ'
];

const POINTS = [1, 2, 3, 5, 8, 13, 21];

const SPRINT_STATUS = [
    'planned' => 'Банақшагирифташуда',
    'active' => 'Ҷорӣ',
    'closed' => 'Анҷомёфта'
];

/* =========================================
   EXISTING FUNCTIONS
   ========================================= */

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function user() {
    return $_SESSION['user'] ?? null;
}

function go($url) {
    header('Location: ' . $url);
    exit;
}

function need_login() {
    if (!user()) {
        go('login.php');
    }
}

function flash($type = null, $msg = null) {
    if ($type !== null) {
        $_SESSION['flash'] = [$type, $msg];
        return null;
    }

    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $f;
}

function need_member($pdo, $pid) {
    $s = $pdo->prepare(
        'SELECT role FROM project_members WHERE project_id=? AND user_id=?'
    );

    $s->execute([$pid, user()['id']]);
    $r = $s->fetchColumn();

    if (!$r) {
        http_response_code(403);
        die(t('access_denied'));
    }

    return $r;
}

function sprint_used($pdo, $sid) {
    $s = $pdo->prepare(
        'SELECT COALESCE(SUM(points),0) FROM tasks WHERE sprint_id=?'
    );

    $s->execute([$sid]);

    return (int)$s->fetchColumn();
}
