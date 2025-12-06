# Repository Description (English)

Use this description for the GitHub repository:

**Short description (max 160 characters):**
```
Framework-agnostic PHP library for automatically fixing PHPStan errors using static analysis. Works with Laravel, Symfony, CodeIgniter, and native PHP projects.
```

**Full description:**
```
Framework-agnostic PHP library for automatically fixing PHPStan errors using static analysis. Works with Laravel, Symfony, CodeIgniter, and native PHP projects.

## Features

- Automatically detects and fixes common PHPStan errors
- Framework-agnostic (works with any PHP project)
- Offline-friendly (no AI or network access required)
- Suggest mode (preview changes) and Apply mode (write changes)
- Supports multiple fix strategies for different error types

## Installation

```bash
composer require --dev lukaszzychal/phpstan-fixer
```

## Quick Start

```bash
# Preview fixes (safe, no file changes)
vendor/bin/phpstan-fixer

# Apply fixes (modifies files)
vendor/bin/phpstan-fixer --mode=apply
```

Requires PHP 8.0+ and PHPStan.
```

**Topics/Tags to add:**
- phpstan
- static-analysis
- code-fixer
- phpdoc
- automation
- code-quality
- php
- laravel
- symfony
- phpstan-error-fixer
- automated-refactoring

