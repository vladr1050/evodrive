# Деплой EvoDrive (Docker + CI/CD)

Основа под **Ride-Hailing SaaS** (деплой по push в main через GitHub Actions): один раз поднимаешь сервер скриптом, дальше деплой — через push в `main` (GitHub Actions). Ручной SSH нужен только для первичной настройки и при сбоях.

- **Сервер:** 89.167.74.32 (Hetzner)  
- **Домен:** www.evodrive.lv  
- **Стек:** Docker Compose (Nginx, Laravel app, queue, scheduler, PostgreSQL, Redis). Никаких ручных установок Nginx/PHP/MySQL/Node/Composer на хост.

---

## 1. Что в репозитории

| Компонент | Описание |
|-----------|----------|
| `Dockerfile` | Laravel (PHP-FPM + расширения для PostgreSQL, Redis, Filament) |
| `docker-compose.yml` | app, nginx, caddy, queue, scheduler, postgres, redis |
| `docker/nginx.conf` | Конфиг Nginx для контейнеров |
| `docker/Caddyfile` | Caddy: HTTPS (Let's Encrypt) и проксирование на Nginx |
| `docker/entrypoint.sh` | Копирование `public/build` в общий volume для Nginx |
| `.env.example` | Локальная разработка (sqlite) + комментарии под production |
| `deploy/env.production.example` | Шаблон .env для production (без секретов) |
| `scripts/server-setup.sh` | Один раз: Docker, UFW, каталог приложения, опционально swap |
| `.github/workflows/deploy.yml` | На push в `main`: деплой по SSH, миграции, кэши, перезапуск воркеров |

---

## 2. Первичная настройка сервера (один раз)

### 2.1 Подключение

```bash
ssh root@89.167.74.32
# или: ssh -i ~/.ssh/ключ root@89.167.74.32
```

Убедись, что в `~/.ssh/authorized_keys` на сервере есть твой публичный ключ.

### 2.2 Запуск server-setup.sh

На сервере клонируй репо (во временную папку), запусти скрипт, затем клонируй репо уже в рабочую папку.

**Вариант A: репо уже есть локально / можно скопировать скрипт**

```bash
# На своей машине (если нужно) скопировать скрипт на сервер
scp -r evodrive-app/scripts root@89.167.74.32:/tmp/evodrive-scripts
```

На сервере:

```bash
sudo bash /tmp/evodrive-scripts/server-setup.sh --app-dir /var/www/evodrive --swap 2048
```

**Вариант B: клонировать репо на сервер и запустить из него**

```bash
apt-get update && apt-get install -y git
git clone https://github.com/GITHUB_USER/REPO.git /tmp/evodrive-repo
sudo bash /tmp/evodrive-repo/scripts/server-setup.sh --app-dir /var/www/evodrive --swap 2048
```

Опции:

- `--app-dir /var/www/evodrive` — каталог приложения (по умолчанию `/var/www/evodrive`).
- `--swap 2048` — создать 2 GB swap (рекомендуется для малых VPS).
- `--user deploy` — создать/использовать пользователя `deploy` и дать ему права на каталог и Docker.

После скрипта на хосте будут: Docker, Docker Compose plugin, UFW (22, 80, 443), каталог приложения. Установок Nginx/PHP/MySQL/Node/Composer на хост **нет**.

### 2.3 Клонирование репозитория в рабочую папку

На сервере (под пользователем, который будет деплоить, или root):

```bash
sudo -u root bash -c 'cd /var/www/evodrive && git clone https://github.com/GITHUB_USER/REPO.git .'
```

Если репо приватный: настрой на сервере SSH-ключ и добавь его в GitHub (Deploy keys), затем клонируй по SSH:

```bash
git clone git@github.com:GITHUB_USER/REPO.git /var/www/evodrive
```

Если Laravel лежит в подпапке репо (например `evodrive-app/`), после клона:

```bash
cd /var/www/evodrive
mv evodrive-app/* . && mv evodrive-app/.* . 2>/dev/null; rmdir evodrive-app 2>/dev/null || true
```

### 2.4 .env и первый запуск

```bash
cd /var/www/evodrive
cp deploy/env.production.example .env
```

Отредактируй `.env`: задай `APP_KEY` (сгенерируй: `docker compose run --rm app php artisan key:generate --show` и вставь), `DB_PASSWORD`, при необходимости `APP_URL` и почту.

```bash
# Сгенерировать APP_KEY и подставить в .env вручную
docker compose run --rm app php artisan key:generate --show
```

Затем:

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
```

Первый пользователь Filament (админка):

```bash
docker compose exec app php artisan make:filament-user
```

### 2.5 SSL (HTTPS) — Caddy

В стек уже добавлен **Caddy**: он слушает 80/443, сам получает сертификаты Let's Encrypt и проксирует запросы на Nginx.

**Перед включением Caddy:**

1. В DNS для **evodrive.lv** и **www.evodrive.lv** должны быть A-записи на IP сервера (89.167.74.32). Иначе Caddy не получит сертификат и не стартует.
2. В `docker/Caddyfile` указаны домены `evodrive.lv, www.evodrive.lv`. Если нужны другие — отредактируй файл.

**Запуск с SSL:**

```bash
cd /var/www/evodrive
docker compose up -d caddy
```

Caddy подхватит конфиг и будет обслуживать HTTPS. Сертификаты сохраняются в volume `caddy_data` и продлеваются автоматически.

**Пока DNS не настроен:** чтобы сайт работал по HTTP с одного только IP, временно замени в `docker/Caddyfile` первую строку на `:80 {` (убери домены). Тогда Caddy будет просто проксировать на Nginx по 80. Когда DNS будет готов — верни домены и перезапусти: `docker compose up -d caddy`.

---

## 3. Деплой в дальнейшем (без ручного SSH)

После настройки п.2 и п.3.1 деплой идёт автоматически при push в `main`.

### 3.1 Секреты в GitHub и один раз — Git на сервере

**Секреты в GitHub**

В репозитории: **Settings → Secrets and variables → Actions → New repository secret**. Добавь:

| Секрет | Значение |
|--------|----------|
| `DEPLOY_HOST` | `89.167.74.32` |
| `DEPLOY_USER` | `root` (или пользователь, у которого есть доступ к `/var/www/evodrive` и Docker) |
| `DEPLOY_SSH_KEY` | Полное содержимое **приватного** SSH-ключа (тот, чей публичный ключ лежит в `~/.ssh/authorized_keys` на сервере). Без пароля или с passphrase — оба варианта поддерживаются. |
| `DEPLOY_PATH` | (необязательно) Каталог приложения, например `/var/www/evodrive` |

На сервере у пользователя `DEPLOY_USER` в `~/.ssh/authorized_keys` должен быть соответствующий публичный ключ.

**Один раз: перевести сервер на Git (для CI/CD)**

Если сейчас код попал на сервер через rsync (без Git), нужно один раз перейти на клон репо, чтобы workflow мог делать `git fetch` и `git reset --hard origin/main`. Если в `/var/www/evodrive` уже есть папка `.git` (репо уже клонирован) — этот шаг пропускай.

На сервере выполни (подставь свой репозиторий; для приватного репо см. ниже):

```bash
cd /var/www/evodrive
cp .env /tmp/evodrive.env.bak
rm -rf ./* ./.[!.]* ./..?* 2>/dev/null || true
git clone https://github.com/GITHUB_USER/REPO.git .
mv /tmp/evodrive.env.bak .env
docker compose up -d
```

Если репо **приватный**: на сервере сгенерируй SSH-ключ для деплоя, добавь его в GitHub как Deploy key (Settings → Deploy keys), затем клонируй по SSH:

```bash
ssh-keygen -t ed25519 -C "deploy@evodrive" -f /root/.ssh/evodrive_deploy -N ""
cat /root/.ssh/evodrive_deploy.pub
# → добавь в GitHub (Deploy keys), затем:
cd /var/www/evodrive
cp .env /tmp/evodrive.env.bak
rm -rf ./* ./.[!.]* ./..?* 2>/dev/null || true
GIT_SSH_COMMAND="ssh -i /root/.ssh/evodrive_deploy -o StrictHostKeyChecking=no" git clone git@github.com:GITHUB_USER/REPO.git .
mv /tmp/evodrive.env.bak .env
docker compose up -d
```

После этого в `/var/www/evodrive` будет полноценный Git-репозиторий, и при push в `main` workflow сможет выполнять деплой без ручного SSH.

### 3.2 Что делает workflow при push в `main`

1. Подключение по SSH к серверу.
2. `cd $DEPLOY_PATH` (или `/var/www/evodrive`).
3. `git fetch origin && git reset --hard origin/main`.
4. `docker compose build --no-cache app` и `docker compose up -d`.
5. Миграции: `php artisan migrate --force`.
6. Кэши: `config:cache`, `route:cache`, `view:cache`.
7. Перезапуск контейнеров `queue` и `scheduler`.

Ручной SSH для обычных деплоев **не нужен**.

### 3.3 Ручной деплой (если понадобится)

На сервере:

```bash
cd /var/www/evodrive
git pull
docker compose build --no-cache app
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose restart queue scheduler
```

---

## 4. Где что хранится

| Что | Где |
|-----|-----|
| Код приложения | `/var/www/evodrive` (на хосте) |
| Конфиг окружения (секреты) | `/var/www/evodrive/.env` (только на сервере, не в Git) |
| Данные PostgreSQL | Docker volume `postgres_data` |
| Данные Redis | Docker volume `redis_data` |
| Статика (public/build) | Копируется в volume `public_assets`, отдаётся Nginx |
| Логи и storage приложения | Docker volumes `storage_app`, `storage_logs` |

Бэкапы: дамп PostgreSQL и (при необходимости) архивирование volume со storage — лучше вынести в отдельный скрипт/cron.

---

## 5. Проверка

- Главная: http://www.evodrive.lv (или https после настройки SSL).
- Админка: http://www.evodrive.lv/admin (логин — пользователь, созданный через `make:filament-user`).
- Логи: `docker compose logs -f app` и `docker compose logs -f nginx`.

Если что-то падает: `docker compose ps`, `docker compose logs app queue scheduler`.
