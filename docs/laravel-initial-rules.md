# Laravel API + React SPA — marco de trabajo

Laravel vanilla + opiniones mínimas. Stack de referencia: \*\*Laravel 13 · React
SPA (Vite) · API JSON + Sanctum · MySQL · hosting compartido (PHP; Node solo
en build). No fijar majors EOL.

---

## Principio

Quedarse en las opiniones de Laravel. La única capa extra es **Actions**: clases
PHP planas con la lógica de negocio. No son un package; son una convención.

Un endpoint nuevo = esta receta, en este orden:

1. **Action** — lógica de negocio (`app/Actions/...`)
2. **Controller** — método HTTP que orquesta Request → Action → Resource
3. **FormRequest** + **API Resource** — validación/autorización y forma del JSON
4. **Ruta** en `routes/api.php` (con `->name()`)

Un mismo controller puede agrupar varios métodos (como un `apiResource`).

---

## Dónde va cada cosa

| Necesito…       | Va en…                                    |
| --------------- | ----------------------------------------- |
| Validar input   | `FormRequest` (`app/Http/Requests/...`)   |
| Autorizar       | `authorize()` del Request o `Policy`      |
| Lógica del caso | `Action::handle()` (`app/Actions/...`)    |
| Respuesta JSON  | `JsonResource` (`app/Http/Resources/...`) |
| Entrada HTTP    | método del `Controller`                   |
| Exponerlo       | `routes/api.php`                          |
| Schema DB       | migración (`database/migrations/`)        |

Controllers existen y se usan. No llevan lógica: reciben el request, llaman a la
Action, devuelven el Resource.

---

## Receta: `POST /api/posts`

```php
// app/Actions/Posts/CreatePost.php
namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\User;

class CreatePost
{
    public function handle(User $author, array $data): Post
    {
        return $author->posts()->create($data);
    }
}
```

```php
// app/Http/Controllers/Api/PostController.php
namespace App\Http\Controllers\Api;

use App\Actions\Posts\CreatePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    public function store(StorePostRequest $request, CreatePost $action): PostResource
    {
        $post = $action->handle($request->user(), $request->validated());

        return new PostResource($post);
    }
}
```

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    // o Route::apiResource('posts', PostController::class);
});
```

Comandos:

```bash
# Action: clase a mano en app/Actions/... (no hay make:action en Laravel core)
php artisan make:controller Api/PostController --api
php artisan make:request StorePostRequest
php artisan make:resource PostResource
php artisan make:test --feature Posts/CreatePostTest   # recomendado
```

### Actions (convención)

- Nombre verbo: `CreatePost`, `PublishOrder`, `InviteUser`.
- `handle()` recibe modelos/datos primitivos y devuelve el resultado.
  Sin `Request`, sin `response()`, sin HTTP → reutilizable desde comandos/seeders
  (y jobs si algún día hay worker).
- Varios writes en un caso de uso → envolver en `DB::transaction()` para no
  dejar estado a medias si falla a mitad.
- El controller es la frontera HTTP.
- Si una Action orquesta 3+ Actions, nombrar el proceso (`OnboardCustomer`), no
  esconder el flujo.

```php
use Illuminate\Support\Facades\DB;

public function handle(User $author, array $data): Post
{
    return DB::transaction(function () use ($author, $data) {
        $post = $author->posts()->create($data);
        $post->tags()->sync($data['tag_ids'] ?? []);

        return $post->fresh('tags');
    });
}
```

---

## Auth (Sanctum, API stateless)

```php
$token = $user->createToken('spa')->plainTextToken;          // login
$request->user()->currentAccessToken()->delete();            // logout
```

- `User` con `HasApiTokens`.
- Públicas fuera de `auth:sanctum`; el resto dentro.
- Front: token en header `Authorization: Bearer …` (vía cliente HTTP compartido).

---

## Frontend

Laravel sirve layouts y JSON; el routing de pantallas vive en React.

- Cliente HTTP compartido (`resources/js/lib/api.js`) con `baseURL: '/api'`.
- Preferir rutas nombradas en `api.php` (fuente de verdad, útil para docs y
  tooling). Evitar esparcir strings `/api/...` sueltos: centralizar en el cliente
  o en un módulo de endpoints.
- Opcional: [Wayfinder](https://laravel.com/docs/wayfinder) si querés URLs
  tipadas desde las rutas nombradas. No es requisito de este marco.

---

## Tests (recomendación)

Cobrir lo que cambia comportamiento: endpoints y Actions con lógica.

- Feature por endpoint: happy path, `422` validación, `401` si es privada.
- Unit/Feature directo a `handle()` cuando hay reglas no triviales.
- Factories + `RefreshDatabase`. SQLite en memoria para tests (`phpunit.xml`).

```bash
php artisan test
vendor/bin/pint --test
```

No es un gate de merge: es la forma más barata de no romper lo ya estabilizado.

---

## DB, sync y colas (hosting compartido)

- Schema solo vía migraciones. Nada a mano en phpMyAdmin.
- `utf8mb4` / `utf8mb4_unicode_ci` (default Laravel).
- **Sin worker persistente** (no Supervisor/Horizon en compartido): Jobs en cola,
  Events/`ShouldQueue` y Listeners en cola **pueden no correr** (o demorar hasta
  el próximo cron). No asumas “fire and forget”.
- **Recomendación:** ejecutar la Action **en sync dentro del mismo request**,
  terminar el trabajo y recién ahí responder. `QUEUE_CONNECTION=sync` es el
  default sensato en este entorno.
- Si un job es inevitable (email pesado, etc.): o lo hacés sync en el request, o
  documentás un `queue:work` vía cron y aceptás latencia/riesgo de acumulación.
- Varios writes → `DB::transaction()` (ver Actions arriba).
- Scheduler: un solo cron en el panel del hosting:

```
* * * * * php /ruta/al/proyecto/artisan schedule:run >> /dev/null 2>&1
```

---

## Deploy (Node solo en build)

```
máquina/CI:  npm run build  →  public/build/
             subir: app/, routes/, config/, database/, resources/views,
                    public/, vendor/, artisan, .env prod
hosting:     docroot → public/
```

```bash
npm run build
# --- subir ---
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Prod: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` real, `APP_URL` real.
Escritura en `storage/` y `bootstrap/cache/`.

---

## Checklist por cambio

1. Lógica → Action (`handle` sin HTTP).
2. HTTP → método de Controller + FormRequest + Resource + ruta en `api.php`.
3. DB → migración (+ factory/seeder si aplica).
4. Test Feature del endpoint cuando el cambio lo justifique.
5. `vendor/bin/pint` para estilo.
6. No versionar: `node_modules/`, `vendor/`, `.env`, `public/build/`.

---

## Comandos del día a día

```bash
composer dev                 # serve + queue + logs + vite (si está configurado)
php artisan serve            # API
npm run dev                  # Vite
php artisan test
php artisan migrate
php artisan make:controller Api/XController --api
php artisan make:request XRequest
php artisan make:resource XResource
php artisan make:model X -mf # modelo + migración + factory
```
