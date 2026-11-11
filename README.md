# AI Content API

Небольшой backend для фоновой генерации описаний товаров. Клиент получает `202 Accepted`, Redis-воркер обращается к AI-провайдеру, а результат и история попыток сохраняются в MySQL.

**PHP 8.3 · Laravel 11 · MySQL 8.4 · Redis 7.4 · Docker Compose**

По умолчанию работает детерминированный демопровайдер: API-ключ и платные вызовы для знакомства с проектом не нужны. Реальный адаптер использует OpenAI Responses API.

> Laravel 11 выбран для соответствия стеку вакансии. Поддержка безопасности этой версии завершилась **12 марта 2026 года**. Для нового production-проекта следует выбрать поддерживаемую версию. [Политика поддержки Laravel](https://laravel.com/docs/11.x/releases#support-policy).

У ветки 11 есть известные security advisories. Для этой локальной демонстрации настроены адресные исключения **только при установке**, с обоснованием по каждой затронутой возможности. `composer audit` продолжает их показывать. См. [разбор ограничений](docs/security.md).

## Быстрый запуск

Нужен работающий Docker Desktop / Docker Engine с Compose v2. PHP и Composer на хосте не требуются.

**Windows, PowerShell:**

```powershell
.\scripts\setup.ps1
```

**Linux / macOS:**

```sh
sh scripts/setup.sh
```

Скрипт создаёт `.env`, собирает образ, генерирует ключ приложения, выполняет миграции и запускает API, воркер и планировщик. В конце выводится персональный токен демопользователя; он действует семь дней. Повторный запуск сохраняет существующий `.env` и данные.

API: **http://localhost:8080/api/v1**. Проверка доступности процесса: **http://localhost:8080/up**. Порт можно изменить через `APP_PORT` в `.env`.

Проверка всего сценария, включая HTTP и отдельный Redis-воркер:

```sh
docker compose exec app php artisan demo:smoke
```

Команда создаёт временный токен, проверяет создание, повтор запроса, конфликт параметров, получение результата и статистику. Токен удаляется в `finally`. Задание остаётся в истории специального smoke-пользователя.

## Пример запроса

Сохраните тело запроса в `request.json` или используйте готовый файл [docs/examples/generation.json](docs/examples/generation.json):

```json
{
  "product_name": "Термокружка Trail",
  "features": ["Нержавеющая сталь", "Объём 350 мл", "Герметичная крышка"],
  "language": "ru",
  "tone": "friendly"
}
```

В PowerShell задайте `$env:API_TOKEN = 'токен из setup'`, затем:

```powershell
curl.exe -i http://localhost:8080/api/v1/generations `
  -H "Authorization: Bearer $env:API_TOKEN" `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -H "Idempotency-Key: product-description-001" `
  --data-binary "@docs/examples/generation.json"
```

В Bash задайте `export API_TOKEN='токен из setup'`:

```sh
curl -i http://localhost:8080/api/v1/generations \
  -H "Authorization: Bearer $API_TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: product-description-001' \
  --data-binary @docs/examples/generation.json
```

Первый запрос возвращает `202`, `Location` и `data.id`. Результат доступен через `GET /api/v1/generations/{id}`. Статусы: `queued`, `processing`, `succeeded`, `failed`.

| Метод | Назначение |
| --- | --- |
| `POST /api/v1/generations` | Создать задание; обязательный заголовок `Idempotency-Key` |
| `GET /api/v1/generations/{id}` | Получить своё задание и результат |
| `GET /api/v1/generations` | История: `status`, `from`, `to`, `per_page`, `cursor` |
| `GET /api/v1/usage` | Статистика за период `from`–`to`, включительно, UTC |

[Контракт OpenAPI 3.0](docs/openapi.yaml) содержит схемы запросов, ответов, ошибок и заголовков.

## Что посмотреть в коде

1. [GenerationService](app/Services/GenerationService.php) и [GenerationRepository](app/Repositories/GenerationRepository.php): идемпотентное создание, уникальное ограничение и конкурентный захват задания.
2. [GenerationProcessor](app/Services/GenerationProcessor.php): граница транзакции, вызов провайдера, завершение попытки и безопасные повторы.
3. [OpenAITextGenerator](app/AI/OpenAITextGenerator.php) и [TextGenerator](app/Contracts/TextGenerator.php): небольшой контракт интеграции, обработка внешних ошибок и подмена реализации.
4. [UsageRepository](app/Repositories/UsageRepository.php): агрегирование без размножения строк при соединении с попытками.
5. [MySqlConcurrencyTest](tests/Integration/MySqlConcurrencyTest.php) и [RedisWorkerTest](tests/Integration/RedisWorkerTest.php): отдельные процессы, настоящий MySQL и Redis.

HTTP-контроллеры отвечают за протокол, Form Requests — за валидацию, сервисы — за сценарии, репозитории — за операции с данными. Policies ограничивают доступ к отдельному заданию. Код интеграции зависит от `TextGenerator`, который связывается с реализацией через контейнер Laravel.

[Архитектура и компромиссы](docs/architecture.md) объясняют поведение при сбоях и границы гарантий.

## Надёжность и ограничения

- **Повтор запроса:** тот же пользователь, ключ и нормализованные параметры → `200` с существующим заданием. Другие параметры → `409`. Ключи чувствительны к регистру, живут столько же, сколько запись задания. Порядок характеристик считается частью входных данных.
- **Конкуренция:** уникальный индекс защищает создание. Воркер захватывает строку через `SELECT ... FOR UPDATE`, создаёт попытку и коммитит транзакцию **до** HTTP-вызова. Повторная доставка завершённого или уже обрабатываемого задания не вызывает AI повторно.
- **Сбой публикации в Redis:** запись `queued` остаётся в MySQL. `generations:recover` каждую минуту повторно публикует до 500 готовых заданий. Поэтому API может принять задание во время краткого сбоя очереди.
- **Повторы провайдера:** только явно классифицированный rate limit, максимум три вызова. Сохраняются задержка, номер попытки и безопасный код ошибки. Квота, ошибки запроса, таймауты и неоднозначные серверные сбои автоматически не повторяются.
- **Прерванный воркер:** после пяти минут `processing` задание завершается с `outcome_unknown`. Поздний ответ не перезаписывает итоговое состояние. Внешний вызов мог быть выполнен и оплачен; гарантии exactly-once между MySQL и сторонним API нет.
- **Статистика:** период относится к дате создания задания. Число заданий и токены попыток считаются отдельно. Неизвестный расход отражён в `attempts_with_unreported_usage`; это не платёжный отчёт. Кеш изолирован по пользователю и периоду, допустимое отставание — 60 секунд.
- **Доступ:** Sanctum Bearer tokens с правами `generations:read` / `generations:write`, чужое задание возвращает `404`. Лимиты — 10 записей и 120 чтений в минуту на пользователя.

Демопровайдер формирует шаблонный текст, не имитирует качество LLM и возвращает нулевой расход токенов. Этот репозиторий демонстрирует backend-сценарий; производительность под реальной пользовательской нагрузкой отдельно не заявляется.

## Реальный AI-провайдер

В `.env` укажите:

```dotenv
AI_DRIVER=openai
OPENAI_API_KEY=your-key
OPENAI_MODEL=the-model-id-available-in-your-account
```

Пересоздайте процессы, чтобы они прочитали новое окружение:

```sh
docker compose up -d --force-recreate app worker scheduler
```

Модель выбирается явно. Приложение отправляет `store=false`, ограничивает ответ 800 токенами, использует connect timeout 5 секунд и общий HTTP timeout 40 секунд. Задание фиксирует провайдера и модель при создании: изменение настроек не переключает уже принятые задания на другой провайдер незаметно.

Проверки HTTP-контракта используют подмену ответов; live-вызовы оплачиваемого API не входят в CI. [Официальное руководство по генерации текста](https://developers.openai.com/api/docs/guides/text).

## Проверки

Проверено локально в Docker: **35 тестов, 165 assertions**, Pint и Larastan уровня 6; полный HTTP smoke-сценарий прошёл. Контракт OpenAPI проверен валидатором спецификации. Live-вызов OpenAI с платным ключом не выполнялся.

```sh
docker compose --profile test run --build --rm tests
```

Одна команда запускает:

- Laravel Pint;
- Larastan / PHPStan, уровень 6;
- PHPUnit: unit, HTTP feature и интеграционные тесты.

У тестов отдельные контейнеры `mysql-test`, `redis-test` и база `content_api_test`. Защитная проверка имени базы выполняется **до миграций тестов**. Конкурентные тесты используют четыре независимых PHP-процесса; интеграционный тест запускает отдельный `queue:work`. Исходящие HTTP-вызовы запрещены в тестовой среде, кроме явно подменённых.

Отдельные наборы:

```sh
docker compose --profile test run --rm tests php artisan test --testsuite=Feature
docker compose --profile test run --rm tests php artisan test --testsuite=Integration
```

Тот же набор проверок задан в [GitHub Actions](.github/workflows/ci.yml). Отдельный неблокирующий шаг выводит аудит выбранной legacy-версии; зелёный результат тестов не означает отсутствия advisories. Для работы с локальным PHP используйте Composer 2.10+, `.env.testing.example`, отдельные MySQL/Redis и `composer check`.

## MySQL и планы запросов

```sh
docker compose exec app php artisan demo:benchmark --rows=10000
```

Команда добавляет синтетические записи отдельному benchmark-пользователю и показывает два реальных `EXPLAIN ANALYZE`: с выбором оптимизатора и с исключением индексов истории. Количество строк можно увеличить до 1 000 000. Повторный запуск добавляет данные; реальные генерации и AI API не вызываются.

Индексы истории: `(user_id, created_at, id)` и `(user_id, status, created_at, id)`. Составной порядок соответствует условиям отбора и стабильной cursor-пагинации. Диапазоны дат задаются сравнением исходного столбца без `DATE(created_at)`. Отдельные индексы обслуживают публикацию очереди и поиск прерванных попыток.

[Пример фактического плана на 20 000 записей](docs/mysql-query-plan.md) содержит результаты локального прогона и объяснение выбора оптимизатора.

## Работа с окружением

```sh
docker compose logs --tail=50 worker scheduler
docker compose exec app php artisan generations:recover
docker compose exec app php artisan queue:failed
docker compose stop
```

После изменения исходников пересоберите образ и пересоздайте процессы:

```sh
docker compose build app
docker compose up -d --force-recreate app worker scheduler
```

Compose сохраняет MySQL и Redis в именованных томах. `docker compose down` сохраняет данные; флаг `--volumes` удаляет их. MySQL и Redis не публикуют порты на хост, API привязан к `127.0.0.1`.

В контейнере используется непривилегированный пользователь. HTTP обслуживает development server Laravel; production-развёртывание потребует отдельной конфигурации веб-сервера, TLS, секретов, резервного копирования и наблюдения. Заданные здесь пароли предназначены только для локального окружения.

## Лицензия

MIT. См. [LICENSE](LICENSE).
