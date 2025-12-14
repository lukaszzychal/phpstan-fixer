# Plan Refaktoryzacji - Sprint 5, Faza 5 (Opcjonalne zadania)

## Cele Fazy 5

Faza 4 została zakończona - utworzono `FunctionLocatorTrait`, `ErrorMessageParser`, i `FixerFactory`.
Faza 5 skupia się na opcjonalnych zadaniach optymalizacyjnych i weryfikacyjnych.

## Plan działania (Faza 5)

### 1. Optymalizacja DocblockManipulator

**Propozycje**:
1. Wyciągnięcie parsowania poszczególnych typów adnotacji do osobnych metod
2. Możliwość użycia `match` expression (jeśli to uprości kod)
3. Sprawdzenie czy można zoptymalizować parsowanie regex patterns

**Analiza**: Sprawdzić `DocblockManipulator` pod kątem:
- Czy parsowanie można uprościć?
- Czy są duplikacje w parsowaniu różnych adnotacji?
- Czy można użyć `match` expression zamiast wielu `if-else`?

### 2. Sprawdzenie spójności w Configuration/*.php

**Propozycje**:
1. Sprawdzenie spójności między klasami `Configuration`, `ConfigurationLoader`, `PathFilter`
2. Weryfikacja czy można uprościć parsowanie konfiguracji
3. Sprawdzenie czy są duplikacje w walidacji

**Analiza**: Sprawdzić:
- Czy logika parsowania jest spójna?
- Czy można wyekstrahować wspólne metody?
- Czy walidacja jest w odpowiednich miejscach?

### 3. Optymalizacja PhpstanLogParser

**Propozycje**:
1. Optymalizacja parsowania JSON
2. Weryfikacja czy można uprościć logikę parsowania
3. Sprawdzenie czy są niepotrzebne operacje

**Analiza**: Sprawdzić:
- Czy parsowanie JSON można zoptymalizować?
- Czy są niepotrzebne przekształcenia danych?
- Czy można użyć bardziej efektywnych struktur danych?

## Metodyka

- TDD: Testy przed refaktoryzacją (jeśli potrzebne)
- Małe, przyrostowe zmiany
- Każda zmiana z własnym commitem
- Weryfikacja testów po każdej zmianie
- Priorytet: optymalizacje, które rzeczywiście poprawiają czytelność lub wydajność

## Uwaga

Te zadania są opcjonalne i powinny być wykonane tylko jeśli:
1. Znajdziemy rzeczywiste problemy do rozwiązania
2. Optymalizacje rzeczywiście poprawią kod
3. Nie wprowadzą niepotrzebnej złożoności

