# Zaimplementowane Fixery vs Dokumentacja PHPStan

## ✅ Zaimplementowane Fixery (21)

### 1. ✅ MissingReturnDocblockFixer
- **Błąd:** "Function has no return type specified" / "Method has no return type"
- **Naprawa:** Dodaje `@return mixed` lub inferuje z native return type hint
- **Status:** Zaimplementowane

### 2. ✅ MissingParamDocblockFixer
- **Błąd:** "Parameter #X $name has no type specified"
- **Naprawa:** Dodaje `@param mixed $name` do PHPDoc
- **Status:** Zaimplementowane

### 3. ✅ MissingPropertyDocblockFixer
- **Błąd:** "Access to an undefined property"
- **Naprawa:** Dodaje `@property` lub `@var` annotations
- **Status:** Zaimplementowane

### 4. ✅ UndefinedPivotPropertyFixer
- **Błąd:** "Access to an undefined property ...->pivot" (Laravel Eloquent)
- **Naprawa:** Dodaje `@property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot`
- **Status:** Zaimplementowane

### 5. ✅ CollectionGenericDocblockFixer
- **Błąd:** "Generic type Collection needs parameters"
- **Naprawa:** Dodaje generics `Collection<int, mixed>`
- **Status:** Zaimplementowane

### 6. ✅ UndefinedVariableFixer
- **Błąd:** "Undefined variable: $variableName"
- **Naprawa:** Dodaje inline `@var mixed $variableName`
- **Status:** Zaimplementowane

### 7. ✅ MissingUseStatementFixer
- **Błąd:** "Class X not found" / "Cannot resolve symbol"
- **Naprawa:** Dodaje `use Fully\Qualified\ClassName;`
- **Status:** Zaimplementowane (podstawowa wersja - może wymagać ręcznej korekty FQN)

### 8. ✅ UndefinedMethodFixer
- **Błąd:** "Call to an undefined method"
- **Naprawa:** Dodaje `@method ReturnType methodName()` dla magic methods
- **Status:** Zaimplementowane

### 9. ✅ MissingThrowsDocblockFixer
- **Błąd:** "Function throws exception but @throws annotation is missing"
- **Naprawa:** Dodaje `@throws ExceptionType`
- **Status:** Zaimplementowane

### 10. ✅ CallableTypeFixer
- **Błąd:** Callable invocation timing issues
- **Naprawa:** Dodaje `@param-immediately-invoked-callable` / `@param-later-invoked-callable`
- **Status:** Zaimplementowane

---

## ❌ Niezaimplementowane (ale możliwe do automatycznej naprawy)

### Z dokumentacji PHPDocs Basics:

#### 1. ❌ MixinFixer
- **Błąd:** Delegacja metod przez `__call` / `__get` / `__set`
- **Naprawa:** Dodaje `@mixin ClassName` na klasie
- **Status:** NIE zaimplementowane

#### 2. ✅ InternalAnnotationFixer
- **Błąd:** "Access to internal element"
- **Naprawa:** Dodaje `@internal` tag
- **Status:** Zaimplementowane (rzadko potrzebne automatycznie)

#### 3. ✅ ImpureFunctionFixer
- **Błąd:** Functions that may return different values
- **Naprawa:** Dodaje `@phpstan-impure` lub `@phpstan-pure`
- **Status:** Zaimplementowane

#### 4. ✅ RequireExtendsFixer
- **Błąd:** Interface/trait wymaga konkretnej klasy bazowej
- **Naprawa:** Dodaje `@phpstan-require-extends ClassName` na interfejsie/trait
- **Status:** Zaimplementowane

#### 5. ✅ RequireImplementsFixer
- **Błąd:** Trait wymaga implementacji interfejsu
- **Naprawa:** Dodaje `@phpstan-require-implements InterfaceName` na trait
- **Status:** Zaimplementowane

#### 6. ❌ ReadonlyPropertyFixer
- **Błąd:** Property assigned outside of declaring class (PHP < 8.1)
- **Naprawa:** Dodaje `@readonly` tag na property
- **Status:** NIE zaimplementowane

#### 7. ✅ ImmutableClassFixer
- **Błąd:** Property assigned outside of immutable class
- **Naprawa:** Dodaje `@immutable` lub `@readonly` na klasie
- **Status:** Zaimplementowane

#### 8. ✅ SealedClassFixer
- **Błąd:** Class extends sealed class (PHPStan 2.1.18+)
- **Naprawa:** Dodaje `@phpstan-sealed Class1|Class2`
- **Status:** Zaimplementowane

