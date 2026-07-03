# Laravel Beacon

**Laravel Project Context Generator**

Scan any Laravel project and export clean, structured context for AI-assisted development.

---

## Quick Start

```bash
composer require coffesoft/laravel-beacon
```

The service provider auto-discovers via Laravel package discovery.

---

## Commands

```bash
# Scan the project and display a summary
php artisan beacon:scan

# Export context as Markdown (AI-ready document)
php artisan beacon:export --format=md

# Export context as structured JSON
php artisan beacon:export --format=json

# Export to a custom location
php artisan beacon:export --format=md --output=custom/path/context.md
```

## Output

### context.md
Six-section markdown document with factual project data:
1. **Project Overview** — Laravel version, PHP version, counts
2. **Architecture** — Detected modules (admin, coach, trainee, public, api)
3. **Models** — Each model with namespace, relationships, fillable fields
4. **Controllers** — Grouped by subdirectory with method lists
5. **Routes** — Grouped by prefix with route counts
6. **Migrations** — Table list with totals

### context.json
Strict structured JSON — only scanned data, no derived intelligence.

## What It Scans

| Scanner | Source | Data |
|---|---|---|
| ModelScanner | `app/Models/` | Class names, namespaces, Eloquent relationships, fillable fields |
| ControllerScanner | `app/Http/Controllers/` | Class names, namespaces, public methods, subdirectory grouping |
| RouteScanner | All registered routes | URI, HTTP methods, route names, action, middleware, prefix grouping |
| MigrationScanner | `database/migrations/` | File names, class names, timestamps, table names, column types |

## Requirements

- PHP 8.2+
- Laravel 11.x

## License

MIT
