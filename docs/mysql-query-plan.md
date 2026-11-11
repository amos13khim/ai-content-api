# План запроса истории

Проверено 10 ноября 2026 года, MySQL 8.4 в локальном Docker Desktop. У benchmark-пользователя 20 000 синтетических заданий после двух запусков команды по 10 000 строк; 80% имеют статус `succeeded`. Это один диагностический прогон, без контроля холодного кеша и без одновременной пользовательской нагрузки.

Запрос соответствует выборке истории с фильтром по статусу и первой страницей. Включены все столбцы, как при загрузке Eloquent-модели:

```sql
EXPLAIN ANALYZE
SELECT *
FROM generations
WHERE user_id = 3 AND status = 'succeeded'
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

## Выбор оптимизатора

```text
Limit: 20 row(s) (actual time=0.0394..0.143 rows=20 loops=1)
  Filter: status = 'succeeded' (actual time=0.0387..0.141 rows=20 loops=1)
    Index lookup using generations_history_index (user_id=3), reverse
    (actual time=0.0273..0.136 rows=26 loops=1)
```

В этом распределении оптимизатор выбрал `(user_id, created_at, id)`, хотя индекс с дополнительным `status` тоже существует. Доля успешных заданий высока: достаточно прочитать 26 строк в нужном порядке, чтобы вернуть 20. Отдельная сортировка не потребовалась. Индекс нельзя считать выбранным только потому, что он создан миграцией — это проверяется фактическим планом.

## Сравнение без индексов истории

В диагностическом запросе оба индекса истории исключены через `IGNORE INDEX`; схема БД не меняется:

```sql
EXPLAIN ANALYZE
SELECT *
FROM generations
IGNORE INDEX (generations_history_index, generations_status_history_index)
WHERE user_id = 3 AND status = 'succeeded'
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

```text
Limit: 20 row(s) (actual time=47.4..47.4 rows=20 loops=1)
  Sort: created_at DESC, id DESC, limit input to 20 row(s)
  (actual time=47.4..47.4 rows=20 loops=1)
    Filter: status = 'succeeded' (actual time=0.0108..40.6 rows=16000 loops=1)
      Index lookup using generations_user_id_idempotency_key_unique (user_id=3)
      (actual time=0.00708..38.8 rows=20000 loops=1)
```

Сохранившийся уникальный индекс помогает найти пользователя, но не обеспечивает порядок истории. MySQL читает 20 000 строк, фильтрует их и выполняет сортировку. В этом прогоне верхний узел завершился за 47,4 мс вместо 0,143 мс. Это наблюдение на конкретных данных, а не обещание такой же разницы под любой нагрузкой.

## Воспроизведение

```sh
docker compose exec app php artisan demo:benchmark --rows=10000
```

Команда сама находит benchmark-пользователя, добавляет данные, обновляет статистику оптимизатора и печатает оба плана вместе с числом его записей. ID пользователя и времена будут отличаться. Для следующей страницы API использует cursor по паре `(created_at, id)`, чтобы одинаковые timestamps не давали дубли и не требовался большой `OFFSET`.
