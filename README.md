# FOTON Detailing & Paint

Laravel 13 / PHP 8.4 API and React SPA, with MySQL 8.4, Redis, Nginx, and Docker Compose.

## Local setup

Copy `.env.example` to `.env`, `backend/.env.example` to `backend/.env`, and `frontend/.env.example` to `frontend/.env`. These local environment files are ignored by Git. Set your own MySQL passwords in the root `.env`, then use matching credentials in `backend/.env`:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8080
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=foton_detailing_paint
DB_USERNAME=foton
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

Set `DB_PASSWORD` to the root `MYSQL_PASSWORD` value. Keep `SESSION_DOMAIN=null` for a host-only cookie on localhost, and use the same hostname for both applications: `localhost` on ports 5173/8080, or `127.0.0.1` on both ports with `FRONTEND_URL` changed accordingly. Configure HTTPS and secure session cookies for production.

The frontend uses `VITE_API_BASE_URL=http://localhost:8080/api`. Vite environment changes require restarting the frontend service.

```bash
docker compose build
docker compose run --rm backend composer install --no-interaction
docker compose run --rm frontend npm ci
docker compose up -d
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose ps
```

For a new local development account, set a strong `DEVELOPMENT_USER_PASSWORD` in `backend/.env` before seeding. The seeder creates `developer@foton-detailing.local` only in local/testing environments when this value is present. An existing development account keeps its password and receives the `super_admin` role. Production seeding creates the role and permission catalog without creating or promoting a privileged user.

Open <http://localhost:5173>. The API health endpoint is <http://localhost:8080/api/health>, and Laravel/Nginx is available at <http://localhost:8080>.

## Authentication and authorization

Sanctum authenticates the first-party SPA through Laravel session cookies. Login requests initialize CSRF protection with `GET /sanctum/csrf-cookie`, submit credentials to `POST /api/auth/login`, then load `GET /api/auth/me`. Axios sends credentials and the XSRF header. The browser manages the session cookie; authentication tokens are never stored in browser storage. Login rotates the session ID, applies throttling, and uses generic credential errors. `POST /api/auth/logout` invalidates the session and rotates the CSRF token.

The `/api/auth/*` endpoints are declared in `routes/web.php` under an API prefix, so Laravel's web middleware always starts the session and validates CSRF, including requests without a trusted SPA origin. Protected endpoints use `auth:sanctum`. The API middleware also enables Sanctum's `statefulApi()` for future module routes; authentication endpoints use one web session pipeline.

The SPA restores the session through `/api/auth/me` on startup and withholds protected content until that request completes. Protected routes redirect guests to `/login`; authenticated users land on `/dashboard`. The authenticated payload contains the user's ID, name, email, roles, and effective permissions. Frontend authorization helpers support interface decisions; backend Gate, middleware, and policies enforce access.

Spatie Permission uses the `web` guard, with centralized `App\Enums\Role` and `App\Enums\Permission` definitions and an idempotent `RolePermissionSeeder`. The initial roles are:

| Role | Baseline |
| --- | --- |
| `super_admin` | Every defined application permission through a centralized Gate bypass |
| `admin` | All application permissions |
| `supervisor` | Operational oversight, work order creation/update/assignment, dashboard, and audit |
| `operator` | Unit/work order viewing, time tracking, materials consumption, traceability, and training |
| `inspector` | Inspection execution, release, diagnosis/unit viewing, traceability, and dashboard |

The permission catalog uses `module.action` names across units, diagnosis, work orders, processes, time tracking, materials, inspection, release, traceability, training, dashboard, audit, and users. The seeder is the source of truth for role assignments. Middleware aliases `role`, `permission`, and `role_or_permission` are available.

`UserPolicy` provides the reference pattern: read operations require `users.view`, and management operations require `users.manage`. Future module policies should use the centralized permission enum and Laravel Gate checks. No domain tables or user-management endpoints are added for this foundation. Published package migrations provide Sanctum and Spatie tables; existing Laravel session tables hold cookie sessions.

SSO integration with LDRH Sys remains pending by product decision. No LDRH routes, adapters, or dependencies are included. Password reset, account provisioning screens, and business module screens belong to later work.

## Validation

```bash
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan test
docker compose exec backend vendor/bin/pint --test
docker compose exec backend php artisan route:list
docker compose exec frontend npm run lint
docker compose exec frontend npm run test -- --run
docker compose exec frontend npm run build
```

Tests isolate their database state from the development database. GitHub Actions runs backend migrations, tests, and Pint, plus frontend dependency installation, lint, tests, and production build. Local regression checks also cover the health endpoint, both HTTP entry points, database connectivity, Redis, and running worker/scheduler services.
