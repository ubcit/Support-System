# The Space Management — Roadmap: Next Phase

**Based on:** Comprehensive Production-Readiness Audit, August 3, 2026
**Current state:** UI/UX is a genuine strength (75/100), but overall production readiness is 42/100, mainly due to security (20/100).

---

## Phase 1 — Navigation/UI overhaul: ClickUp-style dual sidebar

**Estimated effort:** 1–2 weeks

Goal: replace current navigation with a two-sidebar system like ClickUp —
a thin icon-only primary rail, plus a wider collapsible secondary sidebar
showing a searchable Spaces → Projects → Lists tree.

### Layout spec

**1. Primary sidebar** (far left, ~56–64px, icon-only, fixed)
- Workspace/logo mark at top
- Global destinations pulled from `MenuHelper.php`: Home/Dashboard, My Tasks, Inbox (WhatsApp conversations), AI Center, Reports, Settings
- Clear active/selected state (background pill + accent color)
- Bottom: user avatar + settings icon

**2. Secondary sidebar** (~240–280px, collapsible)
- Content changes based on which primary icon is active
- For "Projects": search box to filter, "Favorites" section, collapsible tree (Space > Project > List/Board) using Alpine `x-data`/`x-show`
- "+ New project" action at the bottom
- Collapse state persists (localStorage via Alpine, or a user preference column if one already exists)

**3. Main content area**
- Breadcrumb (Space > Project) + view switcher tabs (Board/List/Calendar) at top
- Existing Kanban/List/Table/Calendar components render unchanged underneath

