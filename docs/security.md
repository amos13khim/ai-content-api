# Laravel 11: ограничения выбранной версии

Дата проверки: 10 ноября 2026 года. Laravel 11 оставлен по явному требованию к стеку демонстрационного проекта. Это не рекомендация использовать неподдерживаемый фреймворк для нового публичного сервиса.

Composer блокирует разрешение зависимостей для ветки 11 из-за перечисленных ниже уведомлений. В `composer.json` заданы исключения по конкретным идентификаторам: `policy.advisories.ignore-id`, `on-block: true`, `on-audit: false`. Исключение применяется к блокировке установки, но не к аудиту: уведомления и ненулевой код возврата `composer audit` сохраняются. Проверки остальных зависимостей и новых уведомлений не отключены.

| Уведомление | Затронутая возможность | Состояние этого проекта |
| --- | --- | --- |
| [GHSA-f9x3-7vq9-cph6](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6), PKSA-b41t-wbzk-9q51 | Обход встроенного TokenGuard через нестроковые credentials | Маршруты используют `auth:sanctum`; guard с `driver=token` отсутствует. Невалидные query credentials проверяются тестом. |
| [GHSA-jh5r-qr3c-85q8](https://github.com/laravel/framework/security/advisories/GHSA-jh5r-qr3c-85q8), PKSA-d5tc-s1qs-h781 | XSS в debug-странице | `APP_DEBUG=false` во всех примерах и тестах, API возвращает JSON. |
| [GHSA-crmm-hgp2-wgrp](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp), PKSA-m5cs-t1y6-qpcs | Временные подписанные URL локального файлового драйвера | Файловая раздача и временные подписанные URL не используются. |
| [GHSA-5vg9-5847-vvmq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq), PKSA-3r5d-mb8f-1qw9 и PKSA-mdq4-51ck-6kdq | CRLF в email validation совместно с исходящей почтой | HTTP API не принимает email и не отправляет почту. Локальная CLI-команда проверяет адрес через `FILTER_VALIDATE_EMAIL`. Два PKSA описывают одно уведомление. |

Проверка:

```sh
docker compose run --rm --no-deps app composer audit --locked
```

В CI этот аудит выводится отдельным неблокирующим шагом. Функциональные тесты, форматирование и статический анализ остаются обязательными. Исключения не являются исправлением фреймворка: перед добавлением перечисленных возможностей или production-развёртыванием требуется обновить Laravel и убрать исключения.

Механизм исключений описан в [документации Composer](https://getcomposer.org/doc/06-config.md#ignore-id).
