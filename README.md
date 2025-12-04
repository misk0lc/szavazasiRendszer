# Szavazási rendszer fejlesztői napló

## Bevezetés

Ez a dokumentum a 13. évfolyamos vizsgaremekként készített szavazási rendszer teljes fejlesztői naplója. A rendszer Laravel 11 keretrendszerrel készült, Bearer token alapú (Laravel Sanctum) API-val, amely biztosítja, hogy minden felhasználó csak a saját nevében szavazhasson.

**Követelmények:**
- users(id, name, email, verified, created_at, updated_at)
- polls(id, question, description, options, closes_at, created_at, updated_at)
- votes(id, user_id, poll_id, selected_option, voted_at, created_at, updated_at)

**Üzleti szabályok:**
- Egy felhasználó egy szavazásban csak egyszer szavazhat
- Csak érvényes opcióra lehet szavazni
- Lezárt szavazásra nem lehet szavazni
- Autentikáció Bearer tokennel (Sanctum)

---

## 1. Adatbázis tervezés és migrációk

### 1.1 Users tábla

A felhasználók tárolásához a Laravel alapértelmezett users tábláját használjuk, kiegészítve Sanctum tokenkezeléssel.

**Migráció:** `database/migrations/0001_01_01_000000_create_users_table.php`

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```

**Model:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
```

**Indoklás:** A `HasApiTokens` trait biztosítja a Sanctum Bearer token kezelést. A jelszó automatikusan bcrypt hash-elődik a `hashed` cast miatt.

### 1.2 Polls tábla

A szavazások tárolására szolgáló tábla, ahol az `options` JSON tömbként tároljuk a választható opciókat.

**Migráció:** `database/migrations/2025_12_01_082506_create_polls_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('description')->nullable();
            $table->json('options');
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
```

**Model:** `app/Models/Poll.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    protected $fillable = [
        'question',
        'description',
        'options',
        'closes_at',
    ];

    protected $casts = [
        'options' => 'array',
        'closes_at' => 'datetime',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
```

**Indoklás:** Az `options` tömb JSON-ként tárolódik, ami rugalmas szerkezetet biztosít. A `closes_at` datetime cast lehetővé teszi az egyszerű időösszehasonlítást.

### 1.3 Votes tábla

A leadott szavazatok tárolása, egyedi korlátozással (egy user + egy poll = egy szavazat).

**Migráció:** `database/migrations/2025_12_01_082534_create_votes_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('poll_id')->constrained()->onDelete('cascade');
            $table->string('selected_option');
            $table->timestamp('voted_at')->useCurrent();
            $table->timestamps();
            
            // Ensure a user can only vote once per poll
            $table->unique(['user_id', 'poll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
```

**Model:** `app/Models/Vote.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $fillable = [
        'user_id',
        'poll_id',
        'selected_option',
        'voted_at',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }
}
```

**Indoklás:** A `unique(['user_id', 'poll_id'])` index adatbázis szinten garantálja, hogy egy felhasználó csak egyszer szavazhasson egy adott szavazásban. A `cascade` törlésnél automatikusan eltávolítja a szavazatokat is.

---

## 2. API autentikáció (Bearer token - Sanctum)

### 2.1 Regisztráció és bejelentkezés

**Controller:** `app/Http/Controllers/Api/AuthApiController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('default')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('default')->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
```

**Indoklás:** 
- A `createToken()` metódus Sanctum Bearer tokent generál, amit a kliens az `Authorization: Bearer {token}` headerben küld.
- A jelszó biztonságosan hash-elve van (`Hash::make`).
- A `logout()` metódus törli az aktuális tokent az adatbázisból, így az többé nem használható.
- 401-es válasz érvénytelen hitelesítés esetén.

Regisztráció:
<img width="1670" height="1005" alt="image" src="https://github.com/user-attachments/assets/efd3758d-adcf-4012-9c29-a816a14e03a0" />
Bejelentkezés:
<img width="1676" height="1006" alt="image" src="https://github.com/user-attachments/assets/2cade727-5354-4eba-8bc0-1f5fa8172338" />

---

## 3. Szavazások kezelése (Polls API)

