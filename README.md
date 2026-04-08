# Symfony Project Setup Guide

Welcome to your Symfony application! This guide will help you set up and run your environment.

## 📋 Requirements

- **PHP**: 8.1 or higher
- **Composer**: Latest version
- **Web Server**: Apache with mod_rewrite or Nginx (optional for dev)

## 🚀 Quick Start

### 1. Install Dependencies

First, install all PHP dependencies using Composer:

```bash
composer install
```

This will create a `vendor/` directory with all required packages.

### 2. Configure Environment

Copy `.env` to `.env.local` and customize if needed:

```bash
cp .env .env.local
```

Edit `.env.local` to change database settings, app secret, etc.

### 3. Run Development Server

For development, use PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

Or use the Symfony CLI (if installed):

```bash
symfony serve
```

Then visit: **http://localhost:8000**

### 4. Generate Database (Optional)

If you set up a database, create tables:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## 📁 Project Structure

```
pidev/
├── bin/              # CLI commands
├── config/           # Configuration files
│   └── packages/     # Bundle configuration
├── public/           # Web root (index.php entry point)
│   ├── index.php     # Application entry point
│   └── .htaccess     # Apache rewrite rules
├── src/              # Application source code
│   ├── Controller/   # Route controllers
│   ├── Entity/       # Doctrine entities
│   ├── Repository/   # Doctrine repositories
│   └── Kernel.php    # Application kernel
├── templates/        # Twig templates
├── var/              # Cache and logs
│   ├── cache/        # Application cache
│   └── log/          # Log files
├── .env              # Environment variables (committed)
├── .env.local        # Local overrides (NOT committed)
├── composer.json     # PHP dependencies
└── composer.lock     # Dependency lock file
```

## 📝 Common Commands

### Useful Symfony Console Commands

```bash
# List all available commands
php bin/console list

# Make a new controller (requires maker-bundle)
php bin/console make:controller

# Make a new entity
php bin/console make:entity

# Clear cache
php bin/console cache:clear

# Run built-in server
php bin/console server:run
```

## 🔧 Configuration

### Framework Configuration
- Located in `config/packages/framework.yaml`
- Main Symfony framework settings

### Twig Templates
- Located in `config/packages/twig.yaml`
- Template engine configuration

### Logging
- Located in `config/packages/monolog.yaml`
- Log file settings

### Routing
- Located in `config/routes.yaml`
- Application routes and service auto-loading

### Services
- Located in `config/services.yaml`
- Dependency injection configuration

## 🗄️ Database Setup (Optional)

### Using SQLite (Easiest for Development)

Edit `.env.local`:
```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

### Using MySQL

Edit `.env.local`:
```
DATABASE_URL="mysql://username:password@127.0.0.1:3306/database_name?serverVersion=8.0"
```

### Using PostgreSQL

Edit `.env.local`:
```
DATABASE_URL="postgresql://username:password@127.0.0.1:5432/database_name"
```

## 📌 Creating Your First Controller

Create a file `src/Controller/YourController.php`:

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class YourController extends AbstractController
{
    #[Route('/hello/{name}', name: 'app_hello')]
    public function hello(string $name): Response
    {
        return $this->render('hello.html.twig', [
            'name' => $name,
        ]);
    }
}
```

Create template `templates/hello.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block content %}
<h1>Hello, {{ name }}!</h1>
{% endblock %}
```

Visit: `http://localhost:8000/hello/World`

## 🎨 Creating Your First Entity

Use the maker bundle:

```bash
php bin/console make:entity
```

Follow the prompts to create an entity, then migrate:

```bash
php bin/console doctrine:migrations:make
php bin/console doctrine:migrations:migrate
```

## 🐛 Debugging

### Enable Profiler (Development Only)

Already enabled in `.env` with `APP_ENV=dev`. The profiler appears as a toolbar in templates.

### Check Logs

```bash
tail -f var/log/dev.log
```

### Dump Variables

Use in templates or controllers:

```twig
{{ dump(variable) }}
```

## 🚀 Production Deployment

### 1. Install Production Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 2. Set Environment Variables

In `.env.local`:
```
APP_ENV=prod
APP_DEBUG=0
```

### 3. Clear Cache

```bash
php bin/console cache:clear --env=prod
```

### 4. Configure Web Server

Point web root to `public/` directory. Examples:

**Apache:**
```
DocumentRoot /path/to/project/public
```

**Nginx:**
```
root /path/to/project/public;
location / {
    try_files $uri /index.php$is_args$args;
}
```

## 📚 Additional Resources

- [Official Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Symfony Bundles](https://symfony.com/bundles)
- [Twig Template Engine](https://twig.symfony.com/)
- [Doctrine ORM](https://www.doctrine-project.org/)

## ✅ Checklist

- [ ] Installed Composer dependencies
- [ ] Configured `.env.local`
- [ ] Ran development server
- [ ] Accessed website in browser
- [ ] Created first controller
- [ ] Created first template
- [ ] Set up database (optional)
- [ ] Created first entity (optional)

## 🆘 Troubleshooting

### "vendor/autoload.php" not found
**Solution:** Run `composer install`

### "Class not found" errors
**Solution:** Run `composer dump-autoload`

### Cache issues
**Solution:** Clear cache: `php bin/console cache:clear`

### Permission denied on var/
**Solution:** Set write permissions: `chmod -R 777 var/`

### Database connection failed
**Solution:** Check `DATABASE_URL` in `.env.local`

---

Enjoy building with Symfony! 🎉
