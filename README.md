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
| `GET /api/diagnoses/{diagnosis}/defects/{defect}/evidences/{evidence}` | `diagnosis.view`, or Work Order view for a source Diagnosis that has a Work Order |

All routes require `auth:sanctum`; writes retain the existing SPA CSRF protection. Nested bindings reject mismatched Diagnosis/Defect/Evidence combinations with 404. Actor IDs, parent IDs, disk names, and storage paths are assigned by the server rather than client mass assignment. API errors omit internal paths and debug traces while retaining authentication and validation responses.

Diagnosis and unit-history lists use Laravel pagination (default 20, maximum 100), `search` across VIN/model/notes, optional `unit_id`, `page`, and allowlisted `sort` (`created_at`, `updated_at`, `id`) / `direction`. Sorting has deterministic ID ties. Catalog lists are bounded (default/maximum 100), support name search and pagination, and expose inactive entries with `include_inactive=1` only to catalog managers. Operational defect forms offer catalog search when more types exist than fit in the current options.

The centralized permission catalog adds only `diagnosis.manage_catalog`. Admin receives all defined permissions and Super Admin continues through the central Gate bypass. Supervisor receives all four Diagnosis permissions; Inspector receives view/create/update, without catalog management. Operator's Diagnosis baseline is unchanged. After an upgrade, reload the SPA to obtain its current effective permission payload.

### Private evidence

`DIAGNOSIS_EVIDENCE_DISK=diagnosis_evidence` selects the dedicated private Laravel disk. Its default root is `storage/app/diagnosis-evidence`, outside the web root and the publicly served storage disk, with public serving disabled. Alternative disks must remain private and must not expose a public serving URL. Misconfigured public or web-served disks fail closed. Back up database metadata and evidence files together; do not move the evidence directory under `public` or add a public storage link to it.

Uploads accept multipart `files[]`: 1–5 JPEG/PNG/WEBP images, maximum 10 MiB per image. Extension, detected MIME, and image content are validated. SVG, PDF, scripts, executables, and disguised non-image content are rejected. The server uses random UUID filenames with content-derived extensions and records a sanitized original name only as metadata. A database transaction and explicit file cleanup prevent new orphan files after a failed or partial batch.

PHP accepts up to 52 MiB per request and Nginx enforces the matching body limit. PHP's file-count ceiling remains above five so Laravel can reject oversized batches instead of receiving a silently truncated list. The API enforces the actual five-file limit. Oversized requests receive friendly client feedback.

Evidence resources expose metadata, safe uploader ID/name, and an authenticated API view URL; they never expose `disk`, `path`, or host filesystem paths. Retrieval streams the image with inline disposition, private/no-store caching, and nosniff headers. React requests authenticated blobs, creates temporary object URLs, and revokes them when they are no longer displayed. No public filesystem URL is used, and no evidence deletion/replacement workflow is added.

The SPA adds `/diagnoses`, `/diagnoses/:diagnosisId`, `/units/:unitId/diagnoses`, `/units/:unitId/diagnoses/new`, and `/diagnosis/defect-types`. Unit detail links into Diagnosis without changing Unit editing. View-only users receive read-only content; catalog controls require catalog permission. Work Orders, repair execution, inspection/release approvals, status/location history, KPIs, and SSO LDRH remain outside this phase.

## Work Orders

Phase 5 adds the canonical `WorkOrder`, generated explicitly from one Diagnosis. A database unique foreign key allows at most one Work Order per Diagnosis; a Unit can have many through its diagnoses. The additive migration protects source history and uses nullable user foreign keys. No production jobs or operators are seeded. Upgrade with `php artisan migrate`; existing role baselines and permission names are unchanged.

Generation requires `work_orders.create` and source Diagnosis access, rejects diagnoses without defects, records the session creator, and starts in `queued`. Initial `WorkOrderPriority` is the highest source defect priority: `low` / Baja (1), `medium` / Media (2), `high` / Alta (3), `critical` / Crítica (4). This is a snapshot: later defect changes do not rewrite Work Order priority. Generation does not mutate the Diagnosis, Defects, evidence, or Unit status. Duplicate retries/concurrent requests receive a friendly 422; transactions and row locks complement database uniqueness.

