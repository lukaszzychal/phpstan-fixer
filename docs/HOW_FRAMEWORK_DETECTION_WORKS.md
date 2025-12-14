# Jak działa Framework Detection - Szczegółowe wyjaśnienie

## Przegląd procesu

Wykrywanie frameworków działa w kilku krokach podczas uruchamiania komendy `phpstan-fixer`:

```
1. Uruchomienie komendy
   ↓
2. Wykrywanie frameworka (FrameworkDetector)
   ↓
3. Wyświetlenie informacji użytkownikowi
   ↓
4. Tworzenie listy fixerów
   ↓
5. Filtrowanie fixerów specyficznych dla frameworka
   ↓
6. Uruchomienie analizy z przefiltrowanymi fixerami
```

## Krok 1: Uruchomienie komendy

```php
// W PhpstanAutoFixCommand::execute()
$framework = $this->detectFramework($io);
```

Gdy użytkownik uruchamia `phpstan-fixer suggest` lub `phpstan-fixer apply`, komenda rozpoczyna od wykrycia frameworka.

## Krok 2: Wykrywanie frameworka

### FrameworkDetector::detect()

Wykrywanie odbywa się w dwóch etapach:

#### 2.1. Sprawdzenie composer.json (priorytet)

```php
// FrameworkDetector.php, linia 33-40
$composerPath = rtrim($projectRoot, '/') . '/composer.json';
if (file_exists($composerPath)) {
    $framework = $this->detectFromComposer($composerPath);
    if ($framework !== null) {
        return $framework;
    }
}
```

**Jak to działa:**
- Czyta `composer.json` z katalogu projektu
- Sprawdza sekcję `require` pod kątem charakterystycznych pakietów
- Przykłady wykrywania:

```json
// Laravel
{
  "require": {
    "laravel/framework": "^10.0"  // ✅ Wykryje "laravel"
  }
}

// Symfony
{
  "require": {
    "symfony/symfony": "^6.0"     // ✅ Wykryje "symfony"
  }
}

// Symfony (z komponentów)
{
  "require": {
    "symfony/console": "^6.0",    // ✅ Wykryje "symfony" (≥2 komponenty)
    "symfony/http-foundation": "^6.0"
  }
}
```

**Kolejność sprawdzania:**
1. Laravel (`laravel/framework`)
2. Symfony (`symfony/symfony` lub ≥2 komponenty `symfony/*`)
3. CodeIgniter (`codeigniter4/framework`)
4. CakePHP (`cakephp/cakephp`)
5. Yii (`yiisoft/yii` lub `yiisoft/yii2`)
6. Laminas (`laminas/laminas-mvc`)
7. Phalcon (`phalcon/cphalcon`)

#### 2.2. Fallback: Sprawdzenie struktury katalogów

Jeśli `composer.json` nie zawiera informacji, sprawdzana jest struktura katalogów:

```php
// FrameworkDetector.php, linia 42-43
// Fall back to directory structure
return $this->detectFromDirectoryStructure($projectRoot);
```

**Wykrywanie przez strukturę:**

```php
// Laravel - wymaga ≥3 z tych wskaźników:
- /artisan        (plik)
- /app            (katalog)
- /config         (katalog)
- /routes         (katalog)

// Symfony - wymaga ≥3 z tych wskaźników:
- /symfony.lock   (plik)
- /src            (katalog)
- /config         (katalog)
- /public         (katalog)
```

## Krok 3: Wyświetlenie informacji

```php
// PhpstanAutoFixCommand.php, linia 120-123
$framework = $this->detectFramework($io);
if ($framework !== null) {
    $io->note("Detected framework: {$framework}");
}
```

**Przykład outputu:**
```
$ phpstan-fixer suggest
Note: Detected framework: laravel
```

## Krok 4: Tworzenie listy fixerów

