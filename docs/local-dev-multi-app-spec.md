# Local Development Multi-App Specification

## Purpose

This specification defines how local development should work when multiple Laravel applications are hosted in the same workspace and need to share infrastructure such as MySQL and Mailpit.

## Goals

- Keep one canonical local-development entry point for all apps.
- Avoid port collisions between independent compose projects.
- Make it easy to start, stop, migrate, seed, and test multiple apps together.
- Make the pattern reusable so future apps can be added with minimal changes.

## Canonical approach

Use one root-level Docker Compose file at the workspace root:

- [docker-compose.yml](../docker-compose.yml)

That file should define:

- shared infrastructure services such as MySQL and Mailpit
- one or more application services
- one shared Docker network
- one shared volume for persistent data when needed

## Required behavior

### 1. Shared infrastructure

The local stack must provide shared infrastructure that is reused across apps:

- one MySQL service
- one Mailpit service
- one shared Docker network

### 2. App services

Each app should be represented as its own service entry:

- auth-app
- cert-app

Additional apps can be added following the same pattern.

### 3. Startup command

From the repository root, start everything with:

```bash
docker compose up -d --build
```

### 4. Start only selected apps

The stack should allow starting a subset of services without bringing up the entire environment:

```bash
docker compose up -d --build auth-app auth-nginx auth-scheduler
```

```bash
docker compose up -d --build cert-app cert-nginx cert-scheduler
```

This should be supported for any future app added to the compose file.

### 5. Migrations

Migrations must be executed inside the target app container:

```bash
docker compose exec auth-app php artisan migrate --force
```

```bash
docker compose exec cert-app php artisan migrate --force
```

For future apps, the pattern remains:

```bash
docker compose exec <app-service> php artisan migrate --force
```

### 6. Seeds

Seeds must also be executed inside the target app container:

```bash
docker compose exec auth-app php artisan db:seed --force
```

```bash
docker compose exec cert-app php artisan db:seed --force
```

### 7. Testing

Tests should be executed inside the app container:

```bash
docker compose exec auth-app php artisan test
```

```bash
docker compose exec cert-app php artisan test
```

### 8. Scale and extension

This pattern should support adding more apps later by following the same rules:

- add a new app service block
- connect it to the same shared network
- point it to the shared MySQL and Mailpit services
- keep the root compose file as the canonical launcher

## Non-goals

- This spec does not require using one shared database for all apps.
- This spec does not require a single application codebase; each app can still have its own Laravel project.
- This spec does not require the compose stack to be used in production.

## Acceptance criteria

A future app is considered compliant when:

1. it can be started from the root compose file,
2. its migrations can be run with `docker compose exec <app-service> php artisan migrate --force`,
3. its seeds can be run with `docker compose exec <app-service> php artisan db:seed --force`,
4. it can be started independently of other apps when needed,
5. it shares the same infrastructure services without introducing new port conflicts.
