# Plan Refaktoryzacji - Sprint 5, Faza 3

## Cele Fazy 3

Faza 2 została zakończona - utworzono 2 traity pomocnicze (`TypeFormatterTrait`, `FileValidationTrait`).
Faza 3 skupia się na:
1. Rozszerzeniu zastosowania istniejących traitów do pozostałych fixerów
2. Utworzeniu dodatkowych traitów/pomocniczych klas
3. Dalszych optymalizacjach i refaktoryzacjach

## Plan działania (Faza 3)

### 1. Rozszerzenie zastosowania istniejących traitów

1. ⏳ Zastosować `FileValidationTrait` do pozostałych ~18 fixerów:
   - `UndefinedPivotPropertyFixer`
   - `CollectionGenericDocblockFixer`
   - `UndefinedVariableFixer`
   - `UndefinedMethodFixer`
   - `SealedClassFixer`
   - `RequireImplementsFixer`
   - `RequireExtendsFixer`
   - `ReadonlyPropertyFixer`
   - `PrefixedTagsFixer`
   - `MixinFixer`
   - `MissingUseStatementFixer`
   - `MissingThrowsDocblockFixer`
   - `MissingPropertyDocblockFixer`
   - `MagicPropertyFixer`
   - `IterableValueTypeFixer`
   - `InternalAnnotationFixer`
   - `ImpureFunctionFixer`
   - `ImmutableClassFixer`
   - `CallableTypeFixer`
   - `ArrayOffsetTypeFixer`

2. ⏳ Zastosować `TypeFormatterTrait` do fixerów, które formatują typy (jeśli potrzebne)

### 2. Utworzenie nowych traitów/pomocniczych klas

1. ⏳ **FunctionLocatorTrait** - znajdowanie funkcji/metod na podstawie linii
   - Wyciągnąć wspólny kod znajdowania `$targetFunction` i `$targetMethod`
   - Zastosować w fixerach, które tego potrzebują

2. ⏳ **ErrorMessageParser** - helper class dla parsowania komunikatów błędów PHPStan
   - `parseParameterName()` - wyciąganie nazwy parametru
   - `parseParameterIndex()` - wyciąganie indeksu parametru
   - `parseType()` - wyciąganie typu z komunikatu
   - `parseClassName()` - wyciąganie nazwy klasy
   - Zastosować w fixerach z podobnymi wzorcami regex

### 3. Refaktoryzacja innych obszarów

1. ⏳ **PhpstanAutoFixCommand::loadCustomFixers()**
   - Uproszczenie logiki refleksji
   - Cache'owanie wspólnych zależności (`PhpFileAnalyzer`, `DocblockManipulator`)
   - Możliwość użycia dependency injection

2. ⏳ **DocblockManipulator.php**
   - Wyciągnięcie parsowania poszczególnych typów adnotacji do osobnych metod
   - Możliwość użycia `match` expression (jeśli to uprości kod)

3. ⏳ **Configuration/*.php**
   - Sprawdzenie spójności między klasami
   - Weryfikacja czy można uprościć parsowanie konfiguracji

4. ⏳ **PhpstanLogParser.php**
   - Optymalizacja parsowania JSON
   - Weryfikacja czy można uprościć logikę parsowania

## Metodyka

- TDD: Testy przed refaktoryzacją
- Małe, przyrostowe zmiany
- Każda zmiana z własnym commitem
- Weryfikacja testów po każdej zmianie
- Priorytet: najpierw rozszerzenie istniejących traitów, potem nowe

## Szacowany czas

- Rozszerzenie `FileValidationTrait`: ~2-3 godziny
- Utworzenie `FunctionLocatorTrait`: ~1-2 godziny
- Utworzenie `ErrorMessageParser`: ~2-3 godziny
- Pozostałe refaktoryzacje: ~3-4 godziny
- **Razem**: ~8-12 godzin pracy

