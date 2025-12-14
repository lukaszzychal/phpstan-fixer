# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.2] - 2025-12-14

### Fixed
- **Laravel compatibility**: Changed `dont-discover` from boolean `true` to empty array `[]` in `composer.json` (fixes #63)
  - Laravel PackageManifest expects array, not boolean
  - Fixes error: `array_merge(): Argument #2 must be of type array, true given`

[1.2.2]: https://github.com/lukaszzychal/phpstan-fixer/releases/tag/v1.2.2

## [1.2.1] - 2025-12-14

### Fixed
- **Laravel compatibility**: Added `dont-discover` flag to `composer.json` to prevent Laravel from auto-discovering this package during `package:discover` (fixes #60)

### Enhanced
- **MissingUseStatementFixer**: Added support for additional error patterns:
  - "Unknown class"
  - "Class X is undefined"
  - "Instantiated class X not found"
  - "Referenced class X not found"
  - "Cannot resolve symbol"
  - Added comprehensive tests for all new patterns (fixes #15)

### Verified
- All high priority fixers are implemented and tested (MixinFixer, ReadonlyPropertyFixer, PrefixedTagsFixer) - fixes #18
- All medium priority fixers are implemented and tested (ImpureFunctionFixer, RequireExtendsFixer, RequireImplementsFixer, ArrayOffsetTypeFixer, IterableValueTypeFixer) - fixes #14

[1.2.1]: https://github.com/lukaszzychal/phpstan-fixer/releases/tag/v1.2.1

## [1.0.0] - 2025-12-06

### Added
- Initial release of PHPStan Auto-Fix library
- 10 fixer strategies:
  - MissingReturnDocblockFixer - adds @return annotations
  - MissingParamDocblockFixer - adds @param annotations
  - MissingPropertyDocblockFixer - adds @property/@var annotations
  - UndefinedPivotPropertyFixer - adds @property-read for Laravel pivot
  - CollectionGenericDocblockFixer - adds generics to Collection types
  - UndefinedVariableFixer - adds inline @var annotations
  - MissingUseStatementFixer - adds use statements
  - UndefinedMethodFixer - adds @method annotations
  - MissingThrowsDocblockFixer - adds @throws annotations
  - CallableTypeFixer - adds callable invocation annotations
- Framework-agnostic CLI command
- Suggest and Apply modes
- Support for PHPStan JSON output parsing
- Comprehensive test suite (~65% coverage)
- CI/CD with GitHub Actions
- Full documentation (EN/PL)

### Technical Details
- PHP 8.0+ support
- Built with nikic/php-parser for AST manipulation
- Symfony Console for CLI interface
- PSR-4 autoloading

[1.0.0]: https://github.com/lukaszzychal/phpstan-fixer/releases/tag/v1.0.0

