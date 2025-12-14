# Pre-commit Hook

> 🇬🇧 **English version**: [Pre-commit Hook (EN)](PRE_COMMIT_HOOK.md)

To repozytorium zawiera Git pre-commit hook, który automatycznie uruchamia sprawdzenia jakości kodu przed pozwoleniem na commit.

## Co Robi

Pre-commit hook uruchamia trzy sprawdzenia po kolei:

1. **phpstan-fixer** (tryb suggest)
   - Sprawdza naprawialne problemy PHPStan
   - Blokuje commit, jeśli znaleziono naprawialne problemy
   - Sugeruje uruchomienie `vendor/bin/phpstan-fixer --mode=apply`, aby je naprawić

2. **PHPStan** (analiza statyczna)
   - Analizuje kod pod kątem błędów typów i innych problemów
   - Blokuje commit, jeśli znaleziono błędy
   - Używa analizy poziomu 5

3. **PHPUnit** (testy)
   - Uruchamia wszystkie testy jednostkowe i integracyjne
   - Blokuje commit, jeśli jakikolwiek test nie przejdzie

## Instalacja

### Pierwsza Instalacja

1. Skopiuj hook do katalogu `.git/hooks/`:

```bash
cp .githooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

2. Hook jest teraz aktywny i będzie uruchamiany przy każdym `git commit`.

### Automatyczna Instalacja (Opcjonalna)

Możesz skonfigurować Git, aby automatycznie używał hooków z `.githooks/`:

```bash
git config core.hooksPath .githooks
```

To sprawia, że Git szuka hooków w `.githooks/` zamiast w `.git/hooks/`.

### Weryfikacja

Przetestuj, czy hook działa:

```bash
.git/hooks/pre-commit
```

Powinieneś zobaczyć:
```
✅ All pre-commit checks passed!
```

## Użycie

Hook uruchamia się automatycznie przy każdym `git commit`. Nie musisz robić niczego specjalnego.

### Przykładowy Output

```
🔍 Running pre-commit checks...
📋 Step 1/3: Running phpstan-fixer (suggest mode)...
✅ phpstan-fixer: No issues found
📋 Step 2/3: Running PHPStan static analysis...
✅ PHPStan: No errors found
📋 Step 3/3: Running PHPUnit tests...
✅ PHPUnit: All tests passed
✅ All pre-commit checks passed!
```

### Jeśli Sprawdzenia Nie Przechodzą

Jeśli jakiekolwiek sprawdzenie nie przejdzie, commit jest blokowany:

```
❌ phpstan-fixer found issues that could be fixed!
Run 'vendor/bin/phpstan-fixer --mode=apply' to apply fixes...
```

Napraw problemy i spróbuj ponownie wykonać commit.

## Omijanie Hooka (Nie Zalecane)

Jeśli absolutnie musisz ominąć hook (np. dla commitów WIP), użyj:

```bash
git commit --no-verify
```

**Ostrzeżenie**: Omijaj hook tylko z uzasadnionych powodów. Sprawdzenia istnieją, aby utrzymać jakość kodu.

## Rozwiązywanie Problemów

### Hook się nie uruchamia?

Upewnij się, że plik jest wykonywalny:
```bash
chmod +x .git/hooks/pre-commit
```

### Brakujące zależności?

Hook automatycznie uruchomi `composer install`, jeśli `vendor/bin` nie istnieje.

### Hook nie przechodzi, ale kod wygląda w porządku?

1. Uruchom każde sprawdzenie ręcznie:
   ```bash
   vendor/bin/phpstan-fixer --mode=suggest
   vendor/bin/phpstan analyse src tests --level=5
   vendor/bin/phpunit
   ```

2. Sprawdź uważnie komunikaty błędów
3. Napraw problemy przed commitem

## Dostosowanie

Aby zmodyfikować hook, edytuj `.git/hooks/pre-commit`. Zmiany będą dotyczyć tylko Twojego lokalnego repozytorium.

## Powiązana Dokumentacja

- [PHPStan Configuration](../phpstan.neon)
- [PHPUnit Configuration](../phpunit.xml)
- [README](../README.md)

