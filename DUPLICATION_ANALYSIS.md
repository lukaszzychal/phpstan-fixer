# Analiza Duplikacji w Strategy/*.php

## Zidentyfikowane wzorce duplikacji

### 1. Formatowanie typów PHP-Parser

**Duplikacja**: Metoda `formatType()` występuje w:
- `MissingReturnDocblockFixer.php` (linie 179-202)
- `MissingParamDocblockFixer.php` (prawdopodobnie podobna implementacja)

**Wzorzec**:
```php
private function formatType($typeNode): string
{
    if (is_string($typeNode)) {
        return $typeNode;
    }
    if ($typeNode instanceof \PhpParser\Node\Name) {
        return $typeNode->toString();
    }
    // ... więcej warunków
}
```

**Rozwiązanie**: Wyciągnąć do trait `TypeFormatterTrait` lub klasy pomocniczej.

### 2. Znajdowanie funkcji/metod na podstawie linii

**Duplikacja**: Kod znajdowania `$targetFunction` i `$targetMethod` występuje w:
- `MissingReturnDocblockFixer.php` (linie 58-85)
- `MissingParamDocblockFixer.php` (linie 64-97)
- Prawdopodobnie w innych fixerach

**Wzorzec**:
```php
$functions = $this->analyzer->getFunctions($ast);
$classes = $this->analyzer->getClasses($ast);

$targetFunction = null;
$targetMethod = null;

// Check functions first
foreach ($functions as $function) {
    $functionLine = $this->analyzer->getNodeLine($function);
    if ($functionLine === $targetLine) {
        $targetFunction = $function;
        break;
    }
}

// Check methods in classes
if ($targetFunction === null) {
    foreach ($classes as $class) {
        $methods = $this->analyzer->getMethods($class);
        foreach ($methods as $method) {
            $methodLine = $this->analyzer->getNodeLine($method);
            if ($methodLine === $targetLine) {
                $targetMethod = $method;
                break 2;
            }
        }
    }
}
```

**Rozwiązanie**: Wyciągnąć do metody w trait `FunctionLocatorTrait`.

### 3. Walidacja pliku i parsowanie AST

**Duplikacja**: Powtarzający się kod:
- `MissingReturnDocblockFixer.php` (linie 45-52)
- `MissingParamDocblockFixer.php` (linie 45-52)

**Wzorzec**:
```php
if (!file_exists($issue->getFilePath())) {
    return FixResult::failure($issue, $fileContent, 'File does not exist');
}

$ast = $this->analyzer->parse($fileContent);
if ($ast === null) {
    return FixResult::failure($issue, $fileContent, 'Could not parse file');
}
```

**Rozwiązanie**: Wyciągnąć do metody pomocniczej w trait `FileValidationTrait`.

### 4. Parsowanie parametrów z komunikatów błędów PHPStan

**Duplikacja**: Podobne wzorce regex do parsowania:
- `MissingParamDocblockFixer.php` - `extractParameterInfo()` (linie 183-199)
- `PrefixedTagsFixer.php` - podobne parsowanie (linie 141-148)
- `CallableTypeFixer.php` - parsowanie parametrów (linie 169-174)

**Wzorzec**:
```php
if (preg_match('/Parameter\s+#(\d+)\s+\$(\w+)/i', $message, $matches)) {
    // ...
}
if (preg_match('/Parameter\s+\$(\w+)/i', $message, $matches)) {
    // ...
}
```

**Rozwiązanie**: Utworzyć `ErrorMessageParser` helper class.

### 5. Praca z docblockami

**Duplikacja**: Podobny kod sprawdzania i dodawania adnotacji:
- Sprawdzanie czy adnotacja już istnieje
- Dodawanie adnotacji do istniejącego docblocka
- Tworzenie nowego docblocka

**Wzorzec**: Powtarza się w wielu fixerach, ale głównie używa `DocblockManipulator` - więc duplikacja jest minimalna.

## Proponowane rozwiązania

### 1. Utworzenie traitów pomocniczych

1. **`TypeFormatterTrait`** - formatowanie typów PHP-Parser
2. **`FunctionLocatorTrait`** - znajdowanie funkcji/metod na linii
3. **`FileValidationTrait`** - walidacja pliku i parsowanie AST

### 2. Utworzenie klasy pomocniczej

**`ErrorMessageParser`** - parsowanie komunikatów błędów PHPStan
- `parseParameterName()`
- `parseParameterIndex()`
- `parseType()`
- `parseClassName()`

### 3. Kolejność refaktoryzacji

1. ✅ `TypeFormatterTrait` - utworzony i zastosowany w 2 fixerach
2. ✅ `FileValidationTrait` - utworzony i zastosowany w 2 fixerach
3. ⏳ `FunctionLocatorTrait` - do zrobienia w Fazie 3
4. ⏳ `ErrorMessageParser` - do zrobienia w Fazie 3

## Status Fazy 2

✅ **Zakończona** - Utworzono 2 traity i zastosowano je w 2 fixerach:
- `TypeFormatterTrait` - eliminuje duplikację `formatType()`
- `FileValidationTrait` - eliminuje duplikację walidacji pliku i parsowania AST

**Następne kroki** (Faza 3):
- Zastosować `FileValidationTrait` do pozostałych ~18 fixerów
- Utworzyć `FunctionLocatorTrait` dla znajdowania funkcji/metod
- Utworzyć `ErrorMessageParser` helper class