### 3.1 Polls Controller

**Controller:** `app/Http/Controllers/Api/PollApiController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollApiController extends Controller
{
    public function index(): JsonResponse
    {
        $polls = Poll::orderByDesc('created_at')->get();
        return response()->json($polls);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'options.*' => ['string'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $poll = Poll::create([
            'question' => $data['question'],
            'description' => $data['description'] ?? null,
            'options' => array_values(array_filter($data['options'], fn($s) => trim($s) !== '')),
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        return response()->json($poll, 201);
    }

    public function show(Poll $poll): JsonResponse
    {
        return response()->json($poll);
    }

    public function results(Poll $poll): JsonResponse
    {
        $options = $poll->options ?? [];
        $counts = [];
        foreach ($options as $opt) {
            $counts[$opt] = $poll->votes()->where('selected_option', $opt)->count();
        }
        $total = array_sum($counts);
        return response()->json([
            'poll' => $poll,
            'counts' => $counts,
            'total' => $total,
        ]);
    }
}
```

**Indoklás:**
- `index()`: összes szavazás listázása, nyilvános végpont.
- `store()`: védett (Bearer token szükséges), minimum 2 opciót követel meg.
- `show()`: egyedi szavazás részletei.
- `results()`: összesítés, opciónként számolja a szavazatokat.

Szavazások kilistázása
<img width="1680" height="1009" alt="image" src="https://github.com/user-attachments/assets/83d877e8-0456-495d-9ee2-0c0b1fa9adfe" />

Egy szavazás kilistázása
<img width="1677" height="1003" alt="image" src="https://github.com/user-attachments/assets/7307b1cb-d79c-43f8-8536-6bcdb670ccba" />

Szavazás eredmények
<img width="1677" height="1006" alt="image" src="https://github.com/user-attachments/assets/428b1057-b429-4664-bbd5-d6611ba1a480" />




---

## 4. Szavazás leadása (Voting API)

### 4.1 Vote Controller

**Controller:** `app/Http/Controllers/Api/VoteApiController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteApiController extends Controller
{
    public function store(Request $request, Poll $poll): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'selected_option' => ['required', 'string'],
        ]);

        $isClosed = $poll->closes_at !== null && now()->greaterThan($poll->closes_at);
        if ($isClosed) {
            return response()->json(['message' => 'Poll is closed'], 422);
        }

        $options = $poll->options ?? [];
        if (!in_array($data['selected_option'], $options, true)) {
            return response()->json(['message' => 'Invalid option'], 422);
        }

        $already = $poll->votes()->where('user_id', $user->id)->exists();
        if ($already) {
            return response()->json(['message' => 'You already voted in this poll'], 422);
        }

        $vote = Vote::create([
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'selected_option' => $data['selected_option'],
            'voted_at' => now(),
        ]);

        return response()->json($vote, 201);
    }
}
```

**Validációs lépések:**
1. Felhasználó autentikáció (Bearer token)
2. Lezárt szavazás ellenőrzése (`closes_at`)
3. Érvényes opció ellenőrzése (benne van-e az `options` tömbben)
4. Duplikált szavazat ellenőrzése (DB query)

**Indoklás:** Minden szabály API szinten és adatbázis szinten (unique index) is ellenőrzött, dupla védelmet biztosítva.

---

## 5. API útvonalak (routes/api.php)

**Fájl:** `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\PollApiController;
use App\Http\Controllers\Api\VoteApiController;

// Auth API (Bearer tokens via Sanctum)
Route::post('/register', [AuthApiController::class, 'register']);
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/logout', [AuthApiController::class, 'logout'])->middleware('auth:sanctum');

// Public poll endpoints
Route::get('/polls', [PollApiController::class, 'index']);
Route::get('/polls/{poll}', [PollApiController::class, 'show']);
Route::get('/polls/{poll}/results', [PollApiController::class, 'results']);

// Protected endpoints (Bearer token required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/polls', [PollApiController::class, 'store']);
    Route::post('/polls/{poll}/vote', [VoteApiController::class, 'store']);
});
```

