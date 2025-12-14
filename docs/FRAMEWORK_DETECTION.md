# Framework Detection

> 🇵🇱 **Polish version**: [Wykrywanie Frameworków (PL)](FRAMEWORK_DETECTION_PL.md)

PHPStan Fixer automatically detects PHP frameworks and filters framework-specific fixers accordingly.

## How It Works

1. **Detection**: The tool scans `composer.json` and directory structure to identify the framework
2. **Filtering**: Framework-specific fixers are automatically included/excluded based on detection
3. **Framework-Agnostic Fixers**: Always included regardless of detected framework

## Supported Frameworks

### Laravel
- Detected from: `composer.json` (`laravel/framework`) or directory structure (`artisan`, `app/`, `config/`, `routes/`)
- Framework-specific fixers: `UndefinedPivotPropertyFixer`, `CollectionGenericDocblockFixer`

### Symfony
- Detected from: `composer.json` (`symfony/symfony` or multiple `symfony/*` components) or directory structure (`symfony.lock`, `src/`, `config/`, `public/`)
- Framework-specific fixers: (none currently)

### CodeIgniter
- Detected from: `composer.json` (`codeigniter4/framework`) or directory structure (`app/`, `public/`, `writable/`, `spark`)

### CakePHP
- Detected from: `composer.json` (`cakephp/cakephp`) or directory structure (`config/`, `src/`, `webroot/`, `bin/cake`)

### Yii
- Detected from: `composer.json` (`yiisoft/yii` or `yiisoft/yii2`)

### Laminas (formerly Zend Framework)
- Detected from: `composer.json` (`laminas/laminas-mvc` or `laminas/laminas-mvc-skeleton`)

### Phalcon
- Detected from: `composer.json` (`phalcon/cphalcon`)

## Native PHP Projects

For projects without a framework (native PHP):

- ✅ **All framework-agnostic fixers work normally**
- ❌ **Framework-specific fixers are automatically excluded**

Example:
```bash
$ phpstan-fixer suggest
# No "Detected framework" message
# Framework-specific fixers (like UndefinedPivotPropertyFixer) are excluded
# All other fixers work normally
```

## Custom Fixers for Other Frameworks

If you need fixers for other frameworks or want to create framework-specific fixers:

1. Create a fixer that implements `FixStrategyInterface`
2. Implement `getSupportedFrameworks()` method:
   ```php
   public function getSupportedFrameworks(): array
   {
       return ['your-framework-name'];
   }
   ```
3. The fixer will be automatically included when the framework is detected

## Extending Framework Detection

To add support for additional frameworks, extend `FrameworkDetector`:

```php
// In detectFromComposer()
if (isset($require['your-framework/package'])) {
    return 'your-framework';
}

// In detectFromDirectoryStructure()
// Add directory structure indicators
```

## Examples

### Laravel Project
```bash
$ phpstan-fixer suggest
Note: Detected framework: laravel
# UndefinedPivotPropertyFixer is included
```

### Symfony Project
```bash
$ phpstan-fixer suggest
Note: Detected framework: symfony
# All framework-agnostic fixers work
```

### Native PHP Project
```bash
$ phpstan-fixer suggest
# No framework detected
# Framework-specific fixers excluded, others work normally
```