#### 9. ✅ PrefixedTagsFixer
- **Błąd:** Advanced types not understood by IDEs
- **Naprawa:** Dodaje `@phpstan-param`, `@phpstan-return` obok standardowych tagów
- **Status:** Zaimplementowane

#### 11. ✅ ClassesNamedAfterInternalTypesFixer
- **Błąd:** Konflikt nazw klas z wewnętrznymi typami PHP (Resource, Double, Number)
- **Naprawa:** Zmienia PHPDoc na fully-qualified name
- **Status:** Zaimplementowane

#### 12. ✅ ArrayOffsetTypeFixer
- **Błąd:** \"Unknown array offset types\" / \"Missing iterable value type\"
- **Naprawa:** Dodaje generyki do tablic (np. `array<int, string>`)
- **Status:** Zaimplementowane

#### 12. ✅ MagicPropertyFixer
- **Błąd:** Unknown magic properties on classes with __get
- **Naprawa:** Dodaje brakujące @property dla magicznych właściwości
- **Status:** Zaimplementowane (enhancement)

---

## 🟡 Częściowo zaimplementowane / Wymagają poprawy

### 1. 🟡 MissingUseStatementFixer
- **Status:** Podstawowa implementacja działa, ale:
  - Nie rozwiązuje automatycznie fully-qualified class names
  - Wymaga ręcznej korekty w niektórych przypadkach
  - Nie przeszukuje vendor/ dla znalezienia klas

---

## 🔵 Trudne do automatycznej naprawy (wymagają kontekstu)

### 1. 🔵 ExtraArgumentsFixer
- **Błąd:** "Extra arguments passed to function"
- **Problem:** Wymaga zrozumienia logiki biznesowej - nie można automatycznie usunąć argumentów

### 2. 🔵 WrongArgumentTypeFixer
- **Błąd:** "Parameter expects X, Y given"
- **Problem:** Wymaga zrozumienia typu i konwersji - ryzykowne do automatycznej naprawy

### 3. 🔵 TypeMismatchFixer
- **Błąd:** Różne type mismatches
- **Problem:** Wymaga analizy kontekstu - często wymaga zmiany logiki, nie tylko adnotacji

### 4. 🔵 DeadCodeFixer
- **Błąd:** "Obvious errors in dead code"
- **Problem:** Usuwanie dead code może być niebezpieczne bez pełnej analizy

---

## 📊 Podsumowanie

### Statystyki:
- ✅ **Zaimplementowane:** 21 fixerów
- ❌ **Możliwe do dodania:** ~8 dodatkowych fixerów z dokumentacji
- 🟡 **Wymagają poprawy:** 1 fixer (MissingUseStatementFixer – rozszerzenie FQN)
- 🔵 **Trudne/Ryzykowne:** 4+ typy błędów

### Główne pokrycie:
- ✅ **PHPDoc basics:** ~70% (zaimplementowane główne tagi)
- ✅ **Common errors:** ~80% najczęstszych błędów
- ❌ **Advanced PHPDoc tags:** ~30% (brakuje mixin, internal, impure, readonly, etc.)
- ❌ **PHPStan-specific features:** ~40% (brakuje niektórych zaawansowanych tagów)

### Zalecenia:
1. **Krótkoterminowe:** Dodać `MixinFixer` i `ReadonlyPropertyFixer` (często używane)
2. **Średnioterminowe:** Dodać `PrefixedTagsFixer` dla zaawansowanych typów
3. **Długoterminowe:** Ulepszyć `MissingUseStatementFixer` o discovery symboli

---

## 🎯 Priorytety dodania nowych fixerów

### Wysoki priorytet:
1. **MixinFixer** - często używane w Laravel/Symfony
2. **ReadonlyPropertyFixer** - wsparcie dla PHP < 8.1
3. **PrefixedTagsFixer** - zaawansowane typy PHPStan

### Średni priorytet:
4. **ImpureFunctionFixer** - przydatne dla funkcji impure
5. **RequireExtendsFixer** - zaawansowane użycie interfejsów/traitów
6. **RequireImplementsFixer** - zaawansowane użycie traitów

### Niski priorytet:
7. **InternalAnnotationFixer** - rzadko potrzebne
8. **SealedClassFixer** - nowa funkcja, mało używana
9. **ClassesNamedAfterInternalTypesFixer** - rzadki problem

