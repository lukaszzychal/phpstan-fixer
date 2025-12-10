# TODO - Missing Fixers

This document lists fixers that could be implemented based on PHPStan documentation but are not yet available.

## Organization

All tasks from this TODO are organized into GitHub milestones:
- **v1.0.1**: Bug fixes (Issue #16)
- **v1.1.0**: Configuration system (Issue #17)
- **v1.1.1**: High priority fixers (Issue #18)
- **v1.2.0**: Enhancements (Issues #14, #15)

See [ROADMAP.md](ROADMAP.md) for detailed planning.

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
- **Status:** Implemented (see PrefixedTagsFixer)
- **Reference:** PHPStan docs - Prefixed tags section

## Medium Priority

### 4. ImpureFunctionFixer
- **Error Pattern:** Functions that may return different values on successive calls
- **Fix:** Add `@phpstan-impure` or `@phpstan-pure` tag
- **Status:** Implemented (see ImpureFunctionFixer)
- **Reference:** PHPStan docs - Impure functions section

### 5. RequireExtendsFixer
- **Error Pattern:** Interface/trait requires specific base class
- **Fix:** Add `@phpstan-require-extends ClassName` on interface/trait
- **Status:** Implemented (see RequireExtendsFixer)
- **Reference:** PHPStan docs - Enforcing class inheritance section

### 6. RequireImplementsFixer
- **Error Pattern:** Trait requires interface implementation
- **Fix:** Add `@phpstan-require-implements InterfaceName` on trait
- **Status:** Implemented (see RequireImplementsFixer)
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
- **Status:** Implemented (see InternalAnnotationFixer)
- **Reference:** PHPStan docs - Internal symbols section

### 10. ClassesNamedAfterInternalTypesFixer
- **Error Pattern:** Class name conflict with PHP internal types (Resource, Double, Number)
- **Fix:** Change PHPDoc to use fully-qualified name
- **Status:** Implemented (see ClassesNamedAfterInternalTypesFixer)
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
    - **Status:** Implemented (see ArrayOffsetTypeFixer)
    - **Priority:** Medium
    - **Reference:** PHPStan Level 5

12. **IterableValueTypeFixer**
    - **Error Pattern:** "Missing iterable value type"
    - **Fix:** Add value type to iterable (e.g., `iterable<string>`)
    - **Status:** Implemented (see IterableValueTypeFixer)
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

## Configuration System (v1.1.0+)

### Per-Error Configuration
Allow users to configure how each error type should be handled:

1. **Fix** (default) - Attempt to automatically fix the error
2. **Ignore** - Don't fix and don't display the error at all (silent ignore)
3. **Report** - Don't fix, but display in original PHPStan format

**Configuration file format** (phpstan-fixer.yaml):
```yaml
rules:
  "Access to an undefined property":
    action: "fix"  # or "ignore" or "report"
  
  "Method has no return type":
    action: "fix"
  
  "Unknown class":
    action: "ignore"  # Skip fixing, don't show
  
  "Extra arguments":
    action: "report"  # Don't fix, but show in output
```

**Implementation tasks:**
- [ ] Create configuration file parser
- [ ] Add configuration loading to AutoFixService
- [ ] Implement ignore action (skip processing)
- [ ] Implement report action (pass through to output)
- [ ] Add validation for configuration
- [ ] Add documentation for configuration

## Known Issues / Bugs to Fix

### PhpParser API Compatibility
- **Status**: ✅ Fixed
- **Priority**: High (was blocking tests)
- **Description**: PhpParser API compatibility issues have been resolved:
  - ✅ Updated `PhpParser\ParserFactory::create()` to `createForNewestSupportedVersion()`
  - ✅ Fixed DocblockManipulator parsing logic
  - ✅ All tests passing (66/66)
  - ✅ PHPStan analysis clean
- **Affected files** (fixed):
  - `src/PhpstanFixer/CodeAnalysis/PhpFileAnalyzer.php` (line 38) - uses `createForNewestSupportedVersion()`
  - `src/PhpstanFixer/CodeAnalysis/DocblockManipulator.php` - parsing logic corrected
- **Resolution**: 
  - [x] Verified PhpParser version compatibility (v5.6.2, compatible with `^5.0`)
  - [x] Updated PhpParser usage to match current API
  - [x] Fixed DocblockManipulator parsing logic
  - [x] All tests pass (66/66)
- **Related Issue**: #16 (resolved)

## Notes

- See `IMPLEMENTED_FIXERS.md` for complete comparison
- See `PHPSTAN_LEVELS_ANALYSIS.md` for analysis by PHPStan levels (0-8)
- Current implementation covers ~70% of common PHPStan errors
- Focus should be on high-priority fixers for next release