**Végpontok:**
- **Nyilvános:** register, login, polls listázása/részletek/eredmények
- **Védett (auth:sanctum):** logout, szavazás létrehozása, szavazat leadása

---

## 6. Tesztelés

### 6.1 Feature teszt: szavazás folyamat

**Fájl:** `tests/Feature/PollVotingTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollVotingTest extends TestCase
{
    use RefreshDatabase;

    protected function createSamplePoll(array $options = ['Igen', 'Nem'], ?string $closesAt = null): Poll
    {
        return Poll::create([
            'question' => 'Tetszik a rendszer?',
            'description' => 'Egyszerű teszt szavazás',
            'options' => $options,
            'closes_at' => $closesAt,
        ]);
    }

    public function test_can_create_poll_and_vote(): void
    {
        $user = User::factory()->create();
        $poll = $this->createSamplePoll();

        $this->actingAs($user);
        $resp = $this->post(route('polls.vote', $poll), [
            'selected_option' => 'Igen',
        ]);

        $resp->assertRedirect(route('polls.results', $poll));
        $this->assertDatabaseHas('votes', [
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'selected_option' => 'Igen',
        ]);
    }

    public function test_cannot_vote_twice_in_same_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createSamplePoll();

        $this->actingAs($user);
        $this->post(route('polls.vote', $poll), [
            'selected_option' => 'Igen',
        ])->assertRedirect(route('polls.results', $poll));

        // second attempt should fail with validation error
        $this->from(route('polls.show', $poll))
            ->post(route('polls.vote', $poll), [
                'selected_option' => 'Nem',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('votes', 1);
    }

    public function test_cannot_vote_on_closed_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createSamplePoll(['A', 'B'], now()->subHour()->toDateTimeString());

        $this->actingAs($user);
        $this->from(route('polls.show', $poll))
            ->post(route('polls.vote', $poll), [
                'selected_option' => 'A',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('votes', 0);
    }
}
```

**Teszt lefedettség:**
1. Sikeres szavazás flow
2. Duplikált szavazat megakadályozása
3. Lezárt szavazásra tiltás

**Futtatás:**
```bash
php vendor/phpunit/phpunit/phpunit
```

---

## 7. Telepítés és indítás

### 7.1 Környezet előkészítése

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 7.2 Adatbázis konfiguráció

**.env fájl (MySQL):**
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=szavazasiRendszer
DB_USERNAME=root
DB_PASSWORD=
```

**Migrációk futtatása:**
```bash
php artisan migrate
```

**Megjegyzés:** Ha a MySQL kapcsolat nem működik (XAMPP nem fut), használható SQLite alternatíva:

```bash
# Create SQLite database
New-Item -ItemType File -Path database/database.sqlite -Force

# Update .env
# DB_CONNECTION=sqlite

# Run migrations
php artisan migrate
```

### 7.3 Szerver indítása

```bash
php artisan serve
```

API elérhető: `http://127.0.0.1:8000/api`

**Megjegyzés:** Ez egy tisztán API-alapú alkalmazás, nincs webes felület. Használj Postman-t vagy más API klienst a végpontok teszteléséhez.

---

## 8. API használat (példák)

### 8.1 Regisztráció

```bash
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Teszt Elek","email":"teszt@example.com","password":"secret123"}'
```

**Válasz:**
```json
{
  "token_type": "Bearer",
  "access_token": "1|randomtokenstring..."
}
```

### 8.2 Kijelentkezés

```bash
curl -X POST http://127.0.0.1:8000/api/logout \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Válasz:**
```json
{
  "message": "Successfully logged out"
}
```

### 8.3 Szavazás létrehozása

```bash
curl -X POST http://127.0.0.1:8000/api/polls \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"question":"Tetszik?","options":["Igen","Nem"]}'
```

### 8.4 Szavazat leadása

```bash
curl -X POST http://127.0.0.1:8000/api/polls/1/vote \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"selected_option":"Igen"}'
```

### 8.5 Eredmények lekérése

```bash
curl http://127.0.0.1:8000/api/polls/1/results
```

**Válasz:**
```json
{
  "poll": {"id":1,"question":"Tetszik?","options":["Igen","Nem"]},
  "counts": {"Igen":5,"Nem":3},
  "total": 8
}
```

---

## 9. Postman kollekció

**Hely:** `docs/postman/szavazasiRendszer.postman_collection.json`

Importáld Postmanbe, és állítsd be a következő változókat:
- `base_url`: `http://127.0.0.1:8000`
- `token`: (automatikusan töltődik a Login végpont futtatása után)
- `pollId`: `1` (vagy bármely létező szavazás ID)