`WorkOrderStatus` defines the lifecycle:

| Current status | Allowed destinations |
| --- | --- |
| `queued` / En cola | `in_progress`, `blocked`, `cancelled` |
| `in_progress` / En proceso | `blocked`, `completed`, `cancelled` |
| `blocked` / Bloqueada | `queued`, `in_progress`, `cancelled` |
| `completed` / Completada | Terminal |
| `cancelled` / Cancelada | Terminal |

Entering `in_progress` requires an assignee. Assignment is separate from status: a queued order may already have an operator. Only users holding the `operator` role may be assigned. Assignment/reassignment records the session assigning user and current timestamp; unassignment records the actor and clears the assignment timestamp. An in-progress order cannot be left unassigned. Completed/cancelled orders reject status, assignment, priority, and notes changes. There is no reopening or deletion workflow. Mutations reload and lock the current row before enforcing these rules.

| API endpoint | Authorization |
| --- | --- |
| `POST /api/diagnoses/{diagnosis}/work-order` | `work_orders.create` plus Diagnosis view |
| `GET /api/work-orders` | `work_orders.view` |
| `GET /api/work-orders/queue` | `work_orders.view` |
| `GET /api/work-orders/meta` | `work_orders.view` |
| `GET /api/work-orders/operators` | `work_orders.assign` |
| `GET /api/work-orders/{workOrder}` | `work_orders.view` |
| `PATCH /api/work-orders/{workOrder}` | `work_orders.update`; priority/notes only |
| `PATCH /api/work-orders/{workOrder}/status` | `work_orders.update` |
| `PATCH /api/work-orders/{workOrder}/assignment` | `work_orders.assign` |

All endpoints require Sanctum sessions and retain SPA CSRF protection. Client-supplied parent IDs, creator, assignment actor/timestamp, and fields belonging to another mutation workflow are rejected. Notes are trimmed plain text, nullable, maximum 5,000 characters. Empty general updates return 422. Resources expose only safe actor ID/name, source Unit/Diagnosis information, status/priority labels and current allowed transitions.

List and queue filters: `search` across Unit VIN/model and Work Order/Diagnosis notes, `status`, `priority`, `assignment=assigned|unassigned`, `assigned_to`, `page`, and `per_page` (default 20, maximum 100). General listing accepts allowlisted `sort=created_at|updated_at|status|priority` and `direction=asc|desc`, defaulting to newest first with ID ties. Priority sorting uses enum ranking. The active queue excludes completed/cancelled orders and always orders by priority descending, then creation time ascending, then ID ascending. Queue reads have no write side effects. Operator lookup returns ID/name only, with bounded pagination and optional name search.

The SPA adds `/work-orders`, `/work-orders/queue`, and `/work-orders/:workOrderId`, guarded by `work_orders.view`. Diagnosis detail exposes explicit generation with a refreshed server priority preview or a link to the existing order. Trabajos provides list/queue navigation, URL-backed filters, “Mis trabajos”, assignment search, permitted status transitions, and active-order priority/notes editing. Options and transition rules come from backend metadata/resources.

Supervisor retains view/create/update/assign; Operator retains view only; Inspector gains no Work Order permissions. Operators can read the necessary source Diagnosis/Defects inside Work Order detail. The existing authenticated evidence endpoint additionally allows `work_orders.view` only when that Diagnosis has a Work Order; it does not grant general Diagnosis browsing/editing, catalog access, or evidence upload. Nested evidence binding and private storage protections remain unchanged.

No automatic Unit status transitions, Process sequencing, Time Tracking, Material consumption, Inspection workflow, notifications, audit timeline, or SSO LDRH are implemented in Phase 5.


## Processes, materials and inspection

Phases 6, 8 and 9 extend the operational container without implementing Phase 7 Time Tracking. Upgrade existing Phase 5 installations with `docker compose exec backend php artisan migrate`. The additive operations migration creates the domain tables, history-preserving foreign keys, and the nullable Work Order current-step pointer. It adds no production master data, operators, checklists or operational records. Existing role grants and permission names are unchanged.

### Process catalog and sequence

`Process` codes are trimmed/uppercased, unique and limited to 80 characters; names allow 160 characters and descriptions 5,000 plain-text characters. Records are activated/deactivated rather than deleted. Remove a process from the global sequence before deactivating it.

