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

1. Полигоны микрорайонов нарисовать в geojson.io, у каждого `properties.name` («12 мкр») и `properties.population`. Сохранить как `database/data/geodata/districts.geojson`.
2. Маршруты — линии в geojson.io, **вершина = остановка**, `properties.key` (`a`/`b`/`c`) и `properties.name`. Сохранить как `routes.geojson`.
3. Остановки из OpenStreetMap:

```bash
curl -s https://overpass-api.de/api/interpreter --data-urlencode 'data=[out:json][timeout:25];node["highway"="bus_stop"](43.60,51.08,43.73,51.28);out;' -o database/data/geodata/stops.json
```

4. `php artisan city:import-geodata database/data/geodata`

## Деплой (Railway)

1. New Project → Deploy from GitHub → `city-lab-api`; добавить PostgreSQL.
2. Variables: `APP_KEY` (`php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`,
   `DB_CONNECTION=pgsql`, `DB_URL=${{Postgres.DATABASE_URL}}`, `FRONTEND_URL=https://<vercel-домен>`,
   `OPENAI_API_KEY`, `OPENAI_MODEL`, `ADMIN_PASSWORD`, `CACHE_STORE=database`.
3. Pre-deploy command: `php artisan migrate --force`.
4. Один раз после первого деплоя (Railway → service → Shell): `php artisan db:seed --force`.
5. Проверка: `curl https://<railway-домен>/api/city`.
