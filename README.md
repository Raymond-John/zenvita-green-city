# Zenvita Green City

**Core PHP** member / portal / store app for Cellvigor Biorevive LTD.

This project uses a **Laravel-inspired folder layout** for organization. It is **not** a Laravel application (no Composer Laravel framework, no Artisan, no Eloquent, no Blade).

## Structure

```
app/
  Http/Controllers   # reserved for future thin controllers
  Http/Middleware
  Models
  Services           # Mailer and other services (plain PHP)
  Support            # shared helpers (db, auth, uploads)
bootstrap/           # loads app helpers + config
config/              # app, database, mail (Hostinger defaults)
database/
  migrations/        # one-off PHP migrations
  seeders/           # setup / seed scripts
  schema.sql
public/              # web document root (pages, api, assets)
resources/views      # reserved for future templates
routes/              # route maps (documentation; not a framework router)
storage/
```

## Local (XAMPP)

- Document root behavior: project `.htaccess` rewrites into `public/`
- Portal login: `/portal/login`
- Member login: `/login`
- Configure Hostinger SMTP in **Portal → Settings → Email / SMTP**

## Important

Keep implementing features as **core PHP**. Do not install Laravel unless explicitly requested. Prefer putting shared logic in `app/` and keeping `public/` as the web-facing layer.