```php
// PhpstanAutoFixCommand.php, linia 385-409
$allStrategies = [
    new MissingReturnDocblockFixer(...),      // Framework-agnostic
    new MissingParamDocblockFixer(...),       // Framework-agnostic
    new UndefinedPivotPropertyFixer(...),     // ⚠️ Laravel-specific!
    new UndefinedVariableFixer(...),          // Framework-agnostic
    // ... więcej fixerów
];
```

## Krok 5: Filtrowanie fixerów

### 5.1. Metoda filtrowania

```php
// PhpstanAutoFixCommand.php, linia 536-550
private function filterFrameworkSpecificFixers(array $strategies, ?string $framework): array
{
    if ($framework === null) {
        // Brak frameworka = wyklucz wszystkie framework-specific fixers
        return array_filter($strategies, function ($strategy): bool {
            return empty($strategy->getSupportedFrameworks());
        });
    }

    // Framework wykryty = dołącz framework-agnostic + pasujące framework-specific
    return array_filter($strategies, function ($strategy) use ($framework): bool {
        $supportedFrameworks = $strategy->getSupportedFrameworks();
        return empty($supportedFrameworks) || in_array($framework, $supportedFrameworks, true);
    });
}
```

### 5.2. Jak fixery deklarują wsparcie dla frameworków?

Każdy fixer implementuje metodę `getSupportedFrameworks()`:

```php
// UndefinedPivotPropertyFixer.php
public function getSupportedFrameworks(): array
{
    return ['laravel'];  // ✅ Tylko dla Laravel
}

// MissingReturnDocblockFixer (używa PriorityTrait)
public function getSupportedFrameworks(): array
{
    return [];  // ✅ Framework-agnostic (pusta tablica = działa wszędzie)
}
```

### 5.3. Przykłady filtrowania

#### Przykład 1: Projekt Laravel

**Wykrycie:** `framework = "laravel"`

**Lista fixerów przed filtrowaniem:**
- ✅ MissingReturnDocblockFixer (getSupportedFrameworks() = [])
- ✅ MissingParamDocblockFixer (getSupportedFrameworks() = [])
- ✅ UndefinedPivotPropertyFixer (getSupportedFrameworks() = ['laravel'])

**Po filtrowaniu:**
```php
// empty([]) || in_array('laravel', []) → true  ✅
// empty([]) || in_array('laravel', []) → true  ✅
// empty(['laravel']) || in_array('laravel', ['laravel']) → true  ✅
```

**Wynik:** Wszystkie 3 fixery są dołączone

#### Przykład 2: Projekt Symfony

**Wykrycie:** `framework = "symfony"`

**Lista fixerów przed filtrowaniem:**
- ✅ MissingReturnDocblockFixer (getSupportedFrameworks() = [])
- ✅ MissingParamDocblockFixer (getSupportedFrameworks() = [])
- ❌ UndefinedPivotPropertyFixer (getSupportedFrameworks() = ['laravel'])

**Po filtrowaniu:**
```php
// empty([]) || in_array('symfony', []) → true  ✅
// empty([]) || in_array('symfony', []) → true  ✅
// empty(['laravel']) || in_array('symfony', ['laravel']) → false  ❌
```

**Wynik:** Tylko 2 pierwsze fixery są dołączone

#### Przykład 3: Native PHP (brak frameworka)

**Wykrycie:** `framework = null`

**Lista fixerów przed filtrowaniem:**
- ✅ MissingReturnDocblockFixer (getSupportedFrameworks() = [])
- ✅ MissingParamDocblockFixer (getSupportedFrameworks() = [])
- ❌ UndefinedPivotPropertyFixer (getSupportedFrameworks() = ['laravel'])

**Po filtrowaniu:**
```php
// empty([]) → true  ✅
// empty([]) → true  ✅
// empty(['laravel']) → false  ❌
```

**Wynik:** Tylko 2 pierwsze fixery są dołączone

## Krok 6: Uruchomienie analizy

Przefiltrowana lista fixerów jest przekazywana do `AutoFixService`, który używa tylko odpowiednich fixerów do naprawy błędów PHPStan.

