# Status Branchy w Repozytorium

**Ostatnia aktualizacja:** 2025-12-08 01:11:49

## Lokalne Branche

### `main`
- **Status:** ✅ Zsynchronizowany z `origin/main`
- **Ostatni commit:** `fix: Remove version field from composer.json (let Packagist auto-detect from tags)`
- **Cel:** Główny branch produkcyjny
- **Ochrona:** Włączona (wymaga PR)

### `docs/branch-protection-setup`
- **Status:** 🔄 OTWARTY PR#4
- **Cel:** Dokumentacja konfiguracji branch protection i helper files
- **Link PR:** https://github.com/lukaszzychal/phpstan-fixer/pull/4
- **Akcja:** Do zmergowania gdy gotowe
- **Commits:** 1 commit (dokumentacja i config files)

## Zdalne Branche

### `origin/main`
- **Status:** ✅ Aktualny
- **Ostatni commit:** `60c0900` - fix: Remove version field from composer.json

### `origin/docs/branch-protection-setup`
- **Status:** 🔄 OTWARTY PR#4
- **Synchronizacja:** ✅ Zsynchronizowany z lokalnym branch
- **Link PR:** https://github.com/lukaszzychal/phpstan-fixer/pull/4

### `origin/dependabot/composer/phpstan/phpstan-tw-1.10or-tw-2.0`
- **Status:** 🔄 OTWARTY PR#3
- **Cel:** Aktualizacja zależności `phpstan/phpstan` z `^1.10` do `^1.10 || ^2.0`
- **Link PR:** https://github.com/lukaszzychal/phpstan-fixer/pull/3
- **Akcja:** Do review i merge/reject
- **Uwaga:** To branch od Dependabot, nie ma lokalnej kopii (nie jest potrzebna)

## Zmergowane Branche (już usunięte)

### PR#1: `fix/array-splice-and-line-replacement-bugs`
- **Status:** ✅ ZMERGOWANY i USUNIĘTY
- **Data merge:** 2025-12-06
- **Commit merge:** `5222612`

### PR#2: `feature/configuration-system-docs`
- **Status:** ✅ ZMERGOWANY i USUNIĘTY
- **Data merge:** 2025-12-06
- **Commit merge:** `5222612` (bezpośrednio po PR#1)

## Instrukcje Utrzymania

### Aktualizacja branchy lokalnych
```bash
git fetch --prune --all
git checkout main
git pull origin main
```

### Usuwanie zmergowanych branchy lokalnych
```bash
# Po zmergowaniu PR#4:
git branch -d docs/branch-protection-setup

# Sprawdzenie nieużywanych branchy:
git branch --merged | grep -v "main"
```

### Sprawdzenie statusu wszystkich PR
```bash
gh pr list --state all
```

### Czyszczenie zdalnych branchy (tylko przez GitHub UI lub API)
- Zmergowane PR są automatycznie usuwane przez GitHub (jeśli skonfigurowane)
- Ręczne usuwanie przez: `git push origin --delete <branch-name>`

## Uwagi

- **Branch Protection:** Włączona dla `main` - wymaga PR do merge
- **Dependabot:** Automatycznie tworzy branche dla aktualizacji zależności
- **Wszystkie branche są aktualne:** Nie ma niepotrzebnych branchy do usunięcia

