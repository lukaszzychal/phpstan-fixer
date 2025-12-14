# PHPStan False Positives - Rozwiązania

> 🇬🇧 **English version**: [PHPStan False Positives - Solutions (EN)](PHPSTAN_FALSE_POSITIVES.md)

Ten dokument opisuje strategie obsługi fałszywych alarmów PHPStan w projekcie.

## Obecne Podejście

### 1. Konfiguracja PHPStan (`phpstan.neon`)

Używamy `ignoreErrors` w `phpstan.neon` dla projektowych false positives:

```neon
parameters:
    ignoreErrors:
        - '#Call to method.*assertIsArray\(\) with array.*will always evaluate to true#'
        - '#Call to method.*assertIsBool\(\) with bool will always evaluate to true#'
        - '#Unreachable statement - code above always terminates#'
    reportUnmatchedIgnoredErrors: false
```

### 2. Adnotacje Inline

Dla false positives specyficznych dla pliku używamy `@phpstan-ignore-next-line`:

```php
// @phpstan-ignore-next-line - false positive: $typeNode is a union type, instanceof check is valid
if ($typeNode instanceof \PhpParser\Node\UnionType) {
    return implode('|', array_map([$this, 'formatType'], $typeNode->types));
}
```

## Rekomendowane Rozwiązania

### Rozwiązanie 1: PHPStan Baseline (Rekomendowane dla znanych False Positives)

**Co to jest:** Plik baseline, który zapisuje wszystkie obecne błędy PHPStan. Nowe błędy (nie w baseline) będą nadal raportowane, ale istniejące błędy są ignorowane.

**Zalety:**
- ✅ Czysty sposób obsługi istniejących false positives
- ✅ Tylko nowe błędy są raportowane
- ✅ Automatycznie utrzymywany
- ✅ Działa dobrze w CI/CD

**Jak używać:**

1. Wygeneruj baseline (jednorazowo):
```bash
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

To tworzy `phpstan-baseline.neon` ze wszystkimi obecnymi błędami.

2. Zaktualizuj `phpstan.neon`, aby uwzględnić baseline:
```neon
parameters:
    level: 5
    baseline: phpstan-baseline.neon
```

3. Przy naprawianiu błędów, regeneruj baseline:
```bash
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

**Kiedy używać:**
- Gdy masz wiele istniejących false positives
- Gdy chcesz skupić się tylko na nowych błędach
- Gdy false positives są trudne do stłumienia regexem

### Rozwiązanie 2: Ulepszona konfiguracja ignoreErrors

**Co to jest:** Lepsze wzorce regex w `phpstan.neon` do łapania typowych wzorców false positives.

**Zalety:**
- ✅ Brak dodatkowych plików
- ✅ Łatwe w utrzymaniu
- ✅ Dobre dla typowych wzorców

**Obecne wzorce, których używamy:**
```neon
ignoreErrors:
    # PHPUnit assertion false positives
    - '#Call to method.*assertIs(Array|Bool|String)\(\) with (array|bool|string).*will always evaluate to true#'
    
    # Test skipping false positives
    - '#Unreachable statement - code above always terminates#'
```

**Ulepszony wzorzec (bardziej specyficzny):**
```neon
ignoreErrors:
    # PHPUnit assertion false positives (bardziej specyficzny)
    - '#Call to method PHPUnit\\\\Framework\\\\Assert::assertIs(Array|Bool|String|Int|Float)\(\) with (array|bool|string|int|float) will always evaluate to true#'
    
    # Test skipping z logiką warunkową
    - '#Unreachable statement - code above always terminates#'
        paths:
            - tests
    
    # Reflection false positives (jeśli dotyczy)
    - '#Call to method Reflection.*::.*\(\) may not exist#'
```

**Kiedy używać:**
- Dla typowych wzorców w całym projekcie
- Gdy wzorzec jest wystarczająco specyficzny, aby nie ukrywać prawdziwych błędów
- Dla false positives specyficznych dla testów

### Rozwiązanie 3: Podejście Kombinowane (Obecne + Baseline)

**Najlepsza praktyka:** Używaj zarówno baseline, jak i ignoreErrors:

1. **ignoreErrors** - Dla projektowych znanych wzorców (PHPUnit, typowe false positives)
2. **Baseline** - Dla jednorazowych błędów, które są false positives, ale nie pasują do wzorca
3. **@phpstan-ignore-next-line** - Dla specyficznych przypadków inline, które wymagają wyjaśnienia

