<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Реальная геометрия Актау

1. Полигоны микрорайонов: `districts.geojson` собран из OSM (`place=neighbourhood|quarter|city_block`, 57 нумерованных мкр, имена вида «12 мкр»). `properties.population` в OSM нет — дописать вручную.
2. Маршруты — линии в geojson.io, **вершина = остановка**, `properties.key` (`a`/`b`/`c`) и `properties.name`. Сохранить как `routes.geojson`.
3. Остановки из OpenStreetMap:

```bash
curl -sf -A 'city-lab-api/1.0' https://overpass-api.de/api/interpreter --data-urlencode 'data=[out:json][timeout:25];node["highway"="bus_stop"](43.60,51.08,43.73,51.28);out;' -o database/data/geodata/stops.json
```

4. `php artisan city:import-geodata database/data/geodata`
5. `php artisan city:fill-demo-metrics --population` — демо-метрики и оценка населения по площади районов
6. `php artisan city:snap-routes b c` — провести маршруты Б и В по дорогам (нужен доступ к OSRM)

## Деплой (Railway)

1. New Project → Deploy from GitHub → `city-lab-api`; добавить PostgreSQL.
2. Variables: `APP_KEY` (`php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`,
   `DB_CONNECTION=pgsql`, `DB_URL=${{Postgres.DATABASE_URL}}`, `FRONTEND_URL=https://<vercel-домен>`,
   `OPENAI_API_KEY`, `OPENAI_MODEL`, `ADMIN_PASSWORD`, `CACHE_STORE=database`.
3. Pre-deploy command: `php artisan migrate --force`.
4. Один раз после первого деплоя (Railway → service → Shell): `php artisan db:seed --force`.
5. Проверка: `curl https://<railway-домен>/api/city`.

## Деплой на сервер (Ubuntu + Docker)

Для Ubuntu 22.04 / 24.04. Все контейнеры слушают только `127.0.0.1`, наружу проект отдаёт nginx хоста с HTTPS.

### 1. Docker и файрвол

```bash
sudo apt update && sudo apt install -y git nginx certbot python3-certbot-nginx
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # перелогиниться после этого
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

### 2. Код

```bash
sudo mkdir -p /opt/city-lab-api && sudo chown $USER: /opt/city-lab-api
git clone <url-репозитория> /opt/city-lab-api
cd /opt/city-lab-api
```

Если `database/data/geodata/` не закоммичена, скопировать её с локальной машины:
`scp -r database/data/geodata user@server:/opt/city-lab-api/database/data/`.

### 3. `.env`

```bash
cp .env.example .env
nano .env
```

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.kz

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=city_lab_db
DB_USERNAME=city_lab_user
DB_PASSWORD=<длинный-случайный-пароль>   # openssl rand -hex 24

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync

FRONTEND_URL=https://city-lab.example.kz   # точный origin фронта, без / на конце (CORS)
ADMIN_PASSWORD=<пароль-акимата>
OPENAI_API_KEY=...
OPENAI_MODEL=...
```

`DB_*` читает и `docker-compose.yml`: Postgres создаётся с этими же логином и паролем. Менять `DB_PASSWORD` нужно **до** первого `up` — потом он уже записан в volume.

Порт nginx-контейнера по умолчанию `8091`; если занят — `NGINX_PORT=8095` в `.env`.

### 4. Первый запуск

```bash
docker compose up -d --build
docker compose exec php composer install --no-dev --optimize-autoloader
docker compose exec php php artisan key:generate --force
sudo chown -R 33:33 storage bootstrap/cache   # 33 = www-data внутри контейнера

A="docker compose exec -u www-data php php artisan"
$A migrate --force
$A db:seed --force                                    # только один раз!
$A city:import-geodata database/data/geodata          # реальные районы/остановки/маршруты
$A city:fill-demo-metrics --population               # демо-метрики + население по площади
$A city:snap-routes b c                               # маршруты Б и В по дорогам
$A config:cache && $A route:cache
curl -s http://127.0.0.1:8091/api/city | head -c 200  # должен вернуться JSON
```

### 5. Домен и HTTPS

DNS: A-запись `api.example.kz` → IP сервера. Затем:

```bash
sudo tee /etc/nginx/sites-available/city-lab-api >/dev/null <<'EOF'
server {
    listen 80;
    server_name api.example.kz;
    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:8091;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF
sudo ln -s /etc/nginx/sites-available/city-lab-api /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api.example.kz   # сертификат + автопродление
```

Проверка: `curl https://api.example.kz/api/city`.

### Обновление

```bash
cd /opt/city-lab-api && git pull
docker compose up -d --build
docker compose exec php composer install --no-dev --optimize-autoloader
A="docker compose exec -u www-data php php artisan"
$A migrate --force && $A config:cache && $A route:cache
```

Поменяли `.env` — повторить `$A config:cache`.

### Обслуживание

- Логи Laravel: `storage/logs/laravel.log`; контейнеров: `docker compose logs -f php nginx`.
- Бэкап БД: `docker compose exec -T postgres pg_dump -U city_lab_user city_lab_db | gzip > backup-$(date +%F).sql.gz`.
- Восстановление: `gunzip -c backup.sql.gz | docker compose exec -T postgres psql -U city_lab_user city_lab_db`.
- После перезагрузки сервера контейнеры поднимаются сами (`restart: unless-stopped`).