## Diagram przepływu

```
┌─────────────────────────────────────┐
│  phpstan-fixer suggest              │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  detectFramework()                  │
│  ┌──────────────────────────────┐   │
│  │ 1. Sprawdź composer.json     │   │
│  │    → laravel/framework?      │   │
│  │    → symfony/symfony?        │   │
│  │    → ...                     │   │
│  └──────────────────────────────┘   │
│  ┌──────────────────────────────┐   │
│  │ 2. Sprawdź strukturę katalogów│  │
│  │    → /artisan, /app?         │   │
│  │    → /symfony.lock, /src?    │   │
│  └──────────────────────────────┘   │
│  → zwróć "laravel" | "symfony" | null│
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  createDefaultAutoFixService()      │
│  ┌──────────────────────────────┐   │
│  │ Utwórz wszystkie fixery      │   │
│  └──────────────────────────────┘   │
│  ┌──────────────────────────────┐   │
│  │ filterFrameworkSpecificFixers│   │
│  │                              │   │
│  │ if (framework === null)      │   │
│  │   → tylko getSupportedFrameworks() == []│
│  │ else                         │   │
│  │   → getSupportedFrameworks() == []│
│  │     LUB                      │   │
│  │     framework in getSupportedFrameworks()│
│  └──────────────────────────────┘   │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  AutoFixService                     │
│  Używa tylko przefiltrowanych       │
│  fixerów do naprawy błędów          │
└─────────────────────────────────────┘
```

## Kluczowe koncepcje

### Framework-agnostic fixers
- Fixery, które działają w każdym projekcie PHP
- `getSupportedFrameworks()` zwraca `[]`
- **Zawsze** są dołączane

### Framework-specific fixers
- Fixery, które działają tylko w konkretnych frameworkach
- `getSupportedFrameworks()` zwraca np. `['laravel']`
- Są dołączane **tylko** gdy wykryty framework pasuje

### Priorytety wykrywania
1. **composer.json** (najbardziej niezawodne)
2. **Struktura katalogów** (fallback)
3. **Kolejność sprawdzania** (Laravel → Symfony → inne)

## Przykłady użycia

### Projekt Laravel
```bash
$ phpstan-fixer suggest
Note: Detected framework: laravel

# Używane fixery:
# ✅ MissingReturnDocblockFixer (framework-agnostic)
# ✅ MissingParamDocblockFixer (framework-agnostic)
# ✅ UndefinedPivotPropertyFixer (laravel-specific) ← DOŁĄCZONY!
```

### Projekt Symfony
```bash
$ phpstan-fixer suggest
Note: Detected framework: symfony

# Używane fixery:
# ✅ MissingReturnDocblockFixer (framework-agnostic)
# ✅ MissingParamDocblockFixer (framework-agnostic)
# ❌ UndefinedPivotPropertyFixer (laravel-specific) ← WYKLUCZONY!
```

### Native PHP
```bash
$ phpstan-fixer suggest
# (brak komunikatu o frameworku)

# Używane fixery:
# ✅ MissingReturnDocblockFixer (framework-agnostic)
# ✅ MissingParamDocblockFixer (framework-agnostic)
# ❌ UndefinedPivotPropertyFixer (laravel-specific) ← WYKLUCZONY!
```

## Rozszerzanie

### Jak dodać nowy framework?

1. **W FrameworkDetector::detectFromComposer():**
```php
if (isset($require['nowy-framework/package'])) {
    return 'nowy-framework';
}
```

2. **W FrameworkDetector::detectFromDirectoryStructure():**
```php
// Dodaj wskaźniki struktury katalogów
$nowyFrameworkIndicators = [...];
```

### Jak utworzyć framework-specific fixer?

```php
class NowyFrameworkFixer implements FixStrategyInterface
{
    use PriorityTrait;

    public function getSupportedFrameworks(): array
    {
        return ['nowy-framework'];  // ✅ Tylko dla tego frameworka
    }

    // ... reszta implementacji
}
```