### Constraints
- Layout/navigation change only — no touching business logic, Eloquent models, `Modules/*` services, or the API layer
- Stack must stay Livewire 3 + Blade + Tailwind v4 + Alpine.js — no new frontend framework
- Reuse `app/Helpers/MenuHelper.php` as the source of truth for nav items
- Fully responsive: below `lg`, both sidebars collapse into a slide-over/hamburger pattern (fix the Employee Workspace's non-responsive `w-80` sidebar while in this area)
- Preserve dark mode via existing `dark:` variants and the `$store.theme` Alpine store
- No broken routes — verify against `routes/web.php` after changes
- Build as its own Livewire layout component (e.g. `AppShell`) wrapping existing pages, not edited page-by-page
- Don't fix unrelated bugs found along the way — log them for Phase 2 instead, to keep the change reviewable

### Agent prompt (ready to paste)

```
Redesign the app's navigation into a ClickUp-style dual-sidebar layout. This is a
layout/navigation change only — do not touch business logic, Eloquent models,
Modules/* services, or the API layer.

Stack constraints (must match existing app): Livewire 3 + Blade + Tailwind CSS v4
+ Alpine.js. No new frontend framework. Reuse app/Helpers/MenuHelper.php as the
source of truth for nav items instead of hardcoding a new menu list.

Layout spec:
1. Primary sidebar (far left, ~56-64px, icon-only, fixed):
   - Workspace/logo mark at top
   - Global destinations: Home/Dashboard, My Tasks, Inbox (WhatsApp conversations),
     AI Center, Reports, Settings — pull these from MenuHelper's top-level groups
   - Active icon gets a clear selected state (background pill + accent color)
   - Bottom: user avatar + settings icon

2. Secondary sidebar (next to it, ~240-280px, collapsible):
   - Shows content that depends on which primary icon is active
   - For the "Projects" context: a search box to filter, a "Favorites" section,
     and a collapsible tree: Space > Project > List/Board (use Alpine
     x-data/x-show for expand-collapse, persist expanded/collapsed state)
   - "+ New project" action at the bottom
   - Must be collapsible to icon-only width via a toggle, with the collapsed
     state remembered (localStorage via Alpine, or a user preference column if
     we already store user prefs — check first)

3. Main content area:
   - Breadcrumb (Space > Project) + view switcher tabs (Board/List/Calendar) at top
   - Existing Kanban/List/Table/Calendar components render unchanged underneath

Requirements:
- Fully responsive: below `lg`, both sidebars collapse into a slide-over/hamburger
  pattern (check how Employee Workspace currently handles its w-80 sidebar —
  audit flagged this as the weakest responsive spot, fix it here too)
- Preserve dark mode using the existing `dark:` variant patterns and
  `$store.theme` Alpine store — do not introduce a new theming approach
- Do not break any existing route — verify against routes/web.php after changes
- Do not fix unrelated bugs you notice while in these files (e.g. the Employee
  Workspace template-corruption bug at index.blade.php:133/137/139/142) —
  flag them to me separately, don't fix inline, so changes stay reviewable
- Keep the component boundary clean: build this as its own Livewire layout
  component (e.g. AppShell or similar) that wraps existing page components,
  rather than editing each page's blade file individually

Before writing code: read app/Helpers/MenuHelper.php, the current main layout
blade file, and one existing sidebar implementation (Employee Workspace) to
match existing conventions. Then propose the component structure and file list
before implementing, so I can approve it first.
```

---

## Phase 2 — Quick wins + live-breaking bugs

**Estimated effort:** ~2 days

Small, low-risk, high-visibility fixes — good to batch right after the nav work:

- [x] Fix the Employee Workspace template-corruption bug (garbled `id }}, 'in_progress')"` text rendering on screen)
- [x] Fix the AI Models "View" page badge props bug (badges render as empty pills)
- [x] Fix `BossWorkspaceController` — throws a fatal error on every call (`Task::whereHas('current_state_id', ...)`, a column not a relationship)
- [x] Add API rate limiting — `throttleApi()` is never called in `bootstrap/app.php`, so `/api/v1/auth/login` and `/register` are brute-forceable
- [x] Fix dead link on AI Prompts "View" page (`href="#"` where a route to `ai.schemas.view` should be)
- [x] Fix WhatsApp webhook config mismatch (`WHATSAPP_VERIFY_TOKEN` env-name issue breaks Meta's handshake)

---

## Phase 3 — Security foundation

**Estimated effort:** 2–3 weeks

This is what currently blocks the app from ever supporting multiple tenants or untrusted API clients:

- [x] **Add a real authorization layer.** Laravel Policies now exist for `Task`, `Project`, `Customer`, `Employee`, `Issue`, `Attachment`; wired via `Gate::policy()` and enforced in every `FormRequest::authorize()` and controller action.
- [x] **Wire RBAC into the new Policy layer.** `User::hasPermission()`/`hasAnyPermission()` read the permission matrix back from the DB and gate every Policy check above.
- [x] Harden file uploads (MIME allowlist + `attachable_type` allow-list + ownership checks via `AttachmentPolicy`)
- [x] Disable public self-registration (`POST /api/v1/auth/register` removed) + add `throttle:auth`/`throttle:api` rate limiting
- [x] **Fix multi-tenancy.** `BelongsToWorkspace` now auto-stamps `workspace_id` on create and enforces a real global scope on `Project`, `Customer`, `Employee`. `ceo`/`boss` roles bypass the scope by design (cross-workspace executive view); unauthenticated console/queue contexts are left unrestricted (trusted internal code); a user with no linked Employee sees nothing (deny-by-default). Existing NULL `workspace_id` rows were backfilled via migration.

---

## Phase 4 — Consolidate the duplicated architecture

**Estimated effort:** 1–2 weeks

Two parallel layers exist that don't share a service layer:
- `app/Modules/*` — powers the REST API, uses proper Controller → Service → Repository pattern
- `app/Livewire/*` (`TaskDashboard`, `ProjectHub`, `TaskDetail`) — bypasses the Modules service layer and manipulates Eloquent directly, duplicating logic

Consequence: audit trails and business rules applied in one layer aren't applied in the other (e.g. the API's `NativeTaskService::createTask()` writes a `TaskActivityLog` entry; the Livewire path doesn't).

- [x] Remove the dead duplicate `TaskController`/`TaskService`/`TaskRepository` stack (never routed to)
- [x] Refactor `TaskDashboard`, `ProjectHub`, `TaskDetail` onto the shared `Modules\*` services. Extended `NativeTaskService`/`ProjectService` with the field/assignee/bulk/delete methods the UI needed (they didn't exist before — only create/bulk-status/archive were covered), so every task/project edit now writes a `TaskActivityLog` entry regardless of whether it came from the API or the Livewire UI.
- [x] Rewrite `EndToEndBusinessScenarioTest` with real assertions (was `assertTrue(true, ...)` with every real assertion commented out, posted to a route that doesn't exist, and imported classes under wrong namespaces). Now exercises the real WhatsApp webhook → `ProcessIncomingMessage` → Customer/Conversation/Message/Issue pipeline end-to-end.
- [x] Add model factories (`Task`, `Project`, `Customer`, `Employee`, `Issue`)

**Bugs found and fixed along the way (not pre-planned, surfaced by writing real tests):**
- `ProjectHub` hardcoded new projects to `Workspace::first()->id` instead of the creator's actual workspace — silently misassigned projects in any multi-workspace setup. Now relies on the Phase 3c auto-stamp trait.
- `TaskDetail::uploadAttachment()` had no MIME-type/size validation at all (unlike the hardened API `AttachmentController`) — any file type could be uploaded. Now shares the same allow-list via `AttachmentService::ALLOWED_EXTENSIONS`.
- `Task::status` reads through a cached `currentState` relation, so setting and then immediately re-reading `status` in the same request returned the stale pre-change value.
- `BaseRepository::create()` never refreshed the model after insert, so DB column defaults (e.g. `Project.status`, `Issue.priority`/`source`) were `null` in-memory immediately after creation.
- `WebhookController::handleWhatsApp()` was typed to return `JsonResponse` only, but Meta's GET verification handshake returns plain text — a `TypeError` on the very first step of any real WhatsApp integration setup.
- Laravel's default model-factory name resolver doesn't handle the `Modules\*\Models\*` namespace convention used throughout this app; registered a global resolver via `Factory::guessFactoryNamesUsing()`.

**Follow-up (fixed after Phase 4):**
- [x] `Modules\Issues\Models\IssueComment` didn't exist at all (model + migration both missing) — `POST`/`GET /api/v1/issues/{id}/comments` 500'd unconditionally. Added the migration + model, mirroring the `IssueTimeline` fix.
- [x] `AttachmentController::store()` passed `$request->user()?->id` (a **User** id) as `uploaded_by`, but `Attachment::uploader()` is `belongsTo(Employee::class, 'uploaded_by')` — attachments created via the API got the wrong "uploader" attribution. Now resolves the Employee id.

---

## Phase 5 — Cleanup + remaining medium/low items

Wire up or delete what's currently half-built or decorative, so the codebase stops misrepresenting its own state:

- [x] Remove or complete dead/broken modules: `Delivery`, `Storage` (both throw fatal errors or duplicate working functionality)
- [x] Remove decorative fake UI: fake "OpenAI API Key" field, unbound "Storage Provider" dropdown, fake "● Online" presence dot
- [x] Fix the Rules/Automation engine — `evaluateConditions()` always returns `true` regardless of configured conditions
- [x] Consolidate duplicate logging tables (`ai_logs` vs `ai_request_logs`, `sync_logs` vs `synchronizations`) — *decision: kept separate, documented (see note below); `sync_logs` was dead code, removed in the earlier pass*
- [x] Wire up or remove unused infrastructure: `Concurrency` locks, `Telemetry` metrics, `Configuration` feature flags — *decision: removed `Concurrency`/`Configuration` entirely (zero callers); kept `Telemetry\MetricsRegistry`/`DomainEvent` (both genuinely used)*
- [x] Add a real global toast/notification component (currently session-flash only)
- [x] Add `aria-label`s to icon-only buttons across the app *(thorough pass on highest-traffic pages, not a claim of 100% exhaustive coverage — see note)*
- [x] Standardize dark-mode card background classes on `dark:bg-gray-800`
- [x] SoftDeletes/UUID coverage — *audited, no bug found (see note)*

**Done so far (2026-08-03):**
- Deleted `Modules\Delivery` entirely (`CommunicationDispatcher`, `OutboundCommunication`, `MessageTemplate`, `DeliverCommunicationJob`, WhatsApp/SMTP adapters) — confirmed via grep that nothing outside the module ever called it, and `MessageTemplate` had no backing table at all.
- Deleted `Modules\Storage\Services\StorageManager` — it referenced `Modules\Tasks\Models\Attachment`, a class that doesn't exist (the real one is `Modules\Attachments\Models\Attachment`), so it would fatal-error the instant anything called it. It was only ever registered as a singleton, never resolved.
- Deleted `SyncLog` model and `Task::syncLogs()` relation — `SyncLog::create()` was never called anywhere; the real, active sync log is `Synchronization`/`synchronizations`, used by `SynchronizeObjectJob`/`ProcessProviderEventJob`.
- Added migration `2026_08_03_140000_drop_dead_delivery_and_synclog_tables.php` dropping the now-orphaned `outbound_communications` and `sync_logs` tables.
- Rewrote `RulesEngine::evaluateConditions()` to do real evaluation: dot-notation field lookup into the event context, `=`/`!=`/`>`/`>=`/`<`/`<=`/`contains`/`in`/`not_in` operators, and nested `AND`/`OR` groups. Unrecognized condition shapes now fail closed (no match) instead of always matching.
- Found and fixed a second, more fundamental Rules Engine bug while wiring the above: the Rules Center UI's "Event Trigger" dropdown offered values (`inbound_message`, `task_created`, `task_overdue`, `issue_triaged`) that **never matched any real fired event** — `EvaluateRules::handle()` matches on `class_basename($event)` (`TaskStateChanged`, `IssueStateChanged`, `CommunicationCreated`, `TaskCreated`, `SystemHealthDegraded`). This meant every rule ever created through the UI was structurally unable to fire, regardless of the `evaluateConditions()` bug. Updated the dropdown, the rule-creation logic, and the two auto-seeded demo rules to use the real event names.
- Fixed decorative UI: `workspace-settings` "OpenAI API Key" (hardcoded fake `sk-proj-xxxx...` value) and unbound "Storage Provider" dropdown replaced with an honest, read-only status card reading real config (`services.openai.key` presence, `filesystems.default`). `employee-management`'s hardcoded "● Online" badge/dot (shown identically for every employee) now reads the real `Employee.is_available` column, which was already wired into the edit form but never surfaced in the list/detail views.

**Decisions made (2026-08-03, second pass):**
- `ai_logs`/`AILog` vs `ai_request_logs`/`AiRequestLog`: decided to keep them as separate concerns rather than force a merge — `AILog` is a low-level, provider-side log of raw OpenAI calls (`OpenAIProvider`); `AiRequestLog` is a business-level log of AI Center requests (pipeline/model/prompt bookkeeping, cost, validation status) used by the mock/scoring pipeline and the admin UI. Added doc-comments to both models cross-referencing each other so future contributors don't mistake this for an unintentional duplicate.
- `Concurrency` (`LockManager`, `IdempotencyService`, `OutboxMessage`, `ProcessOutboxMessagesJob`) and `Configuration` (`FeatureManager`, `FeatureFlag`) deleted entirely — confirmed zero callers anywhere outside their own singleton registration. Also removed `tests/Feature/ConcurrencyTest.php`, which only unit-tested the now-deleted `LockManager`/`IdempotencyService` in isolation and had no other integration point. Added migration `2026_08_03_150000_drop_dead_concurrency_and_configuration_tables.php` dropping the orphaned `outbox_messages`/`feature_flags` tables.
- `Telemetry` was **not** removed: `MetricsRegistry` feeds the real `OperationsDashboard`, and `DomainEvent`/`domain_events` is read by `GlobalTimeline`. Only the genuinely unused `ContextRegistry` service was deleted (no backing table existed for it).

**UI polish pass (2026-08-03, third pass):**
- **Global toast component.** ~60 call sites across ~29 Livewire components called `session()->flash('success'|'error'|...)`, but only 8 page templates actually rendered it — most user actions (task edits, employee offboarding, rule creation, etc.) showed **zero feedback** to the user. Fixed with a single hook: `AppServiceProvider::registerFlashToastBridge()` uses Livewire's `before('dehydrate', ...)` hook to convert any pending session flash into a `toast` browser event on every component render (must be `before`, not a normal listener — Livewire's own `SupportEvents::dehydrate()` hook reads queued dispatches and runs first otherwise, silently dropping the event). A new `layouts/app-shell/toast.blade.php` Alpine component, included once in both the authenticated and guest layouts, renders it as a real animated/dismissible/auto-expiring toast that survives `wire:navigate`. Removed the 8 now-redundant inline `@if(session('success'))` blocks this replaces.
- **`aria-label`s on icon-only buttons.** Audited icon-only buttons (no visible text) across the app and added `aria-label` to the ones missing it: task delete/edit (kanban + table + detail view), project edit/archive, employee offboard, customer/conversation/rule delete, calendar prev/next month, kanban move-back/move-forward. Confirmed the shared `<x-ui.slide-form-modal>` and the Phase 1 app-shell nav components already had correct `aria-label`s. **This was a thorough but not exhaustive pass** (targeted the highest-traffic pages via grep-based search, not a full automated a11y audit like axe-core) — treat as a strong improvement, not a completeness guarantee.
- **Found and removed 3 more dead files while auditing icon-only buttons:** `resources/views/layouts/app.blade.php`, `layouts/app-header.blade.php`, and `layouts/sidebar.blade.php` were leftovers from *before* the Phase 1 ClickUp nav redesign — completely unreferenced (Livewire's actual default layout is `components.layouts.app`, configured in `config/livewire.php`). Also removed `components/common/theme-toggle.blade.php`, an orphaned duplicate of the theme toggle that's actually inline in `layouts/app-shell/topbar.blade.php`.
- **SoftDeletes/UUID coverage audited, no bug found.** Initially suspected SoftDeletes was unwired (a naive `grep "use SoftDeletes"` matched 0 files), but that grep pattern was wrong — all 8 tables with a `deleted_at` column (`customers`, `projects`, `employees`, `issues`, `tasks`, `attachments`, `conversations`, `work_communications`) correctly have `SoftDeletes` on their models already. UUID coverage (19/45 models) looks intentionally scoped to customer/API-facing resources rather than a gap — internal-only lookup tables (`Workflow`, `WorkflowState`, `BusinessRule`, etc.) use plain auto-increment IDs, which is a reasonable, common pattern, not a bug.
- **Dark-mode card background classes — standardized on `dark:bg-gray-800` (2026-08-03, fourth pass).** User picked `gray-800` (the already-most-common convention, 47 sites); turned out to also be the objectively *correct* choice, not just the most popular one — the app-shell page background and all nav chrome (primary/secondary sidebar, topbar, mobile nav) already use `dark:bg-gray-900`, so a card using `-900` too just blends into the page in dark mode and loses its visual "elevation" as a distinct surface. Fixed the 32 genuine content-card sites that used `dark:bg-gray-900` or `dark:bg-white/5` for card surfaces (`project-hub`, `task-detail`, `task-dashboard`, `conversation-center`, `conversation-explorer`, `ai-center`, `kanban-board`, `global-timeline`, `rules-center`, `employee-workspace`, `workspace/dashboard`). Deliberately left 10 sites untouched because they aren't "cards": full-page `<body>` backgrounds (`errors/403`, `errors/404`, guest layout), nav chrome (`mobile-nav`, matches the sidebars/topbar convention), modal/overlay chrome (`components/ui/modal`, `global-command-palette`), and form `<input>`/`<textarea>` backgrounds (4 sites) — those are different UI roles and changing them wasn't part of "card" standardization. Verified with a render-smoke test hitting all affected pages before removing it.

---

## Suggested order

**1 → 2 → 3 → 4 → 5**

The UI work is safe to do first since it doesn't touch security-critical paths. That said, Phase 3 (security foundation) shouldn't be delayed too long — right now any logged-in API user can reach across tenants, which matters a lot the moment this app has more than one trusted internal user.

---

## Reference: overall production readiness (from audit)

| Dimension | Score /100 |
|---|---|
| Feature completeness | 80 |
| UI/UX polish & consistency | 75 |
| Code architecture & maintainability | 50 |
| Security | 20 |
| Performance | 60 |
| Testing | 35 |
| Documentation accuracy | 15 |
| Deployment readiness | 40 |
| **Overall** | **42** |

**Verdict from audit:** acceptable today as an internal, single-tenant pilot with trusted users. Not safe for multi-tenant or untrusted external use until Phase 3 lands.
