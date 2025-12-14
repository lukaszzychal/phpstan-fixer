# Wytyczne Dokumentacji Dwujęzycznej (Bilingual Documentation Guidelines)

> 🇵🇱 **Polish version**: [Wytyczne Dokumentacji Dwujęzycznej (PL)](BILINGUAL_DOCUMENTATION_GUIDELINES_PL.md)

## Wprowadzenie

Projekt `phpstan-fixer` wspiera dokumentację w dwóch językach:
- **Angielski (EN)** - język główny, domyślny
- **Polski (PL)** - język dodatkowy

Wszystkie dokumenty techniczne powinny być dostępne w obu językach.

## Konwencja Nazewnictwa Plików

### Standardowa konwencja (preferowana)

- **Angielska wersja**: `DOCUMENT_NAME.md` (bez sufiksu)
- **Polska wersja**: `DOCUMENT_NAME_PL.md` (sufiks `_PL`)

**Przykłady**:
- `README.md` / `README_PL.md` ✅
- `docs/PHPSTAN_FIXERS_GUIDE.md` / `docs/PHPSTAN_FIXERS_GUIDE_PL.md` ✅
- `docs/FRAMEWORK_DETECTION.md` / `docs/FRAMEWORK_DETECTION_PL.md` ✅

### Wyjątki (legacy)

Niektóre starsze dokumenty mogą używać innej konwencji (np. `TASK_016_PHPSTAN_AUTO_FIX.en.md`). 
Dla nowych dokumentów zawsze używaj standardowej konwencji (`DOCUMENT_NAME.md` / `DOCUMENT_NAME_PL.md`).

## Struktura Dokumentów

### 1. Linki do wersji językowych

Każdy dokument powinien zawierać linki do swojej wersji w innym języku na początku pliku.

**Przykład dla dokumentu angielskiego**:
```markdown
# Document Title

> 🇵🇱 **Polish version**: [Tytuł dokumentu (PL)](DOCUMENT_NAME_PL.md)
```

**Przykład dla dokumentu polskiego**:
```markdown
# Tytuł dokumentu

> 🇬🇧 **English version**: [Document Title (EN)](DOCUMENT_NAME.md)
```

### 2. Spójność treści

- Treść powinna być tłumaczona, nie parafrazowana
- Struktura dokumentów (nagłówki, sekcje, listy) powinna być identyczna
- Linki wewnętrzne powinny wskazywać na odpowiednie wersje językowe
- Przykłady kodu mogą być takie same (nie wymagają tłumaczenia)

### 3. Aktualizacje

- Przy aktualizowaniu dokumentu, zaktualizuj obie wersje językowe
- Jeśli dodajesz nową sekcję, dodaj ją do obu wersji
- Sprawdź spójność po każdej większej zmianie

## Kiedy Tworzyć Dokumentację Dwujęzyczną?

### Dokumenty wymagające tłumaczenia

✅ **Wymagają wersji PL**:
- `README.md` - główna dokumentacja projektu
- `docs/*.md` - wszystkie dokumenty techniczne
- `CONTRIBUTING.md` - wytyczne dla kontrybutorów
- Dokumenty z instrukcjami użycia

❌ **Nie wymagają tłumaczenia**:
- `CHANGELOG.md` - może być tylko w języku angielskim
- Pliki konfiguracyjne (`.yml`, `.yaml`, `.json`) - komentarze mogą być po angielsku
- Pliki testowe - komentarze w kodzie mogą być po angielsku

## Proces Tworzenia Dokumentacji

### 1. Tworzenie nowego dokumentu

1. Utwórz dokument w języku angielskim jako `DOCUMENT_NAME.md`
2. Dodaj link do przyszłej wersji polskiej:
   ```markdown
   > 🇵🇱 **Polish version**: [Tytuł (PL)](DOCUMENT_NAME_PL.md) *(coming soon)*
   ```
3. Po przetłumaczeniu, utwórz `DOCUMENT_NAME_PL.md`
4. Zaktualizuj link w wersji angielskiej (usuń *(coming soon)*)

### 2. Aktualizacja istniejącego dokumentu

1. Zaktualizuj dokument w języku źródłowym
2. Zaktualizuj odpowiadający mu dokument w drugim języku
3. Sprawdź spójność struktur (nagłówki, sekcje)
4. Zweryfikuj, że linki działają poprawnie

### 3. Weryfikacja spójności

Przed commitem sprawdź:
- ✅ Czy obie wersje mają tę samą strukturę (nagłówki H1-H6)
- ✅ Czy wszystkie sekcje są obecne w obu wersjach
- ✅ Czy linki wskazują na poprawne wersje językowe
- ✅ Czy przykłady kodu są identyczne (jeśli to możliwe)

## Narzędzia i Skrypty

### Weryfikacja spójności struktury

Możesz użyć skryptu do sprawdzenia spójności nagłówków między wersjami językowymi:

```bash
./scripts/verify_doc_structure.sh docs/DOCUMENT_NAME.md
```

Skrypt porównuje:
- Liczbę nagłówków (H1-H6) w obu wersjach
- Strukturę poziomów nagłówków
- Sprawdza, czy istnieje polska wersja dokumentu

**Przykład użycia**:
```bash
# Sprawdź spójność README.md
./scripts/verify_doc_structure.sh README.md

# Sprawdź dokumentację techniczną
./scripts/verify_doc_structure.sh docs/FRAMEWORK_DETECTION.md
```

## Lista Dokumentów Do Weryfikacji

Zobacz [V1.2.0_IMPLEMENTATION_PLAN.md](../V1.2.0_IMPLEMENTATION_PLAN.md) dla pełnej listy dokumentów wymagających weryfikacji lub tłumaczenia.

## FAQ

### P: Czy muszę tłumaczyć przykłady kodu?

**Odpowiedź**: Nie. Przykłady kodu, komendy CLI, nazwy zmiennych/funkcji mogą pozostać w oryginalnej formie. Tłumacz tylko opisowy tekst.

### P: Co jeśli nie jestem pewien tłumaczenia technicznego terminu?

**Odpowiedź**: Użyj angielskiego terminu w nawiasie, np. "fixer (narzędzie do naprawy)". Albo użyj najbardziej powszechnie przyjętego tłumaczenia w społeczności PHP/PHPStan.

### P: Jak często aktualizować dokumentację?

**Odpowiedź**: Przy każdej większej zmianie w funkcjonalności lub przy dodawaniu nowych funkcji. Staraj się aktualizować obie wersje jednocześnie.

### P: Co jeśli dokument jest bardzo długi i tłumaczenie zajmie dużo czasu?

**Odpowiedź**: Możesz utworzyć polską wersję z podstawową treścią i stopniowo ją rozbudowywać. Ważne, aby struktura była spójna od początku.

## Kontakt

W razie pytań dotyczących dokumentacji, skontaktuj się z maintainerem projektu.

