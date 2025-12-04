<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Szavazási rendszer – Vizsgaremek (13.)

Egyszerű szavazási rendszer a következő táblákkal:

- `users(id, name, email, verified, created_at, updated_at)`
- `polls(id, question, description, options, closes_at, created_at, updated_at)`
- `votes(id, user_id, poll_id, selected_option, voted_at, created_at, updated_at)`

Funkciók:
- Szavazások listázása, létrehozása (kérdés, leírás, opciók, lezárás dátuma)
- Szavazólap megtekintése és szavazás leadása (felhasználó választásával)
- Eredmények megjelenítése (opciónkénti számlálás és százalék)
- Egy felhasználó csak egyszer szavazhat egy szavazásban
- Lezárt szavazásra nem lehet szavazni

### Futtatás (Windows / PowerShell)

1) Csomagok telepítése és környezet beállítása

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

2) Adatbázis beállítása (.env)

Állítsd be a saját DB kapcsolatod (pl. MySQL a XAMPP-ból), majd:

```powershell
php artisan migrate
```

3) Indítás

```powershell
php artisan serve
```

Ezután megnyitás: http://127.0.0.1:8000/polls

4) Tesztek futtatása

```powershell
php vendor/phpunit/phpunit/phpunit
```

### API – Bearer tokenes használat (Sanctum)

Szavazni API-n keresztül lehet, Bearer tokennel.

1) Regisztráció és token kérése:

```powershell
curl -X POST http://127.0.0.1:8000/api/register -H "Content-Type: application/json" -d '{"name":"Teszt Elek","email":"teszt@example.com","password":"secret123"}'
```

Vagy bejelentkezés tokenért:

```powershell
curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" -d '{"email":"teszt@example.com","password":"secret123"}'
```

Válaszban: `{"token_type":"Bearer","access_token":"..."}`

2) Szavazás leadása tokennel:

```powershell
curl -X POST http://127.0.0.1:8000/api/polls/1/vote -H "Authorization: Bearer YOUR_TOKEN" -H "Content-Type: application/json" -d '{"selected_option":"Igen"}'
```

3) Publikus végpontok:

- Lista: `GET /api/polls`
- Részletek: `GET /api/polls/{id}`
- Eredmények: `GET /api/polls/{id}/results`

4) Védett végpontok (Bearer szükséges):

- Létrehozás: `POST /api/polls` (body: question, description?, options[array], closes_at?)
- Szavazás: `POST /api/polls/{id}/vote`

Megjegyzés: A webes bejelentkezés/regisztráció nem használatos; a szavazás kizárólag Bearer tokennel történik API-n.

---

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
