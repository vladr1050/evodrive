# EvoDrive — Deploy (bare metal)

Конфиги для развёртывания на сервере без Docker.

## 502 Bad Gateway — что проверить на сервере

502 значит: Nginx не получил ответ от PHP-FPM. Выполни на сервере по порядку.

### 1. PHP-FPM запущен и сокет совпадает с конфигом Nginx

```bash
# Статус (Ubuntu 24.04 — обычно PHP 8.3)
sudo systemctl status php8.3-fpm

# Если не active — запустить
sudo systemctl start php8.3-fpm
sudo systemctl enable php8.3-fpm
```

На Ubuntu 24.04 в репозиториях по умолчанию только PHP 8.3. На более старых системах может быть 8.1 или 8.2 — тогда сокет другой, например:
- `unix:/run/php/php8.1-fpm.sock`
- `unix:/run/php/php8.2-fpm.sock`

В `deploy/nginx-evodrive.conf` в `fastcgi_pass` должен быть тот же путь. Узнать сокет:

```bash
ls -la /run/php/php*-fpm.sock
```

После смены конфига Nginx: `sudo nginx -t && sudo systemctl reload nginx`.

### 2. Ошибки в логах

```bash
# Nginx
sudo tail -50 /var/log/nginx/error.log

# PHP-FPM (часто тут видна причина падения; подставь 8.2 или 8.3)
sudo journalctl -u php8.3-fpm -n 50 --no-pager
```

### 3. Права и путь приложения

- `root` в Nginx должен указывать на каталог с `public` (например `/var/www/evodrive/public`).
- Владелец файлов приложения — пользователь, под которым работает PHP-FPM (часто `www-data`):  
  `sudo chown -R www-data:www-data /var/www/evodrive`

### 4. Перезапуск цепочки

```bash
sudo systemctl restart php8.3-fpm   # или php8.2-fpm
sudo systemctl reload nginx
```

---

## Файлы

| Файл | Назначение |
|------|------------|
| `nginx-evodrive.conf` | Серверный блок Nginx для evodrive.lv. Копировать в `/etc/nginx/sites-available/`, включить site, настроить SSL (certbot). |
| `env.production.example` | Пример `.env` для production. |
