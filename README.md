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
| `supervisor` | Operational oversight, diagnosis creation/editing/catalog governance, work order creation/update/assignment, dashboard, and audit |
| `operator` | Unit/work order viewing, time tracking, materials consumption, traceability, and training |
| `inspector` | Diagnosis creation/editing, inspection execution, release, unit viewing, traceability, and dashboard; no catalog administration |

The permission catalog uses `module.action` names across units, diagnosis, work orders, processes, time tracking, materials, inspection, release, traceability, training, dashboard, audit, and users. The seeder is the source of truth for role assignments. Middleware aliases `role`, `permission`, and `role_or_permission` are available.

`UserPolicy` provides the reference pattern: read operations require `users.view`, and management operations require `users.manage`. Future module policies should use the centralized permission enum and Laravel Gate checks. No domain tables or user-management endpoints are added for this foundation. Published package migrations provide Sanctum and Spatie tables; existing Laravel session tables hold cookie sessions.

SSO integration with LDRH Sys remains pending by product decision. No LDRH routes, adapters, or dependencies are included. Password reset, account provisioning screens, and the remaining operational modules belong to later work.

## Units

Phase 3 adds the canonical `App\Models\Unit` model and one `units` table. Run the normal migrations when upgrading an existing checkout; no fake units are seeded. VIN has a unique database index, status/location/model have indexes for filters, and the nullable registering-user foreign key uses `nullOnDelete` so the unit survives user removal.

VIN is trimmed and uppercased consistently on registration, model writes, lookup, and exact VIN filtering. It must contain exactly 17 letters/digits excluding I, O, and Q; internal characters are never removed. VIN cannot be changed after registration. Duplicate submissions, including a database uniqueness race, return a VIN validation error with HTTP 422. The server assigns the creator and initial `registered` status; clients cannot supply either. Optional model and location values are trimmed and may be cleared with null/blank input.

All endpoints require Sanctum authentication and `UnitPolicy` authorization using the existing Phase 2 permissions:

| Method and endpoint | Permission | Behavior |
| --- | --- | --- |
| `GET /api/units` | `units.view` | Paginated resource collection with `data`, `links`, and `meta` |
| `POST /api/units` | `units.create` | Register a unit; returns HTTP 201 and `data` |
| `GET /api/units/meta` | `units.view` | Authoritative status values and Spanish labels |
| `GET /api/units/by-vin/{vin}` | `units.view` | Canonical VIN lookup; invalid VIN 422, unknown VIN 404 |
| `GET /api/units/{unit}` | `units.view` | Unit detail; missing ID 404 |
| `PATCH /api/units/{unit}` | `units.update` | Update model, status, and/or current location |

The API resource exposes VIN, model, status/label, current location, timestamps, and only the registering user's ID/name. Empty updates and any submitted VIN/creator update are rejected with 422. There is no delete endpoint or soft-delete workflow.

List filters are `search`, `vin`, `model`, `status`, and `location`. VIN/model/status/location match exactly; `search` performs a substring search across VIN, model, and location, combined with the other filters. `per_page` defaults to 20 and must be 1–100. `page` must be positive. Sorting accepts only `vin`, `model`, `status`, `current_location`, `created_at`, or `updated_at`, with `asc`/`desc` direction. The default is `created_at DESC, id DESC`; other sorts also use ID to break ties. Invalid query values return 422.

`UnitStatus` defines `registered`, `in_diagnosis`, `in_process`, `inspection`, `rework`, `ready_for_release`, `released`, and `blocked`. React obtains labels/options from the meta endpoint and resource rather than maintaining an independent status list. Any valid status can be selected in this phase; transition rules, status/location history, and plant catalogs are deferred.

The authenticated navigation provides Dashboard, Unidades, and logout. `/units` and `/units/:unitId` require `units.view`; `/units/new` requires `units.create`. The list supports direct VIN lookup, explicit filter application reflected in URL parameters, and server pagination. Detail editing requires `units.update`; viewers receive read-only details. Existing role-permission baselines are unchanged, so normal registration/edit access belongs to admin and Super Admin unless permissions are explicitly assigned separately. Frontend checks support UX; the API always enforces policies and CSRF protection.

## Diagnosis

Phase 4 adds `Diagnosis`, `Defect`, `DefectType`, and `DefectEvidence`. A unit can have multiple diagnoses; a diagnosis contains defects, each referencing a catalog type and optional private image evidence. Four new migrations preserve core history with restrictive foreign keys; removed user accounts leave nullable actor references. There are no delete endpoints, soft deletes, workflow assignments, or repair fields.

When upgrading an existing Phase 3 installation, rebuild the PHP services for GD/image support and upload limits, apply migrations, and run the authorization seeder:

```bash
docker compose up -d --build
docker compose exec nginx nginx -s reload
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
```

The seeder updates permissions only; it does not invent a production defect catalog, diagnoses, or defects. Configure the first active type through the catalog UI before recording a defect. An empty catalog provides an explicit configuration action for managers; searching types only finds existing active entries. Catalog administration returns to the diagnosis that opened it.

