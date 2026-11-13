<?php
session_start();

$pdo = new PDO('mysql:host=localhost;dbname=scrum;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

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
const PRIO = [1 => 'Паст', 2 => 'Миёна', 3 => 'Баланд', 4 => 'Ҳатмӣ'];
const POINTS = [1, 2, 3, 5, 8, 13, 21];
const SPRINT_STATUS = ['planned' => 'Банақшагирифташуда', 'active' => 'Ҷорӣ', 'closed' => 'Анҷомёфта'];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function user() { return $_SESSION['user'] ?? null; }
function go($url) { header('Location: ' . $url); exit; }
function need_login() { if (!user()) go('login.php'); }

function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function need_member($pdo, $pid) {
    $s = $pdo->prepare('SELECT role FROM project_members WHERE project_id=? AND user_id=?');
    $s->execute([$pid, user()['id']]);
    $r = $s->fetchColumn();
    if (!$r) { http_response_code(403); die('Шумо аъзои ин лоиҳа нестед'); }
    return $r;
}

function sprint_used($pdo, $sid) {
    $s = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM tasks WHERE sprint_id=?');
    $s->execute([$sid]);
    return (int)$s->fetchColumn();
}
