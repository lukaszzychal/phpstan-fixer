# GitHub Repository Setup Instructions

## 1. Update Repository Description

### Current (Polish):
```
Pakiet z narzędziami automatyzującymi poprawki błędów PHPStan. Udostępnia parser logów, strategie refaktoryzacji i komendy wspierające utrzymanie wysokiej jakości kodu w projektach PHP 8.2+.
```

### Replace with (English):
```
Framework-agnostic PHP library for automatically fixing PHPStan errors using static analysis. Provides log parser, refactoring strategies, and commands supporting high code quality in PHP 8.0+ projects.
```

**How to update:**
1. Go to: https://github.com/lukaszzychal/phpstan-fixer
2. Click "⚙️ Settings" (top right)
3. Scroll down to "Repository details"
4. Edit "Description" field
5. Paste the English description above
6. Click "Save changes"

---

## 2. Packagist Tag Issue - Fixed ✅

Both tags have been pushed to GitHub:
- ✅ `v1.0.0` - pushed
- ✅ `v1.0.1` - pushed

### Verify Tags on GitHub:
Check: https://github.com/lukaszzychal/phpstan-fixer/tags

You should see both tags listed there.

### Packagist Auto-Update

Packagist should automatically detect the tags within 5-10 minutes. If tags don't appear on Packagist:

1. **Wait a few minutes** - Packagist checks for updates periodically
2. **Manually trigger update:**
   - Go to: https://packagist.org/packages/lukaszzychal/phpstan-fixer
   - Look for "Update" button and click it
   - Or use API: `curl -X POST https://packagist.org/api/update-package?username=YOUR_USERNAME&apiToken=YOUR_TOKEN&repository=https://github.com/lukaszzychal/phpstan-fixer`

3. **Check GitHub Webhook:**
   - Go to: https://github.com/lukaszzychal/phpstan-fixer/settings/hooks
   - Look for Packagist webhook
   - Verify it's active and receiving events

4. **Verify Packagist Integration:**
   - Go to Packagist → Submit → Connect GitHub
   - Ensure repository is connected
   - Repository should show "Auto-updated" status

### Expected Packagist Output

After update, Packagist should show:
- `v1.0.0` (stable release)
- `v1.0.1` (stable release)
- `dev-main` (development branch)

---

## 3. Repository Topics

Add these topics to your GitHub repository:
- `phpstan`
- `static-analysis`
- `code-fixer`
- `phpdoc`
- `automation`
- `code-quality`
- `php`
- `laravel`
- `symfony`
- `phpstan-error-fixer`
- `automated-refactoring`

**How to add topics:**
1. Go to repository main page
2. Click gear icon ⚙️ next to "About" section
3. Add topics in the "Topics" field
4. Press Enter after each topic
5. Click "Save changes"