Diagnosis notes are optional plain text, trimmed, up to 5,000 characters. Defects require an active type, a trimmed physical vehicle location (160 characters), and an explicit `DefectPriority`: `low` / Baja, `medium` / Media, `high` / Alta, or `critical` / Crítica. Optional descriptions allow 5,000 plain-text characters. HTML is rejected. Defect location identifies the damaged vehicle area; `Unit.current_location` remains the vehicle's operational location. Neither diagnosis creation nor priority changes alter the Unit status or create Work Orders.

Catalog names are trimmed, unique, and limited to 120 characters; descriptions allow 2,000 characters. Types can be renamed, activated, or deactivated, but not deleted. Inactive types remain readable on existing defects. An existing defect can retain its current inactive type while its other fields are edited; inactive types cannot be newly assigned. Priority options come from the backend meta endpoint, not a separate React enum.

| API endpoint | Authorization |
| --- | --- |
| `GET /api/diagnoses` and `GET /api/diagnoses/{diagnosis}` | `diagnosis.view` |
| `GET /api/units/{unit}/diagnoses` | `diagnosis.view` plus access to the Unit context |
| `POST /api/units/{unit}/diagnoses` | `diagnosis.create` plus access to the Unit context |
| `PATCH /api/diagnoses/{diagnosis}` | `diagnosis.update` |
| `POST /api/diagnoses/{diagnosis}/defects` | `diagnosis.update` |
| `PATCH /api/diagnoses/{diagnosis}/defects/{defect}` | `diagnosis.update` |
| `GET /api/diagnosis/meta` | `diagnosis.view` |
| `GET /api/defect-types` | `diagnosis.view`; active types by default |
| `POST /api/defect-types` and `PATCH /api/defect-types/{defectType}` | `diagnosis.manage_catalog` |
| `POST /api/diagnoses/{diagnosis}/defects/{defect}/evidences` | `diagnosis.update` |
| `GET /api/diagnoses/{diagnosis}/defects/{defect}/evidences/{evidence}` | `diagnosis.view` |

All routes require `auth:sanctum`; writes retain the existing SPA CSRF protection. Nested bindings reject mismatched Diagnosis/Defect/Evidence combinations with 404. Actor IDs, parent IDs, disk names, and storage paths are assigned by the server rather than client mass assignment. API errors omit internal paths and debug traces while retaining authentication and validation responses.

Diagnosis and unit-history lists use Laravel pagination (default 20, maximum 100), `search` across VIN/model/notes, optional `unit_id`, `page`, and allowlisted `sort` (`created_at`, `updated_at`, `id`) / `direction`. Sorting has deterministic ID ties. Catalog lists are bounded (default/maximum 100), support name search and pagination, and expose inactive entries with `include_inactive=1` only to catalog managers. Operational defect forms offer catalog search when more types exist than fit in the current options.

The centralized permission catalog adds only `diagnosis.manage_catalog`. Admin receives all defined permissions and Super Admin continues through the central Gate bypass. Supervisor receives all four Diagnosis permissions; Inspector receives view/create/update, without catalog management. Operator's Diagnosis baseline is unchanged. After an upgrade, reload the SPA to obtain its current effective permission payload.

### Private evidence

`DIAGNOSIS_EVIDENCE_DISK=diagnosis_evidence` selects the dedicated private Laravel disk. Its default root is `storage/app/diagnosis-evidence`, outside the web root and the publicly served storage disk, with public serving disabled. Alternative disks must remain private and must not expose a public serving URL. Misconfigured public or web-served disks fail closed. Back up database metadata and evidence files together; do not move the evidence directory under `public` or add a public storage link to it.

Uploads accept multipart `files[]`: 1–5 JPEG/PNG/WEBP images, maximum 10 MiB per image. Extension, detected MIME, and image content are validated. SVG, PDF, scripts, executables, and disguised non-image content are rejected. The server uses random UUID filenames with content-derived extensions and records a sanitized original name only as metadata. A database transaction and explicit file cleanup prevent new orphan files after a failed or partial batch.

PHP accepts up to 52 MiB per request and Nginx enforces the matching body limit. PHP's file-count ceiling remains above five so Laravel can reject oversized batches instead of receiving a silently truncated list. The API enforces the actual five-file limit. Oversized requests receive friendly client feedback.

Evidence resources expose metadata, safe uploader ID/name, and an authenticated API view URL; they never expose `disk`, `path`, or host filesystem paths. Retrieval streams the image with inline disposition, private/no-store caching, and nosniff headers. React requests authenticated blobs, creates temporary object URLs, and revokes them when they are no longer displayed. No public filesystem URL is used, and no evidence deletion/replacement workflow is added.

The SPA adds `/diagnoses`, `/diagnoses/:diagnosisId`, `/units/:unitId/diagnoses`, `/units/:unitId/diagnoses/new`, and `/diagnosis/defect-types`. Unit detail links into Diagnosis without changing Unit editing. View-only users receive read-only content; catalog controls require catalog permission. Work Orders, repair execution, inspection/release approvals, status/location history, KPIs, and SSO LDRH remain outside this phase.

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
