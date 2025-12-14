# Testy Kompatybilności

> 🇬🇧 **English version**: [Compatibility Testing (EN)](COMPATIBILITY_TESTING.md)

Ten dokument wyjaśnia, jak skonfigurować i używać testów kompatybilności dla pakietu PHP za pomocą `php-compatibility-tester`.

## Przegląd

Testy kompatybilności zapewniają, że pakiet działa poprawnie w różnych wersjach PHP i frameworkach. Pakiet `php-compatibility-tester` automatyzuje ten proces poprzez:

- Testowanie pakietu na wielu wersjach PHP (8.1, 8.2, 8.3, 8.4)
- Testowanie integracji z popularnymi frameworkami (Laravel, Symfony, CodeIgniter, itp.)
- Uruchamianie niestandardowych skryptów testowych do weryfikacji funkcjonalności
- Generowanie raportów do integracji CI/CD

## Instalacja

Dodaj `php-compatibility-tester` jako zależność deweloperską:

```bash
composer require --dev lukaszzychal/php-compatibility-tester
```

## Inicjalizacja

### Szybki Start

Uruchom komendę inicjalizacji:

```bash
vendor/bin/compatibility-tester init
```

Ta komenda:

1. **Utworzy `.compatibility.yml`** - Główny plik konfiguracyjny
2. **Skopiuje szablony testów PHPUnit** do `tests/compatibility/`:
   - `FrameworkCompatibilityTest.php`
   - `ComposerCompatibilityTest.php`
3. **Skopiuje workflow GitHub Actions** do `.github/workflows/compatibility-tests.yml`
4. **Skopiuje skrypty testowe** do `scripts/compatibility-test.sh`

### Plik Konfiguracyjny

Plik `.compatibility.yml` jest tworzony w głównym katalogu projektu. Jeśli szablon przykładu nie zostanie znaleziony w pakiecie, możesz odwołać się do przykładu z:

```
vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml
```

## Konfiguracja

### Podstawowa Konfiguracja

Edytuj `.compatibility.yml`, aby skonfigurować testy:

```yaml
package_name: "vendor/package-name"

php_versions: ['8.1', '8.2', '8.3', '8.4']

frameworks:
  laravel:
    versions: ['11.*', '12.*']
    install_command: 'composer create-project laravel/laravel'
    php_min_version: '8.1'
  
  symfony:
    versions: ['7.4.*', '8.0.*']
    install_command: 'composer create-project symfony/skeleton'
    php_min_version: '8.1'
  
  codeigniter:
    versions: ['4.*', '5.*']
    install_command: 'composer create-project codeigniter4/appstarter'
    php_min_version: '8.1'

test_scripts:
  - name: "Autoload test"
    command: "composer dump-autoload && php -r \"require 'vendor/autoload.php'; echo 'Autoload OK';\""
  
  - name: "Binary test"
    command: "vendor/bin/your-binary --help"
  
  - name: "Basic functionality test"
    command: "php -r \"require 'vendor/autoload.php'; use YourNamespace\\YourClass; echo 'Classes loaded OK';\""

github_actions:
  enabled: true
```

### Opcje Konfiguracyjne

#### `package_name`
Nazwa pakietu Composer (np. `lukaszzychal/phpstan-fixer`)

#### `php_versions`
Tablica wersji PHP do testowania (np. `['8.1', '8.2', '8.3', '8.4']`)

#### `frameworks`
Konfiguracje frameworków. Każdy framework może określić:
- `versions`: Wersje frameworka do testowania (obsługuje wildcardy jak `11.*`)
- `install_command`: Komenda do utworzenia nowego projektu frameworka
- `php_min_version`: Minimalna wymagana wersja PHP

#### `test_scripts`
Tablica skryptów testowych do uruchomienia. Każdy skrypt ma:
- `name`: Opisowa nazwa testu
- `command`: Komenda shell do wykonania

#### `github_actions`
Integracja GitHub Actions:
- `enabled`: Włącz/wyłącz workflow GitHub Actions

## Uruchamianie Testów

### Lokalnie

