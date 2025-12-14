# Plan Refaktoryzacji - Sprint 5

## Analiza problemów

### 1. PhpstanAutoFixCommand.php (1051 linii)

#### Problemy:
- **createDefaultAutoFixService()** - długa lista strategii (linie 413-437) - można wyciągnąć do osobnej metody/fabryki
- **loadCustomFixers()** - skomplikowana logika refleksji (linie 464-535) - można uprościć
- **execute()** - metoda jest względnie krótka (~113 linii), ale można wyciągnąć część logiki
- **displayResults()** - dobrze podzielona na sekcje, ale można wyciągnąć niektóre części

#### Propozycje refaktoryzacji:
1. Wyciągnąć tworzenie strategii do `StrategyFactory` lub metody `createStrategies()`
2. Uprościć `loadCustomFixers()` - możliwość użycia service container pattern
3. Wyciągnąć logikę apply mode do osobnej metody `applyFixes()`

### 2. AutoFixService.php (364 linie)

#### Problemy:
- `fixAllIssues()` - długie (~73 linie), można wyciągnąć część logiki
- `sortStrategiesByPriority()` - używa Schwartzian transform - dobrze zoptymalizowane

#### Propozycje refaktoryzacji:
1. Wyciągnąć logikę przygotowania plików do osobnej metody
2. Wyciągnąć przetwarzanie pojedynczego pliku do osobnej metody

### 3. DocblockManipulator.php (513 linii)

#### Problemy:
- `parseDocblock()` - długa metoda (~55 linii)
- `parseAnnotationValue()` - długa metoda z wieloma warunkami

#### Propozycje refaktoryzacji:
1. Wyciągnąć parsowanie poszczególnych typów adnotacji do osobnych metod
2. Uprościć logikę `parseAnnotationValue()` używając `match` expression

### 4. Strategy/*.php

#### Problemy:
- Potencjalna duplikacja w wzorcach `canFix()` i `fix()`
- Podobna logika tworzenia docblocków w wielu fixerach

#### Propozycje refaktoryzacji:
1. Sprawdzenie czy można wyciągnąć wspólną logikę do traitów
2. Ujednolicenie wzorców parsowania błędów PHPStan

## Plan działania

1. ✅ Utworzenie brancha
2. ⏳ Refaktoryzacja `PhpstanAutoFixCommand::createDefaultAutoFixService()`
3. ⏳ Uproszczenie `PhpstanAutoFixCommand::loadCustomFixers()`
4. ⏳ Wyciągnięcie logiki apply mode do osobnej metody
5. ⏳ Refaktoryzacja `AutoFixService::fixAllIssues()`
6. ⏳ Refaktoryzacja `DocblockManipulator::parseAnnotationValue()`
7. ⏳ Analiza i eliminacja duplikacji w Strategy/*.php
8. ⏳ Testy i weryfikacja

