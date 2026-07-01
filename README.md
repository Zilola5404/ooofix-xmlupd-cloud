# Генерация XML (УПД) — облачное REST-приложение Bitrix24

Тиражное приложение для облака Bitrix24 на основе модуля коробки `ooofix.xmlupd`.

## Структура

```
ooofix-xmlupd-cloud/
├── public/              ← document root на сервере (HTTPS)
│   ├── install.php      ← URL установки
│   ├── index.php        ← URL приложения (настройки)
│   ├── handler.php      ← обработчик робота CRM
│   ├── api/             ← REST API приложения
│   └── placements/      ← кнопка в карточке сделки
├── src/
│   ├── Core/            ← ядро XML (скопировано из модуля коробки)
│   ├── Rest/            ← REST-клиент Bitrix24
│   ├── Generate/        ← конвейер генерации
│   ├── App/             ← OAuth, установка
│   └── Storage/         ← MySQL
├── config/app.php       ← client_id, secret, БД
└── database/schema.sql
```

## Установка на сервер

### 1. Требования

- PHP 8.1+, ext-dom, ext-iconv, ext-curl, ext-pdo, MySQL
- HTTPS-домен
- Аккаунт партнёра [vendors.bitrix24.ru](https://vendors.bitrix24.ru)

### 2. Загрузка файлов (Beget / shared hosting)

Скопируйте папку `ooofix-xmlupd-cloud` на сервер, например:

```
/home/u/up-fix/public_html/market/ooofix-xmlupd-cloud/
```

Структура на сервере:

```
ooofix-xmlupd-cloud/
  .htaccess          ← маршрутизация в public/
  .env
  bootstrap.php
  public/            ← сюда попадают HTTP-запросы
    index.php
    install.php
    handler/
    ...
```

**Не** указывайте `APP_URL` с `/public/` — в корне лежит `.htaccess`, который проксирует запросы.

Публичный URL приложения:

```
https://up-fix.ru/market/ooofix-xmlupd-cloud/
```

### 3. База данных

#### Вариант A: отдельная база `ooofix_xmlupd_cloud` (VPS / root)

**Важно:** файл `database/schema.sql` создаёт только таблицы, **без** `CREATE DATABASE`.  
Поэтому команда `mysql -u root -p < database/schema.sql` выдаёт ошибку *No database selected*.

Используйте **полный скрипт**:

```bash
cd /path/to/ooofix-xmlupd-cloud
mysql -u root -p < database/install.sql
```

#### Вариант B: shared-хостинг, база `btops_app` (уже создана)

1. Скопируйте `.env.example` → `.env` и укажите доступы:

```env
DB_HOST=localhost
DB_NAME=btops_app
DB_USER=btops_app
DB_PASS=ваш_пароль
```

2. Создайте таблицы:

```bash
mysql -u btops_app -p btops_app < database/install_btops_app.sql
```

3. Проверка с сервера приложения:

```bash
php public/cli/db_check.php
```

Проверка:

```bash
mysql -u root -p -e "USE ooofix_xmlupd_cloud; SHOW TABLES;"
```

Должны быть: `portals`, `portal_settings`, `b_xmldoc_log`, `b_xmldoc_document`, `queue_jobs`.

#### Сервер Bitrix (коробка)

Пароль root MySQL часто в `/root/.my.cnf` или спросите у админа.  
Можно взять доступ к БД из Битрикс: `/home/bitrix/www/bitrix/php_interface/dbconn.php` — но для приложения лучше **отдельная** база `ooofix_xmlupd_cloud`.

```bash
cd /home/bitrix/www/ooofix-xmlupd-cloud
mysql -u root -p < database/install.sql
```

Создать отдельного пользователя (рекомендуется):

```sql
CREATE USER 'ooofix_xmlupd'@'localhost' IDENTIFIED BY 'ваш_пароль';
GRANT ALL ON ooofix_xmlupd_cloud.* TO 'ooofix_xmlupd'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Конфигурация

```bash
cp .env.example .env
```

Заполните `.env`:

- `DB_*` — доступ к MySQL (`btops_app` на shared-хостинге)
- `B24_CLIENT_ID`, `B24_CLIENT_SECRET` — из кабинета разработчика
- `APP_URL` — `https://ваш-домен.ru`

Файл `config/app.php` читает переменные из `.env` автоматически.

### Проверка после загрузки

```
https://up-fix.ru/market/ooofix-xmlupd-cloud/public/health.php
```

Скрипт покажет: версию PHP, наличие файлов, подключение к БД и таблицы.

### HTTP 500 — частые причины на Beget

1. **PHP ниже 8.1** — в панели Beget для каталога `market/ooofix-xmlupd-cloud` выберите PHP **8.1** или **8.2**.
2. **Нет `config/app.php`** — загрузите из репозитория (секретов в нём нет, только чтение `.env`).
3. **Нет `.env`** или неверный `DB_*` — проверьте хост БД в панели Beget (часто `localhost`).
4. **Таблицы не созданы** — `mysql -u btops_app -p btops_app < database/install_btops_app.sql`
5. **Включите** `APP_DEBUG=1` в `.env` для текста ошибки в браузере.

### 5. Кабинет разработчика Bitrix24

| Поле | URL |
|------|-----|
| URL приложения | `https://up-fix.ru/market/ooofix-xmlupd-cloud/index.php` |
| URL установки | `https://up-fix.ru/market/ooofix-xmlupd-cloud/install.php` |
| URL обработчика робота | `https://up-fix.ru/market/ooofix-xmlupd-cloud/handler/robot.php` |

**Права:** `crm`, `userfieldconfig`, `user`, `disk`, `bizproc`, `placement`

### 6. Установка на портал

1. Откройте тестовый портал B24
2. Установите приложение из кабинета разработчика
3. Заполните настройки в приложении
4. Откройте сделку → кнопка «Сформировать УПД»

При установке автоматически создаются поля:
- `UF_UPD_NUMBER` — «Номер УПД (1С)»
- `UF_UPD_FILE` — «Файл УПД»

для **сделок** и смарт-процесса **«Счета»**. На уже установленных порталах — кнопка **«Создать UF-поля»** в настройках приложения.

### 7. Cron worker (опционально)

```bash
* * * * * php /var/www/ooofix-xmlupd-cloud/public/cli/worker.php 20
```

Робот и кнопка выполняют задачи синхронно; worker подхватывает зависшие `pending` из `queue_jobs`.

### 8. Миграция БД (если уже развёрнуто)

```bash
mysql -u root -p ooofix_xmlupd_cloud < database/migrations/001_production.sql
```

## Production-архитектура

```
public/handler/robot.php    — робот CRM → очередь → генерация
public/handler/button.php   — кнопка в карточке
public/handler/webhook.php  — события B24
public/api/sync.php         — UF-поля, health-check
src/Core/Tenant.php         — multi-tenant (portal_id)
src/Core/Logger.php         — request_id + portal_id + entity
src/Queue/                  — JobQueue + Worker
```

## Что скопировано из модуля коробки

| Коробка | Облако |
|---------|--------|
| `lib/Documents/Upd/*` | `src/Core/Documents/Upd/*` |
| `lib/Xml/*` | `src/Core/Xml/*` |
| `lib/Crm/Product*.php` | `src/Core/Crm/*` |
| `config/schemas/` | `src/Core/config/schemas/` |
| `lib/DataCollector.php` | `src/Rest/RestDataCollector.php` (переписан на REST) |
| `AbstractGenerateRuntime.php` | `src/Generate/GenerateService.php` |

## Nginx (пример)

```nginx
server {
    listen 443 ssl;
    server_name your-domain.ru;
    root /var/www/ooofix-xmlupd-cloud/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Поддержка

ООО «РЕШЕНИЕ» — https://ooofix.ru
