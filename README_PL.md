# PHPStan Auto-Fix

[![CI](https://github.com/lukaszzychal/phpstan-fixer/workflows/CI/badge.svg)](https://github.com/lukaszzychal/phpstan-fixer/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Framework-agnostic biblioteka PHP do automatycznego naprawiania błędów PHPStan używając reguł statycznej analizy. Działa z Laravel, Symfony, CodeIgniter i natywnymi projektami PHP.

## Funkcje

- Automatycznie wykrywa i naprawia najczęstsze błędy PHPStan
- Framework-agnostic (działa z każdym projektem PHP)
- Działa offline (nie wymaga AI ani dostępu do Internetu)
- Tryb suggest (podgląd zmian) i tryb apply (zapis zmian)
- Obsługuje wiele strategii naprawy dla różnych typów błędów

## Instalacja

```bash
composer require --dev lukaszzychal/phpstan-fixer
```

## Użycie

### Podstawowe Użycie (Domyślne - Tryb Suggest)

Domyślnie narzędzie uruchamia się w trybie `suggest` - pokazuje co zostanie zmienione bez modyfikacji plików:

```bash
vendor/bin/phpstan-fixer
```

To jest równoważne z:
```bash
vendor/bin/phpstan-fixer --mode=suggest
```

### Tryb Suggest (Podgląd Zmian)

Podgląd proponowanych poprawek bez modyfikacji plików (tak samo jak domyślny):

```bash
vendor/bin/phpstan-fixer --mode=suggest
```

**Uwaga:** Tryb suggest jest bezpieczny - tylko pokazuje co zostanie zmienione i NIE modyfikuje plików.

### Tryb Apply (Zapis Zmian)

Zastosuj poprawki bezpośrednio do plików:

```bash
vendor/bin/phpstan-fixer --mode=apply
```

**Ostrzeżenie:** Tryb apply zmodyfikuje pliki źródłowe. Zawsze najpierw przejrzyj zmiany w trybie suggest!

### Użycie Istniejącego Outputu JSON z PHPStan

Jeśli masz już plik z outputem JSON z PHPStan:

```bash
vendor/bin/phpstan-fixer --input=phpstan-output.json --mode=apply
```

### Własna Komenda PHPStan

Określ własną komendę PHPStan:

```bash
vendor/bin/phpstan-fixer --phpstan-command="vendor/bin/phpstan analyse src tests --level=5 --error-format=json" --mode=apply
```

## Obsługiwane Strategie Naprawy

Biblioteka automatycznie naprawia następujące typy błędów PHPStan:

1. **Brakujący Typ Zwrotu** (`MissingReturnDocblockFixer`)
   - Dodaje adnotacje `@return` gdy brakuje typu zwrotu

2. **Brakujący Typ Parametru** (`MissingParamDocblockFixer`)
   - Dodaje adnotacje `@param` dla parametrów bez typów

3. **Niezdefiniowane Właściwości** (`MissingPropertyDocblockFixer`)
   - Dodaje adnotacje `@property` lub `@var` dla niezdefiniowanych właściwości

4. **Właściwość Pivot Eloquent** (`UndefinedPivotPropertyFixer`)
   - Dodaje adnotację `@property-read` dla właściwości `$pivot` w Laravel Eloquent

5. **Generyki Kolekcji** (`CollectionGenericDocblockFixer`)
   - Dodaje parametry generyczne do typów Collection (np. `Collection<int, mixed>`)

6. **Niezdefiniowane Zmienne** (`UndefinedVariableFixer`)
   - Dodaje inline adnotacje `@var` dla niezdefiniowanych zmiennych

7. **Brakujące Use Statements** (`MissingUseStatementFixer`)
   - Dodaje instrukcje `use` dla niezdefiniowanych klas

8. **Niezdefiniowane Metody** (`UndefinedMethodFixer`)
   - Dodaje adnotacje `@method` dla magic methods

9. **Brakująca Adnotacja Throws** (`MissingThrowsDocblockFixer`)
   - Dodaje adnotacje `@throws` gdy wyrzucane są wyjątki

10. **Typ Callable Invocation** (`CallableTypeFixer`)
    - Dodaje adnotacje `@param-immediately-invoked-callable` lub `@param-later-invoked-callable`

## Przykłady

### Przykład 1: Brakujący Typ Zwrotu

**Przed:**
```php
function calculateSum($a, $b) {
    return $a + $b;
}
```

**Po:**
```php
/**
 * @return mixed
 */
function calculateSum($a, $b) {
    return $a + $b;
}
```

### Przykład 2: Niezdefiniowana Właściwość

**Przed:**
```php
class User {
    public function getName() {
        return $this->name; // Błąd PHPStan: niezdefiniowana właściwość
    }
}
```

**Po:**
```php
/**
 * @property string $name
 */
class User {
    public function getName() {
        return $this->name;
    }
}
```

### Przykład 3: Generyki Kolekcji

**Przed:**
```php
/**
 * @return Collection
 */
function getItems() {
    return collect([]);
}
```

**Po:**
```php
/**
 * @return Collection<int, mixed>
 */
function getItems() {
    return collect([]);
}
```

## Konfiguracja

Narzędzie działa od razu z domyślnymi ustawieniami. Wszystkie strategie naprawy są domyślnie włączone.

## Jak to Działa

1. Uruchamia PHPStan (lub czyta istniejący output JSON)
2. Parsuje output JSON z PHPStan na obiekty Issue
3. Dopasowuje każde zgłoszenie do odpowiednich strategii naprawy
4. Stosuje poprawki używając parsowania AST i manipulacji PHPDoc
5. Pokazuje podgląd (tryb suggest) lub zapisuje zmiany (tryb apply)

## Wymagania

- PHP 8.0 lub wyższe
- PHPStan (zainstalowany przez Composer)
- nikic/php-parser (instalowany automatycznie)

## Rozwój

### Uruchamianie Testów

```bash
vendor/bin/phpunit
```

### CI/CD

Projekt używa GitHub Actions do ciągłej integracji:

- **Workflow CI**: Uruchamia testy na PHP 8.0-8.3, analizę statyczną i sprawdzanie stylu kodu
- **Workflow Release**: Automatycznie tworzy release na GitHub przy tagach wersji
- **Workflow Self-Test**: Testuje PHPStan Fixer na własnym kodzie

Zobacz [`.github/workflows/`](.github/workflows/) po szczegóły.

### Współtworzenie

Wkłady są mile widziane! Możesz śmiało przesłać Pull Request.

## FAQ

### Jaka jest różnica między trybami wykonania?

Istnieją trzy sposoby uruchomienia narzędzia:

1. **`vendor/bin/phpstan-fixer`** (domyślny, bez parametrów)
   - Równoważne z `--mode=suggest`
   - Pokazuje co zostanie zmienione
   - NIE modyfikuje plików
   - Bezpieczne do uruchomienia - tylko podgląd

2. **`vendor/bin/phpstan-fixer --mode=suggest`**
   - Explicit tryb suggest (taki sam jak domyślny)
   - Pokazuje proponowane zmiany
   - NIE modyfikuje plików
   - Bezpieczne do uruchomienia - tylko podgląd

3. **`vendor/bin/phpstan-fixer --mode=apply`**
   - Faktycznie zapisuje zmiany do plików
   - Modyfikuje kod źródłowy
   - Używaj ostrożnie - tworzy trwałe zmiany

**Rekomendacja:** Zawsze najpierw uruchom z `--mode=suggest`, aby przejrzeć zmiany przed ich zastosowaniem.

### Co się dzieje z błędami, których nie można naprawić?

Jeśli strategia naprawy nie może automatycznie naprawić błędu, zostanie on wyświetlony na końcu outputu w formacie PHPStan. To są błędy wymagające ręcznej interwencji lub nieobsługiwane jeszcze przez żadną strategię naprawy.

## Licencja

MIT License - zobacz plik LICENSE po szczegóły.

## Autor

**Łukasz Zychal**

- Email: [lukasz.zychal.dev@gmail.com](mailto:lukasz.zychal.dev@gmail.com)
- GitHub Issues: [Zgłoś błędy i problemy](https://github.com/lukaszzychal/phpstan-fixer/issues)
- Dyskusje: [Dołącz do dyskusji](https://github.com/lukaszzychal/phpstan-fixer/discussions)

## Współtworzenie

Wkłady są mile widziane! Możesz śmiało przesłać Pull Request.

Dla zgłoszeń błędów i prośb o funkcje, użyj strony [GitHub Issues](https://github.com/lukaszzychal/phpstan-fixer/issues).

