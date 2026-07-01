# Деплой ooofix-xmlupd-cloud на Beget + vendors.bitrix24.ru

## 1. Собрать пакет (локально)

```bash
python tools/build_deploy.py
```

Архив: `local/modules/dist/ooofix-xmlupd-cloud-deploy.zip`

## 2. Загрузить на сервер

Распаковать в:

```
/home/b/btops/up-fix/public_html/market/ooofix-xmlupd-cloud/
```

Структура после распаковки:

```
ooofix-xmlupd-cloud/
  .htaccess
  .env.example
  bootstrap.php
  config/app.php
  public/
    index.php
    install.php
    health.php
    ...
```

## 3. Настроить сервер

```bash
cd /home/b/btops/up-fix/public_html/market/ooofix-xmlupd-cloud
cp .env.example .env
nano .env
```

`.env` (пример):

```env
DB_HOST=localhost
DB_NAME=btops_app
DB_USER=btops_app
DB_PASS=ваш_пароль

B24_CLIENT_ID=local.xxxxxxxx.xxxxxxxx
B24_CLIENT_SECRET=ваш_секрет
APP_URL=https://up-fix.ru/market/ooofix-xmlupd-cloud
APP_DEBUG=0
```

PHP **8.1+** в панели Beget для каталога `market/ooofix-xmlupd-cloud`.

Таблицы БД:

```bash
mysql -u btops_app -p btops_app < database/install_btops_app.sql
```

Проверка:

```
https://up-fix.ru/market/ooofix-xmlupd-cloud/public/health.php
https://up-fix.ru/market/ooofix-xmlupd-cloud/public/phpinfo.php
```

`phpinfo.php` — только для диагностики, **удалите** после настройки сервера.

## 4. Кабинет [vendors.bitrix24.ru](https://vendors.bitrix24.ru/)

### Версия 1.0.0

| Поле | Значение |
|------|----------|
| **Название приложения** | **Генерация XML (УПД)** (не оставляйте «Новое приложение») |
| **Название в меню (ru)** | **Генерация XML (УПД)** |
| Использовать Rest API | Да |
| Настраивать CRM | **Да** |
| Умные сценарии / шаблоны / BI / Vibe / ЦРМ / MCP | **Да** (обязательно — робот `bizproc.robot.add`) |
| Разделы | **CRM** |
| Минимальный тариф | **Стандарт** |
| Пункт в главном меню | Да |
| BitrixMobile | Нет |
| Виджеты в интерфейс | Да |
| Установка любым пользователем | Нет (только админ) |

### URL

**Используйте «Оставить ссылки на приложение»**, не «Загрузить приложение».

Архив с `index.html` — только для **статичных** локальных приложений без PHP-сервера.  
Это REST-приложение работает по URL на вашем сервере.

| Поле | URL |
|------|-----|
| Ссылка на приложение | `https://up-fix.ru/market/ooofix-xmlupd-cloud/index.php` |
| Установочное приложение | `https://up-fix.ru/market/ooofix-xmlupd-cloud/install.php` |
| Настройки | `https://up-fix.ru/market/ooofix-xmlupd-cloud/index.php` |

Кабинет [vendors.bitrix24.ru](https://vendors.bitrix24.ru/) проверяет URL запросом **HEAD** (должен быть статус **200**).  
`install.php` после установки вызывает `BX24.installFinish()`.

Права REST (scope) — обязательно все:

`crm`, `userfieldconfig`, `user`, `disk`, **`bizproc`**, `placement`

- `crm` — сделки, смарт-процессы, `crm.deal.userfield.add`, `crm.automation.trigger.add`
- `userfieldconfig` — UF смарт-процессов ([userfieldconfig.add](https://apidocs.bitrix24.ru/api-reference/crm/universal/userfieldconfig/userfieldconfig/userfieldconfig-add.html))
- **`bizproc`** — робот CRM ([bizproc.robot.add](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-robot/bizproc-robot-add.html)); в форме версии включите **«Умные сценарии / шаблоны» = Да**
- `placement` — кнопка в карточке CRM: `CRM_DEAL_DETAIL_TOOLBAR`, для СП «Счета» — `CRM_SMART_INVOICE_DETAIL_TOOLBAR` ([placement.list](https://apidocs.bitrix24.ru/api-reference/widgets/placement-list.html))

После изменения прав **опубликуйте версию** и **переустановите** приложение на портале.

### Название «Новое приложение» → «Генерация XML (УПД)»

**В коде:** `APP_TITLE` / `app_title`, `BX24.setTitle`, `placement.bind` (`LEFT_MENU`) при установке и при первом открытии настроек.

**В vendors (обязательно для пункта меню по умолчанию):**

1. [vendors.bitrix24.ru](https://vendors.bitrix24.ru/) → приложение → **Редактировать**
2. **Название приложения:** `Генерация XML (УПД)` (не «Новое приложение»)
3. Описание **ru** → **Название в меню:** `Генерация XML (УПД)`
4. **Опубликовать** версию → **переустановить** на портале

Если после установки в меню два пункта — в vendors отключите «Пункт в главном меню» и оставьте только виджет `LEFT_MENU` (регистрируется кодом), либо переименуйте в vendors и удалите дубликат вручную.

После загрузки файлов проверьте деплой в браузере:

```
https://up-fix.ru/market/ooofix-xmlupd-cloud/public/build-info.php
```

Должно быть: `"app_title": "Генерация XML (УПД)"`, `"build": "2.0.9.…"`, все `"exists": true`.

Откройте приложение напрямую (вне B24):

```
https://up-fix.ru/market/ooofix-xmlupd-cloud/index.php?page=settings
```

Должны быть карточки настроек, голубые кнопки, вкладки. В исходном коде страницы — **абсолютные** URL CSS (`https://up-fix.ru/.../frontend/css/...?v=`) **без** `<base href=".../public/">`.

### Bitrix24 не показывает новый дизайн / «Нovое приложение»

1. **Файлы на сервере уже новые**, но B24 кэширует iframe — **опубликуйте новую версию** на vendors и **переустановите** приложение на портале.
2. **Название в левом меню** («Новое приложение») — только в **vendors** → «Название приложения» = `Генерация XML (УПД)`.
3. В `.env` на сервере: `APP_TITLE=Генерация XML (УПД)`.
4. После загрузки — Ctrl+F5 в окне приложения B24.

### Cron (опционально)

```
* * * * * php /home/b/btops/up-fix/public_html/market/ooofix-xmlupd-cloud/public/cli/worker.php 20
```
