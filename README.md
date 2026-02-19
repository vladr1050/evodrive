# EvoDrive.lv

Performance-focused driver acquisition funnel for taxi employment in Latvia.

## Tech Stack

- **Laravel** 12+
- **Filament** v3 (Admin Panel)
- **Database:** SQLite (default) / PostgreSQL / MySQL
- **Frontend:** Blade + Tailwind CSS
- **Localization:** en, ru, lv

## Features

- **Google Landing** (`/en/g`, `/ru/g`, `/lv/g`) – High-intent traffic
- **Meta Landing** (`/en/m`, `/ru/m`, `/lv/m`) – Cold traffic
- **Apply Flow** (`/en/apply`, `/ru/apply`, `/lv/apply`) – Multi-step wizard
- **Admin Panel** – Content, images, leads management
- **SEO** – Sitemap, robots.txt, meta tags, JobPosting schema

## Setup

### Requirements

- PHP 8.2+
- Composer
- Node.js & npm (for Tailwind/Vite)

### Installation

```bash
# Clone and enter project
cd evodrive-app

# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database (SQLite default)
touch database/database.sqlite
php artisan migrate
php artisan db:seed

# Storage link (for logo, images)
php artisan storage:link

# Build assets
npm run build
```

### Development

```bash
# Run dev server (Laravel + Vite)
php artisan dev
# or
php artisan serve &
npm run dev
```

### Admin Panel

- **URL:** `/admin`
- **Default admin:** admin@evodrive.lv / password

Create admin user if needed:

```bash
php artisan tinker
>>> App\Models\User::firstOrCreate(['email' => 'admin@evodrive.lv'], ['name' => 'Admin', 'password' => bcrypt('password'), 'role' => 'admin']);
```

## Project Structure

```
app/
├── Filament/Resources/     # Admin resources (SiteSetting, Lead)
├── Http/Controllers/       # LandingController, ApplyController
├── Http/Middleware/        # SetLocale
├── Models/                 # SiteSetting, Page, PageSection, Lead, User
lang/
├── en/, ru/, lv/           # Localization files
resources/views/
├── landing/                # Google, Meta landings
├── apply/                  # Apply flow, thanks page
├── layouts/                # app, apply layouts
└── components/             # Shared components
```

## Routes

| Route       | Description        |
|------------|--------------------|
| `/en/g`    | Google landing     |
| `/ru/g`    | Google landing (ru)|
| `/en/m`    | Meta landing       |
| `/en/apply`| Apply wizard       |
| `/sitemap.xml` | Sitemap       |
| `/robots.txt`  | Robots file   |
| `/admin`   | Filament admin     |

## Form Spam Protection

- **Honeypot:** Hidden `website_url` field
- **Rate limiting:** 10 submits per minute on `/apply`
- **Server-side validation** on all fields

## Environment

For PostgreSQL/MySQL, update `.env`:

```
DB_CONNECTION=pgsql  # or mysql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=evodrive
DB_USERNAME=...
DB_PASSWORD=...
```
