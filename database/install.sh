#!/bin/bash
# Установка БД на сервере Bitrix / Linux
# chmod +x database/install.sh && ./database/install.sh

set -e
cd "$(dirname "$0")/.."
PROJECT_DIR="$(pwd)"

DB_NAME="${DB_NAME:-ooofix_xmlupd_cloud}"
DB_USER="${DB_USER:-ooofix_xmlupd}"
DB_HOST="${DB_HOST:-localhost}"

echo "=== ooofix-xmlupd-cloud: установка БД ==="
echo "Проект: $PROJECT_DIR"
echo "База:   $DB_NAME"
echo ""

read -sp "Пароль MySQL root: " MYSQL_ROOT_PASS
echo ""

mysql -h "$DB_HOST" -u root -p"$MYSQL_ROOT_PASS" < "$PROJECT_DIR/database/install.sql"

echo ""
echo "Создание пользователя $DB_USER (опционально)..."
read -sp "Пароль для пользователя $DB_USER: " APP_DB_PASS
echo ""

mysql -h "$DB_HOST" -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${APP_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo ""
echo "Готово. Укажите в config/app.php:"
echo "  'name' => '$DB_NAME',"
echo "  'user' => '$DB_USER',"
echo "  'pass' => '...',"
