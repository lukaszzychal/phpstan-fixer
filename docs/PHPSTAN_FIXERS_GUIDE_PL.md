# Przewodnik po Fixerach PHPStan

Ten dokument opisuje wszystkie dostępne fixery w bibliotece `phpstan-fixer` oraz problemy PHPStan, które rozwiązują.

> 🇬🇧 **English version**: [PHPStan Fixers Guide (EN)](PHPSTAN_FIXERS_GUIDE.md)

## Spis treści

1. [MissingReturnDocblockFixer](#1-missingreturndocblockfixer)
2. [MissingParamDocblockFixer](#2-missingparamdocblockfixer)
3. [MissingPropertyDocblockFixer](#3-missingpropertydocblockfixer)
4. [UndefinedPivotPropertyFixer](#4-undefinedpivotpropertyfixer)
5. [CollectionGenericDocblockFixer](#5-collectiongenericdocblockfixer)
6. [UndefinedVariableFixer](#6-undefinedvariablefixer)
7. [MissingUseStatementFixer](#7-missingusestatementfixer)
8. [UndefinedMethodFixer](#8-undefinedmethodfixer)
9. [MissingThrowsDocblockFixer](#9-missingthrowsdocblockfixer)
10. [CallableTypeFixer](#10-callabletypefixer)
11. [MixinFixer](#11-mixinfixer)

---

## 1. MissingReturnDocblockFixer

### Problem PHPStan

**Błąd:** `Method has no return type specified` lub `Return type is missing`

PHPStan zgłasza błąd, gdy metoda lub funkcja nie ma zdefiniowanego typu zwracanego ani adnotacji `@return` w PHPDoc.

### Przykładowy kod przed naprawą:

```php
function calculateSum($a, $b) {
    return $a + $b;
}
```

**Błąd PHPStan:**
```
Method calculateSum() has no return type specified
```

### Rozwiązanie

Fixer automatycznie dodaje adnotację `@return mixed` do docblocka metody/funkcji:

```php
/**
 * @return mixed
 */
function calculateSum($a, $b) {
    return $a + $b;
}
```

### Wzorce błędów wykrywane:

- `Return type is missing`
- `Method has no return type specified`
- `Function has no return type specified`

### Kiedy używać:

- Gdy metoda nie ma typu zwracanego w deklaracji (PHP < 8.0)
- Gdy metoda nie ma adnotacji `@return` w PHPDoc
- Gdy PHPStan wymaga informacji o typie zwracanym

---

## 2. MissingParamDocblockFixer

### Problem PHPStan

**Błąd:** `Parameter #X $paramName has no type specified`

PHPStan zgłasza błąd, gdy parametr funkcji/metody nie ma zdefiniowanego typu ani adnotacji `@param` w PHPDoc.

### Przykładowy kod przed naprawą:

```php
function greet($name, $age) {
    return "Hello, $name! You are $age years old.";
}
```

**Błąd PHPStan:**
```
Parameter #1 $name has no type specified
Parameter #2 $age has no type specified
```

### Rozwiązanie

Fixer automatycznie dodaje adnotacje `@param mixed` dla każdego parametru:

```php
/**
 * @param mixed $name
 * @param mixed $age
 */
function greet($name, $age) {
    return "Hello, $name! You are $age years old.";
}
```

### Wzorce błędów wykrywane:

- `Parameter.*has no type specified`
- `Parameter #X $name has no type specified`

### Kiedy używać:

- Gdy parametry nie mają typów w deklaracji (PHP < 8.0)
- Gdy brakuje adnotacji `@param` w PHPDoc
- Gdy PHPStan wymaga informacji o typach parametrów

---

## 3. MissingPropertyDocblockFixer

### Problem PHPStan

**Błąd:** `Access to an undefined property $propertyName`

PHPStan zgłasza błąd, gdy kod próbuje uzyskać dostęp do właściwości, która nie jest zdefiniowana w klasie (np. dynamiczne właściwości w Laravel Eloquent, magic properties).

### Przykładowy kod przed naprawą:

```php
class User extends Model
{
    // Brak właściwości $email w klasie
}

// Użycie:
$user = new User();
$email = $user->email; // Błąd PHPStan
```

**Błąd PHPStan:**
```
Access to an undefined property User::$email
```

### Rozwiązanie

Fixer automatycznie dodaje adnotację `@property` lub `@var` do docblocka klasy:

```php
/**
 * @property string $email
 */
class User extends Model
{
}
```

### Wzorce błędów wykrywane:

- `Access to an undefined property`
- `Access to an undefined property $name`

### Kiedy używać:

- Laravel Eloquent models (dynamiczne właściwości z bazy danych)
- Klasy z magic properties (`__get`, `__set`)
- Dynamiczne właściwości w frameworkach

**Uwaga:** Nie obsługuje właściwości `$pivot` - ta jest obsługiwana przez `UndefinedPivotPropertyFixer`.

---

## 4. UndefinedPivotPropertyFixer

### Problem PHPStan

**Błąd:** `Access to an undefined property Model::$pivot`

PHPStan zgłasza błąd, gdy kod próbuje uzyskać dostęp do właściwości `$pivot` w modelach Laravel Eloquent, która jest dostępna tylko w kontekście relacji many-to-many.

### Przykładowy kod przed naprawą:

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

// Użycie:
$user->roles()->first()->pivot; // Błąd PHPStan
```

**Błąd PHPStan:**
```
Access to an undefined property Role::$pivot
```

### Rozwiązanie

Fixer automatycznie dodaje adnotację `@property-read` do docblocka klasy modelu:

```php
/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 */
class Role extends Model
{
}
```

### Wzorce błędów wykrywane:

- `Access to an undefined property.*\$pivot`
- Właściwość o nazwie `pivot`

### Kiedy używać:

- Laravel Eloquent models z relacjami many-to-many
- Gdy używasz `->pivot` do dostępu do danych tabeli pośredniej

---

## 5. CollectionGenericDocblockFixer

### Problem PHPStan

**Błąd:** `Generic type Collection needs parameters` lub `Generic type Illuminate\Support\Collection needs parameters`

PHPStan wymaga, aby typy generyczne Collection miały określone parametry typów (klucz i wartość).

### Przykładowy kod przed naprawą:

```php
/**
 * @return Collection
 */
public function getItems()
{
    return collect([1, 2, 3]);
}
```

**Błąd PHPStan:**
```
Generic type Illuminate\Support\Collection needs parameters: Collection<int, mixed>
```

### Rozwiązanie

Fixer automatycznie dodaje parametry generyczne do typu Collection:

```php
/**
 * @return Collection<int, mixed>
 */
public function getItems()
{
    return collect([1, 2, 3]);
}
```

### Wzorce błędów wykrywane:

- `Generic.*Collection.*needs parameters`
- `Generic type.*Collection.*needs.*parameters`

### Kiedy używać:

- Gdy używasz `Illuminate\Support\Collection` bez parametrów generycznych
- Gdy PHPStan wymaga pełnej specyfikacji typu generycznego
- Level PHPStan 6+ (wymaga parametrów generycznych)

---

## 6. UndefinedVariableFixer

### Problem PHPStan

**Błąd:** `Undefined variable $variableName` lub `Variable $variableName is undefined`

PHPStan zgłasza błąd, gdy zmienna jest używana bez wcześniejszej deklaracji lub inicjalizacji.

### Przykładowy kod przed naprawą:

```php
function processData($items) {
    foreach ($items as $item) {
        // $result jest używana, ale nie zadeklarowana
        $result[] = $item * 2;
    }
    return $result;
}
```

**Błąd PHPStan:**
```
Undefined variable $result
```

### Rozwiązanie

Fixer automatycznie dodaje inline adnotację `@var` przed użyciem zmiennej:

```php
function processData($items) {
    /** @var array $result */
    foreach ($items as $item) {
        $result[] = $item * 2;
    }
    return $result;
}
```

### Wzorce błędów wykrywane:

- `Undefined variable`
- `Variable.*is undefined`

### Kiedy używać:

- Gdy zmienna jest inicjalizowana dynamicznie (np. w pętli)
- Gdy PHPStan nie może wywnioskować typu zmiennej
- Gdy zmienna jest używana przed deklaracją

---

## 7. MissingUseStatementFixer

### Problem PHPStan

**Błąd:** `Class ClassName not found` lub `Cannot resolve symbol ClassName`

PHPStan zgłasza błąd, gdy klasa jest używana, ale nie została zaimportowana przez `use` statement lub nie jest w pełnej kwalifikowanej nazwie (FQN).

### Przykładowy kod przed naprawą:

```php
namespace App\Services;

class UserService
{
    public function create(DateTime $date) // Błąd: DateTime nie jest zaimportowane
    {
        return new User();
    }
}
```

**Błąd PHPStan:**
```
Class DateTime not found
Class User not found
```

### Rozwiązanie

Fixer automatycznie dodaje odpowiednie `use` statements na początku pliku:

```php
namespace App\Services;

use DateTime;
use App\Models\User;

class UserService
{
    public function create(DateTime $date)
    {
        return new User();
    }
}
```

### Wzorce błędów wykrywane:

- `Class.*not found`
- `Cannot resolve symbol`
- `Class.*does not exist`

### Kiedy używać:

- Gdy brakuje importów klas
- Gdy używasz klas bez pełnej kwalifikowanej nazwy
- Gdy PHPStan nie może rozpoznać klasy

**Ograniczenia:**
- Wymaga, aby klasa była dostępna w autoloaderze
- Nie wyszukuje klas w vendor/ automatycznie
- Może wymagać ręcznej korekty dla niestandardowych ścieżek

---

## 8. UndefinedMethodFixer

### Problem PHPStan

**Błąd:** `Call to an undefined method ClassName::methodName()`

PHPStan zgłasza błąd, gdy metoda jest wywoływana, ale nie istnieje w klasie (np. magic methods).

### Przykładowy kod przed naprawą:

```php
class Model
{
    public function __call($name, $arguments)
    {
        // Magic method handler
    }
}

$model = new Model();
$model->getData(); // Błąd PHPStan
```

**Błąd PHPStan:**
```
Call to an undefined method Model::getData()
```

### Rozwiązanie

Fixer automatycznie dodaje adnotację `@method` do docblocka klasy:

```php
/**
 * @method mixed getData()
 */
class Model
{
    public function __call($name, $arguments)
    {
        // Magic method handler
    }
}
```

### Wzorce błędów wykrywane:

- `Call to an undefined method`
- `Call to an undefined method ClassName::methodName()`

### Kiedy używać:

- Klasy z magic methods (`__call`, `__callStatic`)
- Frameworki z dynamicznymi metodami (np. Laravel Query Builder)
- Proxy/Wrapper klasy delegujące wywołania

**Uwaga:** Dla bardziej zaawansowanych przypadków delegacji użyj `MixinFixer`.

---

## 9. MissingThrowsDocblockFixer

### Problem PHPStan

**Błąd:** `Method throws ExceptionType but @throws annotation is missing`

PHPStan wymaga dokumentacji wszystkich wyjątków, które mogą być rzucone przez metodę/funkcję.

### Przykładowy kod przed naprawą:

```php
function divide($a, $b) {
    if ($b === 0) {
        throw new DivisionByZeroError("Cannot divide by zero");
    }
    return $a / $b;
}
```

**Błąd PHPStan:**
```
Method divide() throws DivisionByZeroError but @throws annotation is missing
```

### Rozwiązanie

Fixer automatycznie dodaje adnotację `@throws` do docblocka:

```php
/**
 * @throws \DivisionByZeroError
 */
function divide($a, $b) {
    if ($b === 0) {
        throw new DivisionByZeroError("Cannot divide by zero");
    }
    return $a / $b;
}
```

### Wzorce błędów wykrywane:

- `@throws.*annotation is missing`
- `throws exception.*but.*@throws`

### Kiedy używać:

- Gdy metoda rzuca wyjątki
- Gdy PHPStan wymaga dokumentacji wyjątków
- Level PHPStan 5+ (wymaga dokumentacji wyjątków)

---

## 10. CallableTypeFixer

### Problem PHPStan

**Błąd:** `Parameter expects callable` lub `callable is invoked`

PHPStan zgłasza błąd, gdy callable jest przekazywany jako parametr lub wywoływany, ale typ nie jest odpowiednio zdefiniowany.

### Przykładowy kod przed naprawą:

```php
function process($callback) {
    return $callback(); // Wywołanie callable
}

process(function() { return 'result'; });
```

**Błąd PHPStan:**
```
Parameter #1 $callback expects callable, but callable is invoked immediately
```

### Rozwiązanie

Fixer automatycznie dodaje odpowiednią adnotację `@param-immediately-invoked-callable` lub `@param-later-invoked-callable`:

```php
/**
 * @param-immediately-invoked-callable(): string $callback
 */
function process($callback) {
    return $callback();
}
```

### Wzorce błędów wykrywane:

- `callable.*invoked`
- `Parameter.*expects callable`

### Kiedy używać:

- Gdy przekazujesz callable jako parametr
- Gdy callable jest wywoływany natychmiast lub później
- Gdy PHPStan wymaga specyfikacji typu callable

---

## 11. MixinFixer

### Problem PHPStan

**Błąd:** `Call to an undefined method ClassName::methodName()` lub `Access to an undefined property ClassName::$property`

PHPStan zgłasza błąd, gdy klasa używa magic methods (`__call`, `__get`, `__set`) do delegacji wywołań do innej klasy, ale PHPStan nie wie, jakie metody/właściwości są dostępne.

### Przykładowy kod przed naprawą:

```php
class Wrapper
{
    private OriginalClass $delegate;
    
    public function __call($name, $arguments)
    {
        return $this->delegate->$name(...$arguments);
    }
    
    public function __get($name)
    {
        return $this->delegate->$name;
    }
}

$wrapper = new Wrapper();
$wrapper->someMethod(); // Błąd PHPStan
$value = $wrapper->property; // Błąd PHPStan
```

**Błąd PHPStan:**
```
Call to an undefined method Wrapper::someMethod()
Access to an undefined property Wrapper::$property
```

### Rozwiązanie

Fixer automatycznie analizuje kod, znajduje klasę delegowaną i dodaje adnotację `@mixin`:

```php
/**
 * @mixin OriginalClass
 */
class Wrapper
{
    private OriginalClass $delegate;
    
    public function __call($name, $arguments)
    {
        return $this->delegate->$name(...$arguments);
    }
    
    public function __get($name)
    {
        return $this->delegate->$name;
    }
}
```

### Strategia wykrywania klasy delegowanej:

1. **Analiza AST magic methods** - analizuje ciało `__call`, `__get`, `__set` i znajduje właściwość używaną do delegacji (np. `$this->delegate->$name(...)`)
2. **Wyszukiwanie właściwości** - szuka właściwości o typowych nazwach: `delegate`, `delegator`, `target`, `handler`, `wrapped`, `inner`, `backing`
3. **Wyciąganie typu** - pobiera typ z:
   - Deklaracji właściwości (`private OriginalClass $delegate`)
   - PHPDoc właściwości (`@var OriginalClass`)
   - PHPDoc klasy (`@property OriginalClass $delegate`)

### Wzorce błędów wykrywane:

- `Call to an undefined method` (gdy klasa ma `__call`)
- `Access to an undefined property` (gdy klasa ma `__get`/`__set`)

### Kiedy używać:

- Klasy wrapper/proxy delegujące wywołania
- Klasy używające magic methods do delegacji
- Pattern Decorator/Adapter z delegacją

### Różnica względem UndefinedMethodFixer:

- **UndefinedMethodFixer**: Dodaje `@method` dla pojedynczych metod magic
- **MixinFixer**: Dodaje `@mixin` dla całej klasy delegowanej (wszystkie metody i właściwości naraz)

---

## Podsumowanie

Każdy fixer rozwiązuje specyficzny problem PHPStan związany z brakującą dokumentacją typów lub magic behavior w PHP. Biblioteka automatycznie wykrywa odpowiedni fixer dla każdego błędu PHPStan i próbuje go naprawić.

### Jak używać:

```bash
# Tryb sugestii (preview zmian)
vendor/bin/phpstan-fixer --mode=suggest

# Tryb aplikacji (zapisuje zmiany)
vendor/bin/phpstan-fixer --mode=apply
```

### Konfiguracja:

Możesz skonfigurować, które błędy są naprawiane, ignorowane lub tylko raportowane w pliku `phpstan-fixer.yaml`:

```yaml
rules:
  "Access to an undefined property":
    action: "fix"
  "Method has no return type":
    action: "fix"
  "Unknown class":
    action: "ignore"
```

Więcej informacji: [README.md](../README.md#configuration-file)

---

## Zobacz też

- [README.md](../README.md) - Główna dokumentacja biblioteki
- [PHPStan Fixers Guide (EN)](PHPSTAN_FIXERS_GUIDE.md) - English version
- [CONFIGURATION_FEATURE.md](../CONFIGURATION_FEATURE.md) - Dokumentacja systemu konfiguracji
- [TODO.md](../TODO.md) - Lista planowanych fixerów

