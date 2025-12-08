# PHPStan Fixers Guide

This document describes all available fixers in the `phpstan-fixer` library and the PHPStan problems they solve.

> 🇵🇱 **Polish version**: [Przewodnik po Fixerach PHPStan (PL)](PHPSTAN_FIXERS_GUIDE_PL.md)

## Table of Contents

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

### PHPStan Problem

**Error:** `Method has no return type specified` or `Return type is missing`

PHPStan reports an error when a method or function doesn't have a defined return type or `@return` annotation in PHPDoc.

### Example Code Before Fix:

```php
function calculateSum($a, $b) {
    return $a + $b;
}
```

**PHPStan Error:**
```
Method calculateSum() has no return type specified
```

### Solution

The fixer automatically adds a `@return mixed` annotation to the method/function docblock:

```php
/**
 * @return mixed
 */
function calculateSum($a, $b) {
    return $a + $b;
}
```

### Error Patterns Detected:

- `Return type is missing`
- `Method has no return type specified`
- `Function has no return type specified`

### When to Use:

- When a method doesn't have a return type in declaration (PHP < 8.0)
- When a method lacks `@return` annotation in PHPDoc
- When PHPStan requires return type information

---

## 2. MissingParamDocblockFixer

### PHPStan Problem

**Error:** `Parameter #X $paramName has no type specified`

PHPStan reports an error when a function/method parameter doesn't have a defined type or `@param` annotation in PHPDoc.

### Example Code Before Fix:

```php
function greet($name, $age) {
    return "Hello, $name! You are $age years old.";
}
```

**PHPStan Error:**
```
Parameter #1 $name has no type specified
Parameter #2 $age has no type specified
```

### Solution

The fixer automatically adds `@param mixed` annotations for each parameter:

```php
/**
 * @param mixed $name
 * @param mixed $age
 */
function greet($name, $age) {
    return "Hello, $name! You are $age years old.";
}
```

### Error Patterns Detected:

- `Parameter.*has no type specified`
- `Parameter #X $name has no type specified`

### When to Use:

- When parameters don't have types in declaration (PHP < 8.0)
- When `@param` annotations are missing in PHPDoc
- When PHPStan requires parameter type information

---

## 3. MissingPropertyDocblockFixer

### PHPStan Problem

**Error:** `Access to an undefined property $propertyName`

PHPStan reports an error when code tries to access a property that is not defined in the class (e.g., dynamic properties in Laravel Eloquent, magic properties).

### Example Code Before Fix:

```php
class User extends Model
{
    // $email property is not defined in the class
}

// Usage:
$user = new User();
$email = $user->email; // PHPStan Error
```

**PHPStan Error:**
```
Access to an undefined property User::$email
```

### Solution

The fixer automatically adds a `@property` or `@var` annotation to the class docblock:

```php
/**
 * @property string $email
 */
class User extends Model
{
}
```

### Error Patterns Detected:

- `Access to an undefined property`
- `Access to an undefined property $name`

### When to Use:

- Laravel Eloquent models (dynamic properties from database)
- Classes with magic properties (`__get`, `__set`)
- Dynamic properties in frameworks

**Note:** Does not handle `$pivot` property - that is handled by `UndefinedPivotPropertyFixer`.

---

## 4. UndefinedPivotPropertyFixer

### PHPStan Problem

**Error:** `Access to an undefined property Model::$pivot`

PHPStan reports an error when code tries to access the `$pivot` property in Laravel Eloquent models, which is only available in the context of many-to-many relationships.

### Example Code Before Fix:

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

// Usage:
$user->roles()->first()->pivot; // PHPStan Error
```

**PHPStan Error:**
```
Access to an undefined property Role::$pivot
```

### Solution

The fixer automatically adds a `@property-read` annotation to the model class docblock:

```php
/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 */
class Role extends Model
{
}
```

### Error Patterns Detected:

- `Access to an undefined property.*\$pivot`
- Property named `pivot`

### When to Use:

- Laravel Eloquent models with many-to-many relationships
- When using `->pivot` to access intermediate table data

---

## 5. CollectionGenericDocblockFixer

### PHPStan Problem

**Error:** `Generic type Collection needs parameters` or `Generic type Illuminate\Support\Collection needs parameters`

PHPStan requires that generic Collection types have specified type parameters (key and value).

### Example Code Before Fix:

```php
/**
 * @return Collection
 */
