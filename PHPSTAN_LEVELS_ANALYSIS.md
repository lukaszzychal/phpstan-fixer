# PHPStan Levels Analysis (0-8)

Analiza błędów wykrywanych na każdym poziomie PHPStan i status implementacji fixerów.

## Level 0 - Basic checks

### Błędy wykrywane:
- ✅ Extra arguments passed to functions
- ✅ Extra arguments passed to print/sprintf functions
- ✅ Unknown classes
- ✅ Unknown functions
- ✅ Unknown methods called on classes
- ✅ Obvious errors in dead code

### Status implementacji:
- ❌ **ExtraArgumentsFixer** - NIE (trudne - wymaga usunięcia argumentów, ryzykowne)
- ✅ **MissingUseStatementFixer** - TAK (obsługuje "Unknown classes")
- ❌ **UnknownFunctionFixer** - NIE (wymaga use statement lub implementacji)
- ✅ **UndefinedMethodFixer** - TAK (obsługuje "Unknown methods")
- ❌ **DeadCodeFixer** - NIE (ryzykowne - usuwanie kodu)

## Level 1 - Possibly undefined variables

### Błędy wykrywane:
- ✅ Undefined variables (possibly)
- ❌ Unknown magic properties on classes with `__get`
- ❌ Unknown magic methods on classes with `__call`

### Status implementacji:
- ✅ **UndefinedVariableFixer** - TAK
- ❌ **MagicPropertyFixer** - NIE (wymaga `@property` - częściowo pokryte przez MissingPropertyDocblockFixer)
- ✅ **UndefinedMethodFixer** - TAK (obsługuje magic methods)

## Level 2 - Possibly undefined methods

### Błędy wykrywane:
- ✅ Possibly undefined methods
- ✅ Possibly undefined properties

### Status implementacji:
- ✅ **UndefinedMethodFixer** - TAK
- ✅ **MissingPropertyDocblockFixer** - TAK

## Level 3 - Unknown return types

### Błędy wykrywane:
- ✅ Unknown return type of function calls
- ✅ Unknown return type of method calls
- ✅ Unknown parameter types

### Status implementacji:
- ✅ **MissingReturnDocblockFixer** - TAK
- ✅ **MissingParamDocblockFixer** - TAK

## Level 4 - Unknown array access

### Błędy wykrywane:
- ✅ Unknown array access
- ✅ Unknown array offset
- ✅ Possible array access on non-array

### Status implementacji:
- ❌ **ArrayAccessFixer** - NIE (może dodawać `@var array` ale często niepoprawne)
- ❌ **ArrayOffsetFixer** - NIE (wymaga analizy kontekstu)

## Level 5 - Unknown array offset types

### Błędy wykrywane:
- ✅ Generic type Collection needs parameters
- ✅ Unknown array offset types
- ✅ Missing iterable value type

### Status implementacji:
- ✅ **CollectionGenericDocblockFixer** - TAK
- ❌ **ArrayOffsetTypeFixer** - NIE (można dodać generics do array, ale wymaga analizy)
- ❌ **IterableValueTypeFixer** - NIE (można dodać `array<int, mixed>`)

## Level 6 - Mixed types

### Błędy wykrywane:
- ✅ Mixed types in various contexts
- ✅ Possibly null types
- ✅ Possibly invalid arguments

### Status implementacji:
- ❌ **MixedTypeFixer** - NIE (wymaga konkretnych typów, nie można automatycznie)
- ❌ **NullTypeFixer** - NIE (wymaga analizy logiki)
- ❌ **InvalidArgumentFixer** - NIE (wymaga zmiany kodu, nie tylko adnotacji)

## Level 7 - Null types and type coercion

### Błędy wykrywane:
- ✅ Possibly null types
- ✅ Type coercion issues
- ✅ Incorrect type comparisons

### Status implementacji:
- ❌ **NullSafetyFixer** - NIE (wymaga zmiany logiki kodu)
- ❌ **TypeCoercionFixer** - NIE (wymaga refaktoryzacji)

## Level 8 - Advanced type checking

### Błędy wykrywane:
- ✅ Complex type mismatches
- ✅ Advanced generics issues
- ✅ Template type issues
- ✅ Callable type issues

### Status implementacji:
- ✅ **CallableTypeFixer** - TAK (częściowo)
- ❌ **GenericTypeFixer** - NIE (zaawansowane generics wymagają analizy)
- ❌ **TemplateTypeFixer** - NIE (zaawansowane, wymaga kontekstu)

## Dodatkowe błędy (niezależne od poziomu)

### PHPDoc-related:
- ✅ Missing @return
- ✅ Missing @param
- ✅ Missing @throws
- ✅ Missing @property
- ✅ Missing @method
- ✅ Missing use statements
- ❌ Missing @mixin
- ❌ Missing @internal
- ❌ Missing @phpstan-impure/@phpstan-pure
- ❌ Missing @phpstan-require-extends
- ❌ Missing @phpstan-require-implements
- ❌ Missing @readonly/@immutable
- ❌ Missing @phpstan-sealed
- ❌ Missing prefixed tags (@phpstan-param, @phpstan-return)

### Framework-specific:
- ✅ Laravel Eloquent pivot property
- ❌ Laravel Collection generics (częściowo - ogólne Collection)
- ❌ Symfony Doctrine entity properties
- ❌ Other framework magic properties/methods

## Podsumowanie według poziomów

| Level | Wykrywane błędy | Zaimplementowane | W TODO | Trudne/Niemożliwe |
|-------|----------------|------------------|--------|-------------------|
| 0 | 5 | 2 | 0 | 3 |
| 1 | 3 | 2 | 1 | 0 |
| 2 | 2 | 2 | 0 | 0 |
| 3 | 3 | 2 | 0 | 0 |
| 4 | 3 | 0 | 1 | 2 |
| 5 | 3 | 1 | 2 | 0 |
| 6 | 3 | 0 | 0 | 3 |
| 7 | 3 | 0 | 0 | 3 |
| 8 | 4 | 1 | 0 | 3 |

## Rekomendacje do TODO

### Dodaj do TODO (możliwe do automatycznej naprawy):
1. **ArrayOffsetTypeFixer** - dodawać generics do array (np. `array<int, string>`)
2. **IterableValueTypeFixer** - dodawać typ wartości dla iterable
3. **MagicPropertyFixer** - ulepszenie MissingPropertyDocblockFixer dla magic properties
4. **Framework-specific fixers** - rozszerzenia dla Laravel/Symfony

### Oznacz jako trudne/niemożliwe (wymagają zmiany logiki):
1. **ExtraArgumentsFixer** - wymaga usuwania argumentów (ryzykowne)
2. **DeadCodeFixer** - usuwanie kodu (niebezpieczne)
3. **MixedTypeFixer** - wymaga konkretnych typów
4. **NullSafetyFixer** - wymaga dodania null checks
5. **TypeCoercionFixer** - wymaga refaktoryzacji kodu

