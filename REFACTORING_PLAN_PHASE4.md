# Plan Refaktoryzacji - Sprint 5, Faza 4

## Cele Fazy 4

Faza 3 została zakończona - zastosowano `FileValidationTrait` do wszystkich fixerów (19/19).
Faza 4 skupia się na dalszych obszarach wymagających refaktoryzacji i optymalizacji.

## Plan działania (Faza 4)

### 1. Utworzenie FunctionLocatorTrait

**Problem**: Duplikacja kodu znajdowania funkcji/metod na podstawie linii w wielu fixerach.

**Rozwiązanie**: Utworzyć `FunctionLocatorTrait` z metodą `findFunctionOrMethodAtLine()`.

**Fixery wymagające tego traitu**:
- `MissingReturnDocblockFixer`
- `MissingParamDocblockFixer`
- `ArrayOffsetTypeFixer`
- `IterableValueTypeFixer`
- I inne...

### 2. Utworzenie ErrorMessageParser

**Problem**: Duplikacja parsowania komunikatów błędów PHPStan (regex patterns).

**Rozwiązanie**: Utworzyć `ErrorMessageParser` helper class z metodami:
- `parseParameterName()` - wyciąganie nazwy parametru
- `parseParameterIndex()` - wyciąganie indeksu parametru
- `parseType()` - wyciąganie typu z komunikatu
- `parseClassName()` - wyciąganie nazwy klasy

**Fixery wymagające tego helpera**:
- `MissingParamDocblockFixer`
- `PrefixedTagsFixer`
- `CallableTypeFixer`
- I inne...

### 3. Refaktoryzacja loadCustomFixers()

**Problem**: Skomplikowana logika refleksji w `PhpstanAutoFixCommand::loadCustomFixers()`.

**Propozycje**:
1. Utworzenie `FixerFactory` lub użycie dependency injection
2. Uproszczenie logiki refleksji
3. Cache'owanie wspólnych zależności (`PhpFileAnalyzer`, `DocblockManipulator`)

### 4. Optymalizacja DocblockManipulator

**Propozycje**:
1. Wyciągnięcie parsowania poszczególnych typów adnotacji do osobnych metod
2. Możliwość użycia `match` expression (jeśli to uprości kod)

### 5. Sprawdzenie spójności w Configuration/*.php

**Propozycje**:
1. Sprawdzenie spójności między klasami
2. Weryfikacja czy można uprościć parsowanie konfiguracji

### 6. Optymalizacja PhpstanLogParser

**Propozycje**:
1. Optymalizacja parsowania JSON
2. Weryfikacja czy można uprościć logikę parsowania

## Metodyka

- TDD: Testy przed refaktoryzacją
- Małe, przyrostowe zmiany
- Każda zmiana z własnym commitem
- Weryfikacja testów po każdej zmianie
- Priorytet: najpierw FunctionLocatorTrait i ErrorMessageParser, potem pozostałe

## Szacowany czas

- Utworzenie `FunctionLocatorTrait`: ~1-2 godziny
- Utworzenie `ErrorMessageParser`: ~2-3 godziny
- Pozostałe refaktoryzacje: ~4-6 godzin
- **Razem**: ~7-11 godzin pracy

