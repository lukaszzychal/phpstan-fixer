# Wykrywanie Frameworków

> 🇬🇧 **English version**: [Framework Detection (EN)](FRAMEWORK_DETECTION.md)

PHPStan Fixer automatycznie wykrywa frameworki PHP i filtruje fixery specyficzne dla frameworków odpowiednio.

## Jak To Działa

1. **Wykrywanie**: Narzędzie skanuje `composer.json` i strukturę katalogów, aby zidentyfikować framework
2. **Filtrowanie**: Fixery specyficzne dla frameworków są automatycznie dołączane/wykluczane na podstawie wykrycia
3. **Fixery Framework-Agnostic**: Zawsze dołączone, niezależnie od wykrytego frameworka

## Obsługiwane Frameworki

### Laravel
- Wykrywany z: `composer.json` (`laravel/framework`) lub struktura katalogów (`artisan`, `app/`, `config/`, `routes/`)
- Fixery specyficzne dla frameworka: `UndefinedPivotPropertyFixer`, `CollectionGenericDocblockFixer`

### Symfony
- Wykrywany z: `composer.json` (`symfony/symfony` lub wiele komponentów `symfony/*`) lub struktura katalogów (`symfony.lock`, `src/`, `config/`, `public/`)
- Fixery specyficzne dla frameworka: (obecnie brak)

### CodeIgniter
- Wykrywany z: `composer.json` (`codeigniter4/framework`) lub struktura katalogów (`app/`, `public/`, `writable/`, `spark`)

### CakePHP
- Wykrywany z: `composer.json` (`cakephp/cakephp`) lub struktura katalogów (`config/`, `src/`, `webroot/`, `bin/cake`)

### Yii
- Wykrywany z: `composer.json` (`yiisoft/yii` lub `yiisoft/yii2`)

### Laminas (dawniej Zend Framework)
- Wykrywany z: `composer.json` (`laminas/laminas-mvc` lub `laminas/laminas-mvc-skeleton`)

### Phalcon
- Wykrywany z: `composer.json` (`phalcon/cphalcon`)

## Projekty Native PHP

Dla projektów bez frameworka (native PHP):

- ✅ **Wszystkie fixery framework-agnostic działają normalnie**
- ❌ **Fixery specyficzne dla frameworków są automatycznie wykluczane**

Przykład:
```bash
$ phpstan-fixer suggest
# Brak komunikatu "Detected framework"
# Fixery specyficzne dla frameworków (jak UndefinedPivotPropertyFixer) są wykluczone
# Wszystkie inne fixery działają normalnie
```

## Niestandardowe Fixery dla Innych Frameworków

Jeśli potrzebujesz fixerów dla innych frameworków lub chcesz utworzyć fixery specyficzne dla frameworka:

1. Utwórz fixer, który implementuje `FixStrategyInterface`
2. Zaimplementuj metodę `getSupportedFrameworks()`:
   ```php
   public function getSupportedFrameworks(): array
   {
       return ['your-framework-name'];
   }
   ```
3. Fixer będzie automatycznie dołączony, gdy framework zostanie wykryty

## Rozszerzanie Wykrywania Frameworków

Aby dodać obsługę dodatkowych frameworków, rozszerz `FrameworkDetector`:

```php
// W detectFromComposer()
if (isset($require['your-framework/package'])) {
    return 'your-framework';
}

// W detectFromDirectoryStructure()
// Dodaj wskaźniki struktury katalogów
```

## Przykłady

### Projekt Laravel
```bash
$ phpstan-fixer suggest
Note: Detected framework: laravel
# UndefinedPivotPropertyFixer jest dołączony
```

### Projekt Symfony
```bash
$ phpstan-fixer suggest
Note: Detected framework: symfony
# Wszystkie fixery framework-agnostic działają
```

### Projekt Native PHP
```bash
$ phpstan-fixer suggest
# Brak wykrytego frameworka
# Fixery specyficzne dla frameworków wykluczone, inne działają normalnie
```

