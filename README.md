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

## Compatibility

| Laravel | PHP | Support Level |
|---|---|---|
| 11.x | 8.1–8.4 | ✅ Official |
| 10.x | 8.1–8.3 | ✅ Official |
| 9.x | 8.1–8.2 | ✅ Official |
| 8.x | 8.1 | ⚠️ Best-effort |

---

## Commands

```bash
# Scan the project and display a summary
php artisan beacon:scan

# Export context as Markdown
php artisan beacon:export --format=md

# Export context as structured JSON
php artisan beacon:export --format=json

# Export to a custom location
php artisan beacon:export --format=md --output=custom/path/context.md
```

---

## Output

### context.md
Six-section markdown document with factual project data:
1. **Project Overview** — Framework, PHP version, counts
2. **Architecture** — Detected modules
3. **Models** — Namespace, relationships, fillable fields
4. **Controllers** — Grouped by subdirectory with method lists
5. **Routes** — Grouped by prefix
6. **Migrations** — Table names and totals

### context.json
Strict structured JSON — only factual scanned data.

---

## What It Scans

| Scanner | Source | Data |
|---|---|---|
| ModelScanner | `app/Models/` | Class names, Eloquent relationships, fillable fields |
| ControllerScanner | `app/Http/Controllers/` | Class names, public methods, subdirectory grouping |
| RouteScanner | All registered routes | URI, methods, names, actions, middleware |
| MigrationScanner | `database/migrations/` | Timestamps, table names, column types |

---

## Requirements

- PHP 8.1+
- Laravel 9.x, 10.x, or 11.x (8.x best-effort)

---

## License

MIT