**Przykład konfiguracji:**
```neon
parameters:
    level: 5
    baseline: phpstan-baseline.neon
    ignoreErrors:
        # Typowe wzorce (projektowe)
        - '#Call to method.*assertIs(Array|Bool|String)\(\) with (array|bool|string).*will always evaluate to true#'
        - '#Unreachable statement - code above always terminates#'
            paths:
                - tests
    reportUnmatchedIgnoredErrors: false
```

## Rekomendacje Implementacji

### Natychmiastowe Działania

1. **Utwórz plik baseline** (jeśli nie istnieje):
```bash
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

2. **Zaktualizuj phpstan.neon**, aby używać baseline:
```neon
parameters:
    baseline: phpstan-baseline.neon
```

3. **Ulepsz wzorce ignoreErrors** dla typowych false positives (zobacz przykłady powyżej)

### Strategia Długoterminowa

1. **Regularne aktualizacje baseline:**
   - Regeneruj baseline przy naprawianiu prawdziwych błędów
   - Przeglądaj wpisy baseline okresowo, aby sprawdzić, czy można je naprawić
   - Dokumentuj, dlaczego każdy wpis baseline istnieje

2. **Zbieranie błędów:**
   - Kontynuuj zbieranie błędów w `log-errors-phpstan/`
   - Analizuj wzorce, aby zidentyfikować nowe możliwości fixerów
   - Przenieś z baseline do fixerów, gdy to możliwe

3. **Dokumentacja:**
   - Dokumentuj typowe wzorce false positives
   - Aktualizuj `phpstan-errors-analysis.md`
   - Dodaj przykłady właściwego użycia ignore

## Przykłady

### Przykład 1: Test Skip False Positive

**Problem:**
```php
public function testSomething(): void
{
    if (!extension_loaded('yaml')) {
        $this->markTestSkipped('YAML extension required');
        return; // PHPStan: Unreachable statement
    }
    
    // Kod testu
}
```

**Rozwiązania:**

Opcja A - Inline ignore:
```php
if (!extension_loaded('yaml')) {
    $this->markTestSkipped('YAML extension required');
    /** @phpstan-ignore-next-line */
    return;
}
```

Opcja B - Baseline (rekomendowane dla wielu wystąpień):
```bash
# Wygeneruj baseline, aby uwzględnić wszystkie takie przypadki
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

Opcja C - Wzorzec ignoreErrors:
```neon
ignoreErrors:
    - '#Unreachable statement - code above always terminates#'
        paths:
            - tests
```

### Przykład 2: Union Type Instanceof Check

**Problem:**
```php
// PHPStan: Instanceof between UnionType and UnionType will always evaluate to true
if ($typeNode instanceof \PhpParser\Node\UnionType) {
    // ...
}
```

**Rozwiązanie - Inline ignore z wyjaśnieniem:**
```php
// @phpstan-ignore-next-line - false positive: $typeNode is a union type, instanceof check is valid
if ($typeNode instanceof \PhpParser\Node\UnionType) {
    // ...
}
```

### Przykład 3: Reflection False Positive

**Problem:**
```php
$reflection = new \ReflectionClass($className);
$method = $reflection->getMethod('someMethod'); // PHPStan: Method may not exist
```

**Rozwiązanie - Inline ignore:**
```php
$reflection = new \ReflectionClass($className);
/** @phpstan-ignore-next-line - Method existence checked elsewhere */
$method = $reflection->getMethod('someMethod');
```

## Drzewo Decyzyjne

```
Czy false positive jest:
├─ Typowym wzorcem w wielu plikach?
│  └─ Użyj ignoreErrors w phpstan.neon
│
├─ Jednorazowym błędem w konkretnym pliku?
│  └─ Użyj @phpstan-ignore-next-line z wyjaśnieniem
│
├─ Wieloma błędami w wielu plikach?
│  └─ Użyj pliku baseline
│
└─ Można go naprawić w kodzie?
   └─ Napraw go! (Nie tłum)
```

## Powiązana Dokumentacja

- [PHPStan Baseline Documentation](https://phpstan.org/user-guide/baseline)
- [PHPStan Ignoring Errors](https://phpstan.org/user-guide/ignoring-errors)
- [log-errors-phpstan/phpstan-errors-analysis.md](../log-errors-phpstan/phpstan-errors-analysis.md) - Obecna analiza błędów

