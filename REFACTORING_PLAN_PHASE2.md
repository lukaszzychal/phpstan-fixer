# Plan Refaktoryzacji - Sprint 5, Faza 2

## Cele Fazy 2

Faza 1 została zakończona - wykonano refaktoryzację głównych klas (`PhpstanAutoFixCommand`, `AutoFixService`).
Faza 2 skupia się na dalszych obszarach wymagających refaktoryzacji i redesignu.

## Analiza obszarów do refaktoryzacji

### 1. Strategy/*.php - Eliminacja duplikacji

#### Problemy:
- Potencjalna duplikacja w wzorcach `canFix()` i `fix()`
- Podobna logika tworzenia docblocków w wielu fixerach
- Powtarzające się wzorce parsowania błędów PHPStan

#### Propozycje:
1. Analiza wspólnych wzorców w Strategy/*.php
2. Wyciągnięcie wspólnej logiki do traitów (jeśli potrzebne)
3. Ujednolicenie wzorców parsowania błędów PHPStan
4. Utworzenie pomocniczych klas dla często używanych operacji

### 2. PhpstanAutoFixCommand::loadCustomFixers()

#### Problemy:
- Skomplikowana logika refleksji
- Duplikacja tworzenia `PhpFileAnalyzer` i `DocblockManipulator`

#### Propozycje:
1. Utworzenie `FixerFactory` lub użycie dependency injection
2. Uproszczenie logiki refleksji
3. Cache'owanie wspólnych zależności

### 3. DocblockManipulator.php

#### Problemy:
- `parseAnnotationValue()` - długie, ale już czytelne
- Możliwe wyciągnięcie parsowania poszczególnych typów adnotacji

#### Propozycje:
1. Wyciągnięcie parsowania poszczególnych typów adnotacji do osobnych metod
2. Możliwość użycia `match` expression (jeśli to uprości kod)

### 4. Configuration/*.php

#### Propozycje:
1. Sprawdzenie spójności między klasami
2. Weryfikacja czy można uprościć parsowanie konfiguracji

### 5. Parser/PhpstanLogParser.php

#### Propozycje:
1. Optymalizacja parsowania JSON
2. Weryfikacja czy można uprościć logikę parsowania

## Plan działania (Faza 2)

1. ✅ Analiza duplikacji w Strategy/*.php
2. ✅ Identyfikacja wspólnych wzorców
3. ✅ Utworzenie traitów pomocniczych:
   - ✅ `TypeFormatterTrait` - formatowanie typów PHP-Parser
   - ✅ `FileValidationTrait` - walidacja pliku i parsowanie AST
4. ⏳ Refaktoryzacja `loadCustomFixers()` - przeniesione do Fazy 3
5. ⏳ Dalsza optymalizacja `DocblockManipulator` - przeniesione do Fazy 3
6. ⏳ Sprawdzenie spójności w Configuration/*.php - przeniesione do Fazy 3
7. ⏳ Optymalizacja `PhpstanLogParser` - przeniesione do Fazy 3
8. ✅ Testy i weryfikacja - wszystkie testy przechodzą

## Metodyka

- TDD: Testy przed refaktoryzacją
- Małe, przyrostowe zmiany
- Każda zmiana z własnym commitem
- Weryfikacja testów po każdej zmianie