Uruchom testy kompatybilności lokalnie:

```bash
vendor/bin/compatibility-tester test
```

### Filtrowanie według Frameworka

Testuj tylko określone frameworki:

```bash
vendor/bin/compatibility-tester test --framework=laravel
```

### Filtrowanie według Wersji PHP

Testuj tylko określone wersje PHP:

```bash
vendor/bin/compatibility-tester test --php-version=8.3
```

## Integracja CI/CD

### GitHub Actions

Komenda init automatycznie tworzy `.github/workflows/compatibility-tests.yml`. Ten workflow:

- Uruchamia się co miesiąc (1. dnia każdego miesiąca o 2:00 UTC)
- Może być wywoływany ręcznie przez `workflow_dispatch`
- Testuje na wszystkich skonfigurowanych wersjach PHP
- Generuje raporty testowe jako artefakty

### Ręczna Integracja CI

Możesz również zintegrować z istniejącym pipeline CI:

```yaml
# .github/workflows/compatibility.yml
name: Compatibility Tests

on:
  schedule:
    - cron: '0 2 1 * *'  # Miesięcznie
  workflow_dispatch:

jobs:
  compatibility:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3', '8.4']
    
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      
      - name: Run compatibility tests
        run: vendor/bin/compatibility-tester test
```

## Niestandardowe Skrypty Testowe

### Tworzenie Skryptów Testowych

Utwórz niestandardowe skrypty testowe w `tests/compatibility/`:

```php
<?php
// tests/compatibility/check-autoload.php

require __DIR__ . '/../../vendor/autoload.php';

use YourNamespace\YourClass;

// Test, że klasy mogą być załadowane
$instance = new YourClass();
echo "Autoload OK\n";
```

### Dodawanie do Konfiguracji

Odwołaj się do swoich skryptów testowych w `.compatibility.yml`:

```yaml
test_scripts:
  - name: "Autoload test"
    script: "tests/compatibility/check-autoload.php"
    description: "Test ładowania klas"
  
  - name: "Basic functionality"
    script: "tests/compatibility/check-basic.php"
    description: "Test podstawowej funkcjonalności biblioteki"
```

## Rozwiązywanie Problemów

### Plik Konfiguracyjny Nie Znaleziony

Jeśli komenda `init` nie znajdzie przykładowej konfiguracji:

1. Sprawdź, czy plik istnieje:
   ```bash
   ls vendor/lukaszzychal/php-compatibility-tester/templates/config/.compatibility.yml.example
   ```

2. Jeśli nie znaleziono, użyj przykładu z fixture:
   ```bash
   cp vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml .compatibility.yml
   ```

3. Edytuj skopiowany plik, aby pasował do Twojego pakietu

### Testy Nie Przechodzą

Typowe problemy:

1. **Instalacja frameworka nie powiodła się**: Sprawdź `install_command` w konfiguracji
2. **Niezgodność wersji PHP**: Zweryfikuj, że `php_min_version` pasuje do wymagań frameworka
3. **Błędy autoload**: Upewnij się, że pakiet jest poprawnie skonfigurowany w `composer.json`
4. **Brakujące zależności**: Sprawdź, że wszystkie wymagane zależności są w `composer.json`

### GitHub Actions Nie Działa

1. Sprawdź, czy plik workflow istnieje: `.github/workflows/compatibility-tests.yml`
2. Zweryfikuj `github_actions.enabled: true` w `.compatibility.yml`
3. Sprawdź zakładkę GitHub Actions pod kątem błędów

## Przykładowa Konfiguracja

Zobacz przykładową konfigurację używaną przez `phpstan-fixer`:

- **Lokalizacja**: `.compatibility.yml` w tym repozytorium
- **Referencja**: `vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml`

## Powiązana Dokumentacja

- [PHP Compatibility Tester GitHub](https://github.com/lukaszzychal/php-compatibility-tester)
- [PHP Compatibility Tester Packagist](https://packagist.org/packages/lukaszzychal/php-compatibility-tester)
- [README.md](../README.md) - Główna dokumentacja projektu

