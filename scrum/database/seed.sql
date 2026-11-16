-- ASTAT SCRUM: маълумоти санҷишӣ (пароли умумӣ: Secret123!)
USE astat_scrum;
SET NAMES utf8mb4;

-- Хэши bcrypt барои Secret123!; дар PHP бо password_verify() санҷида мешавад.
SET @demo_hash = '$2y$10$UNkIFfDEqAOi7ySnyD6Y/eOwI/mKH3g0is1OYNEv4OkXNXk3XRGRC';

INSERT INTO users (id, username, email, password_hash, full_name) VALUES
(1, 'farhod', 'farhod@example.tj', @demo_hash, 'Фарҳод Раҳимов'),
(2, 'madina', 'madina@example.tj', @demo_hash, 'Мадина Назарова'),
(3, 'ali', 'ali@example.tj', @demo_hash, 'Али Саидов'),
(4, 'sabrina', 'sabrina@example.tj', @demo_hash, 'Сабрина Каримова'),
(5, 'rustam', 'rustam@example.tj', @demo_hash, 'Рустам Ҷӯраев');

INSERT INTO projects (id, name, description, owner_id) VALUES
(1, 'ASTAT SCRUM', 'Низоми идоракунии лоиҳаҳо ва спринтҳо', 1),
(2, 'Website Redesign', 'Навсозии сомонаи ширкат', 2),
(3, 'Mobile Shop', 'Лоиҳаи мағозаи либос барои Android', 1);

INSERT INTO project_members (project_id, user_id, role) VALUES
(1,1,'product_owner'), (1,2,'scrum_master'), (1,3,'member'), (1,4,'member'),
(2,2,'product_owner'), (2,1,'scrum_master'), (2,5,'member'),
(3,1,'product_owner'), (3,3,'scrum_master'), (3,4,'member');

INSERT INTO sprints (id, project_id, name, goal, start_date, end_date, status) VALUES
(1,1,'Sprint 1','Омода кардани вазифаҳои асосӣ','2026-11-01','2026-11-14','completed'),
(2,1,'Sprint 2','Такмили идоракунии вазифаҳо','2026-11-15','2026-11-28','active'),
(3,2,'Sprint 1','Навсозии саҳифаи асосӣ','2026-11-10','2026-11-24','active');

INSERT INTO tasks
(id, project_id, sprint_id, created_by, assigned_to, title, description, status, priority, story_points)
VALUES
(1,1,1,1,2,'Тарҳрезии саҳифаи воридшавӣ','Тарҳи шакли login','done',1,5),
(2,1,1,1,3,'Сохтани ҷадвали вазифаҳо','Ҷадвали tasks дар MySQL','done',2,8),
(3,1,1,2,3,'Илова кардани нақшҳои корбар','Product Owner, Scrum Master, Member','done',2,5),
(4,1,2,1,2,'Такмили панели лоиҳаҳо','Намоиши лоиҳаҳои корбар','in_progress',1,8),
(5,1,2,2,3,'Филтр аз рӯи ҳолати вазифа','Филтр барои todo ва done','todo',2,3),
(6,1,2,1,4,'Санҷиши амнияти воридшавӣ','Санҷиши password_hash','review',1,5),
(7,1,NULL,1,5,'Навиштани ҳуҷҷатгузорӣ','Тавсифи насб ва истифода','todo',3,3),
(8,2,3,2,5,'Навсозии саҳифаи асосӣ','Тарҳи нави homepage','in_progress',1,8),
(9,2,3,2,1,'Оптимизатсияи тасвирҳо','Коҳиш додани ҳаҷми тасвирҳо','todo',3,3),
(10,2,3,2,5,'Санҷиши версияи мобилӣ','Responsive layout','review',2,5),
(11,3,NULL,1,3,'Сохтани каталоги маҳсулот','Намоиши рӯйхати маҳсулот','todo',2,8),
(12,3,NULL,1,4,'Тарҳи сабади харид','Илова ва хориҷ кардани мол','in_progress',2,5);

INSERT INTO comments (task_id, user_id, body) VALUES
(1,2,'Тарҳ тасдиқ шуд.'),
(2,3,'Ҷадвал сохта ва санҷида шуд.'),
(4,2,'Лутфан ҳолати холиро низ нишон диҳед.'),
(6,4,'Санҷиш гузаронида шуд, натиҷа мусбат аст.'),
(8,5,'Макети аввал омода аст.');

INSERT INTO time_entries (task_id, user_id, minutes, work_date, note) VALUES
(1,2,120,'2026-11-02','Тарҳрезии интерфейс'),
(2,3,180,'2026-11-04','Сохтани ҷадвал'),
(3,3,90,'2026-11-06','Танзими нақшҳо'),
(4,2,150,'2026-11-16','Кор дар панели лоиҳа'),
(6,4,60,'2026-11-17','Санҷиши амният'),
(8,5,240,'2026-11-12','Навсозии макет');