`ProcessSequenceStep` defines up to 100 ordered, distinct active processes. A transaction replaces the configuration without temporary unique-position collisions. A dedicated `operation_mutexes` infrastructure row serializes sequence replacement, catalog changes and snapshot reads, including an empty configuration; it is not business master data.

New Work Orders snapshot the configured sequence into `WorkOrderProcessStep`, preserving process code/name and position. Existing snapshots never change when the catalog is renamed or the global sequence is reordered. The first snapshot step becomes `currentProcessStep`. The next step is the first greater position within that same snapshot; the final step has no next step. Generation without a sequence still succeeds with `process_flow_initialized=false` and no pointer. Existing Work Orders can be explicitly initialized once by a process manager, including historical orders; initialization changes sequence metadata only.

| API endpoint | Authorization |
| --- | --- |
| `GET /api/processes`, `GET /api/processes/{process}` | `processes.view` |
| `POST /api/processes`, `PATCH /api/processes/{process}` | `processes.manage` |
| `GET /api/process-sequence` | `processes.view` |
| `PUT /api/process-sequence` with `process_ids` | `processes.manage` |
| `GET /api/work-orders/{workOrder}/process-flow` | Work Order visibility |
| `POST /api/work-orders/{workOrder}/process-flow/initialize` | `processes.manage` |

`/processes` provides catalog search, pagination, activation/editing and explicit sequence ordering. Work Order detail displays the current/next process and historical sequence. No process advancement, activity, pause/resume, duration or timer API is implemented; Phase 7 can later attach timing records to the existing step IDs.

### Materials, inventory and consumption

`Material` retains its unique uppercase code, name, description, active flag and optional `warehouse_reference`. Inventory adds nullable `minimum_stock` / `maximum_stock`, physical `location`, `supplier` and notes. Parameters are nonnegative and maximum must be at least minimum when both exist. Location belongs to the component; destination belongs to a movement. There is no directly editable stock field or material deletion API. The canonical unit cannot change after any inventory or consumption history.

Backend metadata defines the eight canonical units `Kg, L, ml, Pza, gr, cm, mts, hojas`, movement types and destinations `Pintura 1, Pintura 2, Lavado`. Compatible conversions use exact BCMath arithmetic: L↔ml and Kg↔gr at 1000, mts↔cm at 100, and identity conversions for Pza/hojas. All inventory quantities use `DECIMAL(18,6)`, with at most 12 integer and 6 fractional digits. Send quantities as decimal JSON strings (integers are also accepted); floating-point JSON numbers, exponential notation, incompatible dimensions, overflow and conversions requiring more than six canonical decimals are rejected rather than rounded.

`InventoryMovement` is the immutable stock ledger. `InventoryBalance` is its reconcilable query cache, never an independent source of stock. A unique opening movement can precede all other movements; entries and positive adjustments increase stock, exits and negative adjustments decrease it. Adjustments require a reason. Confirmed movements preserve original quantity/unit, converted/signed quantity, material snapshots, stock before/after, session actor and server time. Corrections use compensating movements; there are no PATCH/DELETE movement endpoints.

A single domain service authorizes, locks the Work Order when applicable, locks the Material/balance, converts, rejects negative stock, allocates the folio and commits the movement/balance/optional consumption together. A drifted or missing nonzero balance fails closed until reconciliation. Folios are server-generated `LDR-DP-0001`, increasing beyond four digits when necessary, protected by a locked transactional sequence and unique database constraint. No MAX+1 or client folio is accepted. Rolled-back allocations are not committed; committed folios are never renumbered/reused. Any future sequence gaps must be retained, not filled by reusing historical numbers.

Every movement and Work Order consumption POST requires a UUID `idempotency_key`. Retry the same intent with the same key: HTTP 201 creates it, HTTP 200 returns the existing receipt with `idempotent_replay=true`. Reusing a key with another payload, actor or context returns 422. React retains the key for retries, including uncertain network/5xx outcomes. Confirmation recalculates the backend preview under locks; preview does not reserve stock.