**Tesztelési lépések:**
1. **Auth / Register vagy Login** – token megszerzése (automatikusan beállítja a token változót)
2. **Polls (protected) / Create poll** – szavazás létrehozása
3. **Polls (public) / List polls** – ID azonosítása
4. **Polls (protected) / Vote** – szavazat leadása
5. **Polls (public) / Results** – eredmények ellenőrzése
6. **Auth / Logout** – kijelentkezés (üresíti a token változót)

Szavazás létrehozása
<img width="1675" height="1005" alt="image" src="https://github.com/user-attachments/assets/27dcbaf8-814c-4c24-9170-4e97149cbaf2" />

Szavazás
<img width="1682" height="1009" alt="image" src="https://github.com/user-attachments/assets/4c8df236-12c3-4f16-8383-b9599d2b6780" />



**Automatikus token capture példa (Login végpont teszt szkript):**

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has token", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('access_token');
    pm.collectionVariables.set("token", jsonData.access_token);
});
```

---

## 10. Hibaelhárítás

### 10.1 MySQL kapcsolódási hiba

**Hiba:** `SQLSTATE[HY000] [2002] No connection could be made`

**Megoldás:**
1. Ellenőrizd, hogy a XAMPP MySQL szolgáltatása fut-e
2. Nézd meg a helyes portot (alapértelmezett 3306, de lehet 3307)
3. Konfiguráld a `.env` fájlt:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=szavazasiRendszer
```
4. Töröld a cache-t: `php artisan config:clear`
5. Ha még mindig nem működik, váltsd SQLite-ra (lásd 7.2)

### 10.2 Token nem működik

**Hiba:** `401 Unauthorized` válasz védett végpontoknál

**Megoldás:**
1. Ellenőrizd, hogy a token helyes formátumban van-e elküldve:
```
Authorization: Bearer YOUR_ACTUAL_TOKEN
```
2. Ne használj `Bearer` prefix-et magában a token értékében (csak a headerben)
3. Ellenőrizd, hogy a Sanctum middleware megfelelően be van-e állítva

### 10.3 Duplikált szavazat hiba nem jelenik meg

**Hiba:** Többször is le lehet adni szavazatot ugyanazzal a userrel

**Ellenőrzés:**
1. Migráció futott-e (`unique(['user_id', 'poll_id'])`)
2. VoteApiController ellenőrzi-e az `exists()` feltételt
3. Adatbázisban van-e a unique index:
```sql
SHOW INDEX FROM votes WHERE Key_name = 'votes_user_id_poll_id_unique';
```

---

## 11. Összefoglalás

Ez a vizsgaremek egy működő, tisztán API-alapú szavazási rendszer Bearer token autentikációval, amely teljesíti a követelményeket:

✅ **Adatmodell:** users, polls (JSON options), votes (unique constraint)  
✅ **Autentikáció:** Laravel Sanctum Bearer token  
✅ **Üzleti logika:** egy user = egy szavazat/poll, lezárt poll tiltás, érvényes opció ellenőrzés  
✅ **API:** RESTful végpontok (nyilvános + védett)  
✅ **Tesztek:** PHPUnit feature tesztek  
✅ **Dokumentáció:** Postman kollekció, API példák  

**Használt technológiák:**
- Laravel 11
- Laravel Sanctum (Bearer tokens)
- MySQL / SQLite
- PHPUnit
- Postman

**Jövőbeli fejlesztési lehetőségek:**
- Email verifikáció (már előkészítve a users táblában)
- Token lejárat kezelése
- Szavazás szerkesztése (csak létrehozó által)
- Real-time eredményfrissítés
- Admin felület statisztikákkal
- Rate limiting (szavazási gyakoriság korlátozása)
