-- ASTAT SCRUM: дархостҳои назоратӣ
USE astat_scrum;
SET NAMES utf8mb4;

-- 1. TF-01: Намоиши вазифа бо лоиҳа, иҷрокунанда ва спринт (JOIN-и 3+ ҷадвал)
SELECT t.id, t.title, p.name AS project_name,
       u.full_name AS assignee, s.name AS sprint_name, t.status
FROM tasks t
JOIN projects p ON p.id = t.project_id
LEFT JOIN users u ON u.id = t.assigned_to
LEFT JOIN sprints s ON s.id = t.sprint_id
ORDER BY t.id;

-- 2. TF-02: Ҷустуҷӯи корбарон аз рӯи оғози ном (LIKE, кириллӣ)
SELECT id, full_name, username
FROM users
WHERE full_name LIKE 'Мад%'
ORDER BY full_name;

-- 3. TF-03: Шумораи вазифаҳо аз рӯи ҳолат дар ҳар лоиҳа (GROUP BY + COUNT)
SELECT p.name, t.status, COUNT(t.id) AS task_count
FROM projects p
LEFT JOIN tasks t ON t.project_id = p.id
GROUP BY p.id, p.name, t.status
ORDER BY p.name, t.status;

-- 4. TF-04: Ҳамаи спринтҳо, аз ҷумла лоиҳаҳое, ки спринти таъиншуда надоранд (LEFT JOIN)
SELECT p.name AS project_name, s.name AS sprint_name, s.status
FROM projects p
LEFT JOIN sprints s ON s.project_id = p.id
ORDER BY p.id, s.id;

-- 5. TF-05: Филтр аз рӯи ҳолат ва афзалият
SELECT id, title, status, priority
FROM tasks
WHERE status IN ('todo','in_progress') AND priority <= 2
ORDER BY priority, id;

-- 6. TF-06: Саҳифабандӣ, саҳифаи дуюм бо 5 вазифа
SELECT id, title, status
FROM tasks
ORDER BY id
LIMIT 5 OFFSET 5;
