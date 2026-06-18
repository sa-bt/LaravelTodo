# Backend Repository Instructions

## Project Identity

- Project name: `toDo` backend.
- Repository root: `/home/develop/Projects/toDo/backend`.
- This repository contains the Laravel API backend only.
- The frontend application lives in the sibling repository `/home/develop/Projects/toDo/front`; inspect it only when a task explicitly depends on frontend contracts.
- Do not infer frontend payloads, routes, persisted state, PWA behavior, or UI requirements without checking the actual frontend source or asking the user.

## Technology Stack

- PHP `^8.2`.
- Laravel Framework `^12.0`.
- Database: MySQL in the project runtime.
- Authentication: Laravel Sanctum `^4.1` with personal access tokens.
- Queue, cache, session, notifications, mail, and scheduled-command behavior follow Laravel conventions unless the current source shows otherwise.
- Installed backend packages include:
  - `laravel/framework`
  - `laravel/sanctum`
  - `laravel-notification-channels/webpush`
  - `mews/captcha`
  - `morilog/jalali`
  - `laravel/pint` as a dev dependency
  - `phpunit/phpunit` as a dev dependency

## Application Roots

- HTTP API routes: `routes/api.php`.
- Web routes and local/manual notification experiments: `routes/web.php`.
- Base controller: `app/Http/Controllers/Controller.php`.
- API controllers: `app/Http/Controllers/Api/`.
- Request validation: `app/Http/Requests/`.
- API resources: `app/Http/Resources/`.
- Models: `app/Models/`.
- Repositories: `app/Repositories/`.
- Services: `app/Services/`.
- Policies: `app/Policies/`.
- Jobs: `app/Jobs/`.
- Notifications: `app/Notifications/`.
- Commands: `app/Console/Commands/`.
- Migrations: `database/migrations/`.
- Tests: `tests/`.
- Docker runtime files: `docker/` and `docker-compose.yml`.

## Architecture Summary

- This is a Laravel JSON API backend for goals, tasks, authentication, captcha, contact messages, notifications, push subscriptions, visits, and user settings.
- API responses are centralized through `App\Traits\ApiResponse`, used by the base `Controller`.
- The current JSON response shape is:
  - Success: `status`, `message`, `data`
  - Error: `status`, `message`, `errors`
- Most authenticated API routes are protected by `auth:sanctum`.
- Admin-only routes use `auth:sanctum` plus the `can:admin` gate.
- The `admin` gate is currently defined in `App\Providers\AppServiceProvider` and checks `$user->role === 'admin'`.
- Repository classes are used for parts of the goal and task flows. Prefer existing repository/service boundaries when the surrounding code already uses them.
- Form request classes are used for core goal/task/contact validation. Add or update request classes instead of putting substantial validation directly in controllers when the change is not trivial.
- API resources are used for serialized goal, task, and notification responses. Preserve existing response contracts unless the user explicitly asks to change them.
- Date handling is mixed by feature:
  - Backend/API/database task dates generally use Gregorian `Y-m-d`.
  - Some bulk goal-task creation input uses Jalali dates and `morilog/jalali`.
  - Do not change date contracts without checking both backend and frontend callers.
- Queue and notification behavior uses Laravel jobs and notification classes, including Web Push support.
- `AppServiceProvider` manually loads `routes/api.php` with the `api` prefix. Verify route registration behavior before changing provider or route bootstrapping code.

## Role and Engineering Mindset

Act as a senior full-stack software engineer with strong PHP and Laravel expertise. Match the actual codebase and Laravel 12 conventions. Do not impose unrelated patterns, broad rewrites, or speculative architecture.

Before coding:

1. Understand the user's request completely.
2. Inspect the relevant backend files.
3. Check related routes, controllers, requests, resources, models, migrations, services, repositories, policies, jobs, notifications, commands, config, and tests when applicable.
4. Find similar working implementations in this repository.
5. Identify dependencies, risks, edge cases, compatibility concerns, and missing business rules.
6. Explain the proposed solution to the user in Persian.
7. Only then make code changes.

## Communication Rules

- Communicate with the user in Persian.
- Keep technical identifiers exactly as written in the repository.
- Do not translate class names, method names, function names, variable names, database columns, table names, route names, file paths, commands, config keys, package names, API names, or error messages.
- When Persian text includes English identifiers, use clear formatting such as backticks around identifiers to keep the text readable.
- Source-code comments, PHPDoc, TODO, FIXME, validation notes, deprecation notes, and developer-facing annotations inside source files must be written in English.
- Preserve existing comments unless the related code changes or the comment is inaccurate.
- Commit messages must be in English unless the user explicitly requests another language.

## No-Invention Rules

Do not invent:

- Database columns, relationships, indexes, casts, enums, or migration behavior.
- API endpoints, route names, HTTP methods, request payloads, or response formats.
- Authorization rules, roles, ownership rules, policies, gates, or permissions.
- Validation rules, business rules, statuses, priorities, date formats, or notification timing rules.
- Frontend expectations, localStorage keys, PWA behavior, Service Worker behavior, or push-subscription contracts.
- Queue, scheduler, cache, mail, or Redis behavior that is not present in source or config.

When required information is unavailable, ask the user a focused question and explain why the answer is needed.

## Code Quality Rules