public function getItems()
{
    return collect([1, 2, 3]);
}
```

**PHPStan Error:**
```
Generic type Illuminate\Support\Collection needs parameters: Collection<int, mixed>
```

### Solution

The fixer automatically adds generic parameters to the Collection type:

```php
/**
 * @return Collection<int, mixed>
 */
public function getItems()
{
    return collect([1, 2, 3]);
}
```

### Error Patterns Detected:

- `Generic.*Collection.*needs parameters`
- `Generic type.*Collection.*needs.*parameters`

### When to Use:

- When using `Illuminate\Support\Collection` without generic parameters
- When PHPStan requires full generic type specification
- PHPStan Level 6+ (requires generic parameters)

---

## 6. UndefinedVariableFixer

### PHPStan Problem

**Error:** `Undefined variable $variableName` or `Variable $variableName is undefined`

PHPStan reports an error when a variable is used without prior declaration or initialization.

### Example Code Before Fix:

```php
function processData($items) {
    foreach ($items as $item) {
        // $result is used but not declared
        $result[] = $item * 2;
    }
    return $result;
}
```

**PHPStan Error:**
```
Undefined variable $result
```

### Solution

The fixer automatically adds an inline `@var` annotation before the variable usage:

```php
function processData($items) {
    /** @var array $result */
    foreach ($items as $item) {
        $result[] = $item * 2;
    }
    return $result;
}
```

### Error Patterns Detected:

- `Undefined variable`
- `Variable.*is undefined`

### When to Use:

- When a variable is initialized dynamically (e.g., in a loop)
- When PHPStan cannot infer the variable type
- When a variable is used before declaration

---

## 7. MissingUseStatementFixer

### PHPStan Problem

**Error:** `Class ClassName not found` or `Cannot resolve symbol ClassName`

PHPStan reports an error when a class is used but hasn't been imported via `use` statement or is not in a fully qualified name (FQN).

### Example Code Before Fix:

```php
namespace App\Services;

class UserService
{
    public function create(DateTime $date) // Error: DateTime is not imported
    {
        return new User();
    }
}
```

**PHPStan Error:**
```
Class DateTime not found
Class User not found
```

### Solution

The fixer automatically adds appropriate `use` statements at the beginning of the file:

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

### Error Patterns Detected:

- `Class.*not found`
- `Cannot resolve symbol`
- `Class.*does not exist`

### When to Use:

- When class imports are missing
- When using classes without fully qualified names
- When PHPStan cannot recognize a class

**Limitations:**
- Requires the class to be available in the autoloader
- Does not automatically search for classes in vendor/
- May require manual correction for custom paths

---

## 8. UndefinedMethodFixer

### PHPStan Problem

**Error:** `Call to an undefined method ClassName::methodName()`

PHPStan reports an error when a method is called but doesn't exist in the class (e.g., magic methods).

### Example Code Before Fix:

```php
class Model
{
    public function __call($name, $arguments)
    {
        // Magic method handler
    }
}

$model = new Model();
$model->getData(); // PHPStan Error
```

**PHPStan Error:**
```
Call to an undefined method Model::getData()
```

### Solution

The fixer automatically adds a `@method` annotation to the class docblock:

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

### Error Patterns Detected:

- `Call to an undefined method`
- `Call to an undefined method ClassName::methodName()`

### When to Use:

- Classes with magic methods (`__call`, `__callStatic`)
- Frameworks with dynamic methods (e.g., Laravel Query Builder)
- Proxy/Wrapper classes delegating calls

**Note:** For more advanced delegation cases, use `MixinFixer`.

---

## 9. MissingThrowsDocblockFixer

### PHPStan Problem

**Error:** `Method throws ExceptionType but @throws annotation is missing`

PHPStan requires documentation of all exceptions that may be thrown by a method/function.

### Example Code Before Fix:

```php
function divide($a, $b) {
    if ($b === 0) {
        throw new DivisionByZeroError("Cannot divide by zero");
    }
    return $a / $b;
}
```

**PHPStan Error:**
```
Method divide() throws DivisionByZeroError but @throws annotation is missing
```

### Solution

The fixer automatically adds a `@throws` annotation to the docblock:

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

### Error Patterns Detected:

- `@throws.*annotation is missing`
- `throws exception.*but.*@throws`

### When to Use:

- When a method throws exceptions
- When PHPStan requires exception documentation
- PHPStan Level 5+ (requires exception documentation)

---

## 10. CallableTypeFixer

### PHPStan Problem

**Error:** `Parameter expects callable` or `callable is invoked`

PHPStan reports an error when a callable is passed as a parameter or invoked, but the type is not properly defined.

### Example Code Before Fix:

```php
function process($callback) {
    return $callback(); // Invoking callable
}