Stock is opening + entries + positive adjustments − exits − negative adjustments. States use this precedence: missing minimum or maximum → SIN PARÁMETROS; below minimum → CRÍTICO; above maximum → SOBRESTOCK; minimum through 120% of minimum inclusive → REORDEN; otherwise OK. Percentage is stock/max × 100 when max > 0, otherwise null. The backend supplies labels and recommended actions; React does not calculate stock or conversion arithmetic.

| API endpoint | Authorization |
| --- | --- |
| `GET /api/materials`, `GET /api/materials/{material}` | `materials.view` |
| `POST /api/materials`, `PATCH /api/materials/{material}` | `materials.manage` |
| `GET /api/materials/meta`, `GET /api/materials/inventory/dashboard` | `materials.view` |
| `GET /api/materials/{material}/stock`, `GET /api/materials/{material}/movements` | `materials.view` |
| `GET /api/materials/{material}/quantity-preview` | `materials.view` |
| `GET /api/inventory-movements`, `GET /api/inventory-movements/{movement}` | `materials.view` |
| `POST /api/inventory-movements` | `materials.manage`, or `materials.consume` for exits only |
| `GET /api/material-consumptions`, `GET /api/work-orders/{workOrder}/material-consumptions` | `materials.view` |
| `POST /api/work-orders/{workOrder}/material-consumptions` | `materials.consume` plus Work Order visibility |
| `GET /api/integrations/warehouse/v1/materials`, `GET /api/integrations/warehouse/v1/material-consumptions` | `materials.manage` |

Movement POST fields: `material_id, movement_type, quantity_original, unit_original, area_destination?, notes?, idempotency_key`. Types are `opening_balance, entry, exit, adjustment_in, adjustment_out`. Movement lists filter by material, type, destination, code/name/folio search and inclusive UTC calendar dates `date_from/date_to`. Sorting is allowlisted; pagination defaults to 20, maximum 100. Material lists additionally filter by `stock_status`, activity and canonical unit.

Consumption accepts `material_id, quantity, unit_original?, work_order_process_step_id?, area_destination?, notes?, external_reference?, idempotency_key`. An omitted original unit uses the material canonical unit. Only active materials and non-terminal orders are accepted. Optional process steps must belong to the order. Exactly one linked inventory exit and one MaterialConsumption are persisted atomically, protected by a unique movement link. Consumption stores canonical quantity; its movement preserves the original input. Stock counts the movement once and never separately subtracts consumption. VIN remains derived through Work Order → Diagnosis → Unit, without an editable duplicate VIN. Material use does not change inspection/release status.

Warehouse v1 retains `contract_version: "1"`, stable IDs, references, pagination and inclusive UTC `updated_since`. Material resources now include calculated stock/parameters/status; movements touch the material timestamp so incremental consumers receive balance changes. Consumption includes its linked movement or explicit `inventory_tracking=legacy_untracked`. Consumers should upsert by stable ID and tolerate repeated boundary records. These are authenticated read contracts only: external connectivity, machine authentication and stock synchronization remain deferred.

The additive inventory migration normalizes known legacy unit aliases and widens consumption precision. Existing consumption snapshots remain unchanged with a null movement link and the legacy label. It does not fabricate historical stock, movements or spreadsheet sample data. Establish verified real opening stock explicitly; legacy consumption must not be deducted again.

Reconcile the cache using `docker compose exec backend php artisan inventory:reconcile` (nonzero exit on mismatch). Scope with `--material=ID`; add `--repair` to rebuild cached quantity and component totals from the exact immutable ledger, without adding movements. Back up database history before an upgrade. Rolling back inventory removes the new ledger/schema and narrows quantity precision; it is not an operational correction workflow.

The SPA provides `/materials` inventory/catalog, `/materials/movements`, `/materials/movements/new`, immutable movement detail, `/materials/inventory/dashboard` and `/materials/consumptions`. Inventory has server search/status filters; movements have component/date/type/destination filters. The dashboard shows all five states and action alerts, refreshed after mutations and on window focus. Movement and Work Order forms display read-only backend equivalence, compatible units and available stock, then the confirmed server folio/actor/time/balance. Permission-specific controls retain authoritative backend policies.

### Inspection, automatic rework and release

Only completed Work Orders can receive inspections. Inspector users select completed orders through `GET /api/inspection/work-orders` using `inspection.execute`, without gaining general Work Order visibility or management permissions.