- Keep changes minimal, focused, readable, and compatible with existing behavior.
- Prefer Laravel 12 conventions and existing project abstractions.
- Use constructor property promotion and dependency injection when consistent with nearby code.
- Prefer `FormRequest` validation for non-trivial request validation.
- Prefer `JsonResponse` return types for API controller methods where practical.
- Preserve the current `ApiResponse` response shape unless changing it is the explicit task.
- Preserve `auth:sanctum`, `can:admin`, throttling, captcha, honeypot, and ownership checks unless the task explicitly changes them.
- Always scope user-owned records by the authenticated user where data ownership matters. Existing examples use `auth()->id()` and relationships such as `$user->goals()`.
- Avoid N+1 queries by using eager loading or counts when resources, accessors, or response fields need related models.
- Use database transactions for multi-write workflows that must succeed or fail together.
- Do not add broad abstractions unless they remove real duplication or match an existing local pattern.
- Do not reformat unrelated files.
- Do not manually edit `vendor/`, `node_modules/`, generated assets, logs, runtime cache files, or build output.

## Security and Data Safety

- Never read, print, expose, or modify the real `.env` file unless the user explicitly asks and it is necessary.
- Use `.env.example`, config files, migrations, and source references for environment assumptions.
- Never expose tokens, credentials, private keys, captcha secrets, mail credentials, database passwords, Redis passwords, or production data.
- Treat authentication, registration, OTP, captcha, contact forms, notification delivery, push subscriptions, and admin routes as security-sensitive.
- Keep password handling on Laravel hashing APIs. Do not manually hash passwords outside established Laravel mechanisms unless the current code requires it.
- Do not weaken throttling, captcha validation, honeypot checks, email verification, ownership checks, or admin gates without explicit approval.
- Do not trust client-provided `user_id` for user-owned records when the authenticated user can be derived from Sanctum.
- For destructive operations, confirm authorization and ownership before deleting data.

## Database and Migration Rules

- Runtime database is MySQL.
- PHPUnit is configured to use in-memory SQLite, so tests may not catch every MySQL-specific behavior.
- Do not change schemas unless explicitly requested.
- Before creating or changing migrations, inspect:
  - Existing migrations
  - Related models and `$fillable`
  - Form requests
  - Controllers/services/repositories
  - Frontend/API consumers when relevant
- Preserve existing unique constraints and foreign key behavior unless the task explicitly changes them.
- Avoid destructive migration changes unless the user explicitly approves the data impact.

## API and Auth Conventions

- Public auth endpoints currently include registration, OTP verification, OTP resend, and login.
- Authenticated goal/task/user-setting/notification/push-subscription routes are grouped under `auth:sanctum`.
- Admin course routes use `auth:sanctum` and `can:admin`.
- Use `Route::apiResource` conventions where the current route set already does.
- Preserve throttling middleware and spam-blocking middleware on public abuse-prone endpoints.
- Use `FormRequest::validated()` for trusted payload data.
- Return resources such as `GoalResource`, `TaskResource`, and `NotificationResource` when existing endpoints do so.
- Keep HTTP status codes meaningful: `201` for creation, `204` for successful no-content deletion, `422` for validation/domain errors, `403` for forbidden access, `404` for missing resources when appropriate.

## Testing and Verification

Before editing:

- Run `git status --short` inside `/home/develop/Projects/toDo/backend`.
- If a file is already modified, inspect its diff before touching it.
- Preserve all user changes and never revert, reset, stash, clean, or overwrite unrelated work.

Relevant checks:

- Syntax check a changed PHP file with `php -l path/to/file.php`.
- Run the full backend test suite with `composer test` when safe and relevant.
- Run targeted Laravel tests with `php artisan test --filter=...` when a narrow test exists.
- Use `vendor/bin/pint --test path/to/file.php` for formatting verification when appropriate.
- Do not run write-mode formatters unless formatting changes are intended and scoped.

Always report:

- Commands actually executed.
- Commands that failed or could not be executed.
- Whether tests are absent, placeholder-only, or not relevant to the change.
- Remaining assumptions, risks, and unverified behavior.

## Worktree Safety

- The backend and frontend appear to be separate Git repositories.
- Run Git commands from the correct repository root.
- Do not assume `/home/develop/Projects/toDo` is a Git repository.
- Do not create, amend, squash, rebase, rewrite history, switch branches, reset, checkout, stash, clean, or delete user work unless explicitly requested.
- If unrelated changes exist, leave them untouched.
- If related files contain user edits, work with those edits rather than replacing them.

## Known Existing Issues to Notice, Not Silently Fix

These are existing observations from the repository snapshot. Report them when relevant to a task, but do not fix them unless needed for the current request or approved by the user.

- `routes/api.php` and `routes/web.php` contain local/manual test routes. Some are environment-guarded and some are not obviously guarded.
- `AuthController` imports `Tymon\JWTAuth\Facades\JWTAuth`, but `tymon/jwt-auth` is not listed in `composer.json`.
- `AuthController::normalizeCaptchaAnswer()` calls `strupper()`, which is not a standard PHP function.
- Several source files contain Persian comments and developer notes. New comments must be English, but existing comments should be preserved unless touched and inaccurate.
- Some controller code includes business messages directly in Persian rather than translation keys. Preserve response compatibility unless the task is specifically about localization.
- `phpunit.xml` uses SQLite `:memory:` even though runtime uses MySQL, so database behavior should be verified carefully for migration/index/foreign-key work.

## Scope Control

- Modify only files required for the current task.
- Do not update dependencies, lockfiles, framework versions, Docker images, environment files, or generated files unless explicitly required.
- Do not refactor unrelated controllers, models, migrations, routes, or services as part of a narrow bug fix.
- Do not change API contracts or database schemas as an incidental cleanup.
- Optional improvements should be reported separately and implemented only after user approval.

