# 🛠️ TASK-016 — Auto-fix błędów PHPStan

## ✅ Cel
Przygotowanie lokalnego narzędzia, które potrafi odczytać logi PHPStan (format JSON) i zaproponować lub automatycznie wprowadzić poprawki w kodzie bez wsparcia AI ani dostępu do Internetu.

## 🧱 Architektura rozwiązania
- `app/Console/Commands/PhpstanAutoFixCommand.php` – polecenie Artisan `phpstan:auto-fix`.
- `app/Support/PhpstanFixer/*` – moduł narzędzia:
  - `PhpstanLogParser` – zamienia log JSON na kolekcję problemów.
  - `AutoFixService` – uruchamia kolejne strategie napraw.
  - `Fixers/*` – katalog strategii (każda implementuje interfejs `FixStrategy`).
    - `UndefinedPivotPropertyFixer` – dodaje adnotację `@property-read ... $pivot` w modelach.
    - `MissingParamDocblockFixer` – uzupełnia docblock o `@param mixed $…` gdy brak typu parametru.
    - `MissingReturnDocblockFixer` – dodaje `@return mixed` gdy PHPStan raportuje brak typu zwrotu.
    - `MissingPropertyDocblockFixer` – wstawia `@property` dla dynamicznych właściwości modeli.
    - `CollectionGenericDocblockFixer` – uzupełnia generic w adnotacjach `Collection<int, Model>`.
- Rejestracja w kontenerze DI: `AppServiceProvider`.
- Rejestracja komendy w `app/Console/Kernel.php`.

## 🚀 Użycie
```bash
php artisan phpstan:auto-fix --mode=suggest
php artisan phpstan:auto-fix --mode=apply
```

Opcjonalnie można wskazać istniejący log:
```bash
php artisan phpstan:auto-fix --input=storage/logs/phpstan.json
```

- `--mode=suggest` (domyślny) – wyświetla tabelę z proponowanymi zmianami bez modyfikacji plików.
- `--mode=apply` – zapisuje poprawki na dysku.

Komenda domyślnie uruchamia `vendor/bin/phpstan analyse --error-format=json`. Jeśli używamy `--input`, log musi być w formacie JSON kompatybilnym z PHPStan.

## 🧪 Testy
- `Tests\Unit\Support\PhpstanFixer\PhpstanLogParserTest` – poprawność parsowania logów.
- `Tests\Unit\Support\PhpstanFixer\Fixers\*` – pokrycie strategii naprawy.
- `Tests\Feature\Console\PhpstanAutoFixCommandTest` – scenariusze `suggest` i `apply` na rozszerzonym zbiorze fixture JSON.

## 🔮 Rozszerzenia
- Zaimplementowane strategie napraw:
  - [x] `MissingReturnDocblockFixer`
  - [x] `MissingPropertyDocblockFixer`
  - [x] `CollectionGenericDocblockFixer`
- Kolejne kroki:
  - Przygotowanie konfiguracji exportu jako osobny pakiet Composer.
  - Integracja z pipeline CI (tryb `suggest` jako raport).

## 📚 Powiązane pliki
- `docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md` – wersja angielska.
- `docs/issue/pl/TASKS.md` / `docs/issue/en/TASKS.md` – backlog zaktualizowany o zadanie.