Checklists and criteria are configurable, activated/deactivated and never deleted through the API. Criteria have unique codes/positions per checklist, explicit required flags and transactional ordering. Inspection creation requires an active checklist with active criteria, locks the Work Order, and snapshots checklist name plus active criteria code/label/description/order/required flags. Subsequent catalog edits do not change inspection meaning. One draft per Work Order is protected by row locks and a unique generated draft key; multiple completed inspections are allowed.

Draft answers are `compliant` / Cumple or `non_compliant` / No cumple, with nullable plain-text comments up to 2,000 characters. Inspection notes allow 5,000 plain-text characters. Required unanswered criteria prevent completion. The server computes the overall result from required criteria: any required No cumple produces `failed`; otherwise it produces `passed`. Optional unanswered/non-compliant criteria do not override this rule.

Failed completion requires a trimmed plain-text `rework_cause` up to 5,000 characters. The same transaction creates exactly one `Rework` for that Inspection and sets Unit.status to `rework`. A later passed inspection resolves open reworks for its Work Order and sets the Unit to `ready_for_release`. Completed inspections are immutable; reinspection creates another record. These operations do not reopen a Work Order, generate another Work Order, change Diagnosis/Defects, or model corrective time/activity.

| API endpoint | Authorization |
| --- | --- |
| `GET /api/inspection/checklists`, `GET /api/inspection/checklists/{checklist}` | `inspection.view`, `inspection.execute` or `inspection.manage` |
| `POST /api/inspection/checklists`, `PATCH /api/inspection/checklists/{checklist}` | `inspection.manage` |
| `POST /api/inspection/checklists/{checklist}/items` | `inspection.manage` |
| `PATCH /api/inspection/checklists/{checklist}/items/{item}` | `inspection.manage`; scoped ownership |
| `PUT /api/inspection/checklists/{checklist}/item-order` with all `item_ids` | `inspection.manage` |
| `GET /api/inspections`, `GET /api/inspections/{inspection}` | `inspection.view` |
| `GET /api/work-orders/{workOrder}/inspections` | `inspection.view` |
| `POST /api/work-orders/{workOrder}/inspections` | `inspection.execute` |
| `PATCH /api/inspections/{inspection}` | `inspection.execute`; draft notes/items only |
| `POST /api/inspections/{inspection}/complete` | `inspection.execute`; optional/required cause determined by answers |
| `POST /api/inspections/{inspection}/release` | `release.execute` |

Draft PATCH accepts `items: [{id, result, comment}]`, where each ID belongs to that Inspection's snapshotted result rows. Creation accepts `inspection_checklist_id` and optional notes. Completion accepts only `rework_cause`; overall results, actors and timestamps are server controlled.

Release requires all six backend checks: completed Inspection, passed result, latest completed Inspection for its Work Order (completion time then ID), completed Work Order, no open Rework for that Work Order, and no previous release for that Inspection. The transaction creates `UnitRelease` with the session actor/time and sets the Unit to `released`. Inspection uniqueness and Unit/Work Order row locks protect concurrent creation/completion/release; duplicate attempts return clean 422 responses. Unit IDs are not globally unique in releases, allowing later lifecycle releases. The existing Phase 3 manual Unit editing API remains unchanged; formal release records can only be created through this validated workflow.

`/inspections`, `/inspections/:inspectionId`, `/inspections/new`, nested Work Order inspection routes, and `/inspection/checklists` provide listing, creation, configuration, execution, automatic rework display and release validation. The UI displays backend result options, required-answer summaries, historical failed criteria and release checks. Catalog and mutation actions are permission-specific; backend policies remain authoritative.

All operational lists use server pagination (default 20, maximum 100), safe sort allowlists and deterministic ID ties. Catalogs support search/activity filters; materials also filter by unit. Consumption supports VIN/work-order/search filters and inspections support Work Order/status/search filters. All writes retain Sanctum SPA sessions/CSRF, reject unsupported actor/parent fields and use safe API errors. Historical records use restrictive FKs; removed user accounts leave nullable actor references.

Phase 7 Time Tracking and Phase 10 generic traceability/event infrastructure remain deferred. No timer/activity tables, production Warehouse connection, generic timeline, notifications or SSO LDRH are added.


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
