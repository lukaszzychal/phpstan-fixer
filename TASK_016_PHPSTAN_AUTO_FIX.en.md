# 🛠️ TASK-016 — PHPStan auto-fix tool

## ✅ Goal
<<<<<<< HEAD
Provide an offline-friendly utility that reads PHPStan JSON logs and either suggests or applies code fixes without relying on AI or network access.
=======
Ship an offline-friendly utility that reads PHPStan JSON logs and either suggests or applies code fixes without relying on AI or network access.
>>>>>>> main

## 🧱 Architecture
- `app/Console/Commands/PhpstanAutoFixCommand.php` – Artisan command `phpstan:auto-fix`.
- `app/Support/PhpstanFixer/*` – core module:
  - `PhpstanLogParser` – converts JSON logs to issue objects.
<<<<<<< HEAD
  - `AutoFixService` – dispatches issues to individual fix strategies.
  - `Fixers/*` – strategy implementations (`FixStrategy` interface):
    - `UndefinedPivotPropertyFixer` – adds `@property-read ... $pivot` for Eloquent models.
    - `MissingParamDocblockFixer` – adds `@param mixed ...` docblocks when PHPStan reports missing parameter types.
    - `MissingReturnDocblockFixer` – injects `@return mixed` when return type information is missing.
    - `MissingPropertyDocblockFixer` – inserts `@property` annotations for dynamic properties.
    - `CollectionGenericDocblockFixer` – adds collection generics such as `Collection<int, Model>`.
=======
  - `AutoFixService` – delegates issues to individual fix strategies.
  - `Fixers/*` – strategy implementations (`FixStrategy` interface):
    - `UndefinedPivotPropertyFixer` – adds `@property-read ... $pivot` for Eloquent models.
    - `MissingParamDocblockFixer` – adds `@param mixed ...` docblocks when PHPStan reports missing parameter types.
>>>>>>> main
- DI wiring in `AppServiceProvider`.
- Command registration in `app/Console/Kernel.php`.

## 🚀 Usage
```bash
php artisan phpstan:auto-fix --mode=suggest
php artisan phpstan:auto-fix --mode=apply
```

Optional external log:
```bash
php artisan phpstan:auto-fix --input=storage/logs/phpstan.json
```

- `--mode=suggest` (default) prints the proposed changes, no files touched.
- `--mode=apply` writes the changes to disk.

<<<<<<< HEAD
When `--input` is omitted, the command runs `vendor/bin/phpstan analyse --error-format=json`. The supplied log must follow PHPStan’s JSON schema.

## 🧪 Tests
- `Tests\Unit\Support\PhpstanFixer\PhpstanLogParserTest` – parsing coverage.
- `Tests\Unit\Support\PhpstanFixer\Fixers\*` – individual strategy behaviour.
- `Tests\Feature\Console\PhpstanAutoFixCommandTest` – end-to-end suggest/apply flow on an extended JSON fixture set.

## 🔮 Next steps
- Implemented fixer strategies:
  - [x] `MissingReturnDocblockFixer`
  - [x] `MissingPropertyDocblockFixer`
  - [x] `CollectionGenericDocblockFixer`
- Roadmap:
  - Extract the module into a standalone Composer package.
  - Wire the command into CI (suggest mode for reporting).
=======
When `--input` is not provided, the command runs `vendor/bin/phpstan analyse --error-format=json`. The supplied log must follow PHPStan’s JSON schema.

## 🧪 Tests
- `Tests\Unit\Support\PhpstanFixer\PhpstanLogParserTest` – ensures parsing works.
- `Tests\Unit\Support\PhpstanFixer\Fixers\*` – cover each strategy.
- `Tests\Feature\Console\PhpstanAutoFixCommandTest` – exercises suggest/apply flows against combined fixtures.

## 🔮 Next steps
- Planned fixer strategies:
  - [ ] `MissingReturnDocblockFixer` – adds a `@return mixed` doc when PHPStan reports missing return type information.
  - [ ] `MissingPropertyDocblockFixer` – inserts `@property` hints for dynamic model properties (e.g., Eloquent `$pivot`, `$appends`).
  - [ ] `CollectionGenericDocblockFixer` – fills in collection generics such as `Collection<int, Model>` when PHPStan complains about missing generics.
- Extract the module into a standalone Composer package.
- Integrate the command into CI (suggest mode for reporting).
>>>>>>> main

## 📚 Related files
- `docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.md` – Polish version.
- `docs/issue/en/TASKS.md` / `docs/issue/pl/TASKS.md` – backlog entries.


