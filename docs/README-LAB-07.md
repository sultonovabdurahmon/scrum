# ASTAT SCRUM — Lab 07

## Файлҳо
- `database/schema.sql` — сохтани пойгоҳи `astat_scrum` ва 7 ҷадвал.
- `database/seed.sql` — маълумоти санҷишӣ барои ҳамаи ҷадвалҳо.
- `database/queries.sql` — 6 дархости назоратӣ.
- `docs/07-deployment.puml` ва `docs/07-deployment.png` — диаграммаи ҷойгиркунӣ.
- `docs/07-screens/README.txt` — рӯйхати скриншотҳои воқеие, ки бояд илова шаванд.

## Иҷро дар XAMPP
1. Дар XAMPP Control Panel MySQL-ро оғоз кунед.
2. Аз XAMPP Shell ба решаи репозиторий гузаред.
3. Иҷро кунед:

```bash
mysql -u root --default-character-set=utf8mb4 < database/schema.sql
mysql -u root --default-character-set=utf8mb4 < database/seed.sql
mysql -u root --default-character-set=utf8mb4 --table < database/queries.sql
```

Агар root парол дошта бошад, ба ҳар фармон `-p` илова кунед.

Дар PowerShell, агар оператори `<` кор накунад, дар MySQL client фармонҳои `SOURCE`-ро истифода баред:

```sql
SOURCE C:/xampp/htdocs/Zoidov-scrum/scrum/database/schema.sql;
SOURCE C:/xampp/htdocs/Zoidov-scrum/scrum/database/seed.sql;
SOURCE C:/xampp/htdocs/Zoidov-scrum/scrum/database/queries.sql;
```

Роҳи болоиро бо роҳи воқеии папкаи лоиҳаи худ иваз кунед.

## Ҳисобҳои санҷишӣ
Пароли ҳамаи ҳисобҳои seed: `Secret123!`.
Нақшҳои лоиҳа дар `project_members`: `product_owner`, `scrum_master`, `member`.
Ин схема барои супориши лабораторӣ аст. Агар сохтори воқеии ASTAT SCRUM-и шумо фарқ кунад, пеш аз пайваст кардани барнома онро мутобиқ кунед.

## Скриншотҳо
Скриншотҳои воқеии натиҷаи `queries.sql` ва phpMyAdmin Designer-ро дар `docs/07-screens/` нигоҳ доред. PNG-и диаграмма ҷойгузини ин далелҳо нест.