process(function() { return 'result'; });
```

**PHPStan Error:**
```
Parameter #1 $callback expects callable, but callable is invoked immediately
```

### Solution

The fixer automatically adds the appropriate `@param-immediately-invoked-callable` or `@param-later-invoked-callable` annotation:

```php
/**
 * @param-immediately-invoked-callable(): string $callback
 */
function process($callback) {
    return $callback();
}
```

### Error Patterns Detected:

- `callable.*invoked`
- `Parameter.*expects callable`

### When to Use:

- When passing callable as a parameter
- When callable is invoked immediately or later
- When PHPStan requires callable type specification

---

## 11. MixinFixer

### PHPStan Problem

**Error:** `Call to an undefined method ClassName::methodName()` or `Access to an undefined property ClassName::$property`

PHPStan reports an error when a class uses magic methods (`__call`, `__get`, `__set`) to delegate calls to another class, but PHPStan doesn't know which methods/properties are available.

### Example Code Before Fix:

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
$wrapper->someMethod(); // PHPStan Error
$value = $wrapper->property; // PHPStan Error
```

**PHPStan Error:**
```
Call to an undefined method Wrapper::someMethod()
Access to an undefined property Wrapper::$property
```

### Solution

The fixer automatically analyzes the code, finds the delegated class, and adds a `@mixin` annotation:

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

### Delegated Class Detection Strategy:

1. **AST analysis of magic methods** - analyzes the body of `__call`, `__get`, `__set` and finds the property used for delegation (e.g., `$this->delegate->$name(...)`)
2. **Property search** - searches for properties with common names: `delegate`, `delegator`, `target`, `handler`, `wrapped`, `inner`, `backing`
3. **Type extraction** - extracts the type from:
   - Property declaration (`private OriginalClass $delegate`)
   - Property PHPDoc (`@var OriginalClass`)
   - Class PHPDoc (`@property OriginalClass $delegate`)

### Error Patterns Detected:

- `Call to an undefined method` (when class has `__call`)
- `Access to an undefined property` (when class has `__get`/`__set`)

### When to Use:

- Wrapper/proxy classes delegating calls
- Classes using magic methods for delegation
- Decorator/Adapter pattern with delegation

### Difference from UndefinedMethodFixer:

- **UndefinedMethodFixer**: Adds `@method` for individual magic methods
- **MixinFixer**: Adds `@mixin` for the entire delegated class (all methods and properties at once)

---

## Summary

Each fixer solves a specific PHPStan problem related to missing type documentation or magic behavior in PHP. The library automatically detects the appropriate fixer for each PHPStan error and attempts to fix it.

### How to Use:

```bash
# Suggest mode (preview changes)
vendor/bin/phpstan-fixer --mode=suggest

# Apply mode (write changes)
vendor/bin/phpstan-fixer --mode=apply
```

### Configuration:

You can configure which errors are fixed, ignored, or only reported in the `phpstan-fixer.yaml` file:

```yaml
rules:
  "Access to an undefined property":
    action: "fix"
  "Method has no return type":
    action: "fix"
  "Unknown class":
    action: "ignore"
```

For more information: [README.md](../README.md#configuration-file)

---

## See Also

- [README.md](../README.md) - Main library documentation
- [PHPStan Fixers Guide (PL)](PHPSTAN_FIXERS_GUIDE_PL.md) - Polish version
- [CONFIGURATION_FEATURE.md](../CONFIGURATION_FEATURE.md) - Configuration system documentation
- [TODO.md](../TODO.md) - List of planned fixers
