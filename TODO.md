# TODO - Missing Fixers

This document lists fixers that could be implemented based on PHPStan documentation but are not yet available.

## High Priority

### 1. MixinFixer
- **Error Pattern:** Methods delegated via `__call` / `__get` / `__set`
- **Fix:** Add `@mixin ClassName` on class
- **Status:** Not implemented
- **Reference:** PHPStan docs - Mixins section

### 2. ReadonlyPropertyFixer
- **Error Pattern:** Property assigned outside of declaring class (PHP < 8.1)
- **Fix:** Add `@readonly` tag on property
- **Status:** Not implemented
- **Reference:** PHPStan docs - Readonly properties section

### 3. PrefixedTagsFixer
- **Error Pattern:** Advanced types not understood by IDEs
- **Fix:** Add `@phpstan-param`, `@phpstan-return` alongside standard tags
- **Status:** Not implemented
- **Reference:** PHPStan docs - Prefixed tags section

## Medium Priority

### 4. ImpureFunctionFixer
- **Error Pattern:** Functions that may return different values on successive calls
- **Fix:** Add `@phpstan-impure` or `@phpstan-pure` tag
- **Status:** Not implemented
- **Reference:** PHPStan docs - Impure functions section

### 5. RequireExtendsFixer
- **Error Pattern:** Interface/trait requires specific base class
- **Fix:** Add `@phpstan-require-extends ClassName` on interface/trait
- **Status:** Not implemented
- **Reference:** PHPStan docs - Enforcing class inheritance section

### 6. RequireImplementsFixer
- **Error Pattern:** Trait requires interface implementation
- **Fix:** Add `@phpstan-require-implements InterfaceName` on trait
- **Status:** Not implemented
- **Reference:** PHPStan docs - Enforcing implementing interface section

## Low Priority

### 7. ImmutableClassFixer
- **Error Pattern:** Property assigned outside of immutable class
- **Fix:** Add `@immutable` or `@readonly` tag on class
- **Status:** Not implemented
- **Reference:** PHPStan docs - Immutable classes section

### 8. SealedClassFixer
- **Error Pattern:** Class extends sealed class (PHPStan 2.1.18+)
- **Fix:** Add `@phpstan-sealed Class1|Class2` tag
- **Status:** Not implemented
- **Reference:** PHPStan docs - Sealed classes section

### 9. InternalAnnotationFixer
- **Error Pattern:** Access to internal element
- **Fix:** Add `@internal` tag (rarely needed automatically)
- **Status:** Not implemented
- **Reference:** PHPStan docs - Internal symbols section

### 10. ClassesNamedAfterInternalTypesFixer
- **Error Pattern:** Class name conflict with PHP internal types (Resource, Double, Number)
- **Fix:** Change PHPDoc to use fully-qualified name
- **Status:** Not implemented
- **Reference:** PHPStan docs - Classes named after internal PHP types section

## Improvements Needed

### MissingUseStatementFixer Enhancement
- **Current Status:** Basic implementation works, but:
  - Does not automatically resolve fully-qualified class names
  - Requires manual FQN correction in some cases
  - Does not search vendor/ for class discovery
- **Enhancement:** Add symbol discovery mechanism

## From PHPStan Levels Analysis

### Level 4-5 Additional Fixers:

11. **ArrayOffsetTypeFixer**
    - **Error Pattern:** "Unknown array offset types" / "Missing iterable value type"
    - **Fix:** Add generics to array types (e.g., `array<int, string>`)
    - **Status:** Not implemented
    - **Priority:** Medium
    - **Reference:** PHPStan Level 5

12. **IterableValueTypeFixer**
    - **Error Pattern:** "Missing iterable value type"
    - **Fix:** Add value type to iterable (e.g., `iterable<string>`)
    - **Status:** Not implemented
    - **Priority:** Medium
    - **Reference:** PHPStan Level 5

### Level 0-1 Additional Fixers:

13. **MagicPropertyFixer** (enhancement)
    - **Error Pattern:** "Unknown magic properties on classes with __get"
    - **Fix:** Enhance MissingPropertyDocblockFixer to better detect magic properties
    - **Status:** Partially implemented (needs enhancement)
    - **Priority:** Low
    - **Reference:** PHPStan Level 1

## Difficult/Not Recommended (Require Code Logic Changes)

These errors cannot be safely fixed automatically as they require changing code logic:

1. **ExtraArgumentsFixer** - Would need to remove arguments (risky)
2. **DeadCodeFixer** - Would need to delete code (dangerous)
3. **MixedTypeFixer** - Requires concrete types (context needed)
4. **NullSafetyFixer** - Requires adding null checks (code changes)
5. **TypeCoercionFixer** - Requires code refactoring
6. **InvalidArgumentFixer** - Requires changing function calls

## Notes

- See `IMPLEMENTED_FIXERS.md` for complete comparison
- See `PHPSTAN_LEVELS_ANALYSIS.md` for analysis by PHPStan levels (0-8)
- Current implementation covers ~70% of common PHPStan errors
- Focus should be on high-priority fixers for next release

