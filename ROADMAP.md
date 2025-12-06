# Roadmap

This document outlines future plans and ideas for PHPStan Fixer.

## Version 1.1.0 (Short-term)

### High Priority
- [ ] **MixinFixer** - Add `@mixin` support for delegated methods
- [ ] **ReadonlyPropertyFixer** - Add `@readonly` tag support (PHP < 8.1)
- [ ] **PrefixedTagsFixer** - Support `@phpstan-param`, `@phpstan-return` for advanced types
- [ ] **Improved MissingUseStatementFixer** - Better FQN resolution with symbol discovery

### Medium Priority
- [ ] **ArrayOffsetTypeFixer** - Add generics to array types (Level 5)
- [ ] **IterableValueTypeFixer** - Add value types to iterable (Level 5)
- [ ] **CLI Command tests** - Add comprehensive command testing

## Version 1.2.0 (Medium-term)

### Features
- [ ] **Configuration file** - Allow custom fixer configuration (enable/disable fixers)
- [ ] **Whitelist/Blacklist** - Configure which files/directories to process
- [ ] **Dry-run diff output** - Show unified diff in suggest mode
- [ ] **Fixer priorities** - Control order of fixer execution
- [ ] **Custom fixers** - Allow users to register custom fixer strategies

### Improvements
- [ ] **Better type inference** - Infer more specific types than `mixed`
- [ ] **Framework detection** - Auto-detect Laravel/Symfony and apply framework-specific fixes
- [ ] **Batch processing** - Optimize performance for large codebases
- [ ] **Progress indicators** - Show progress for long-running operations

## Version 2.0.0 (Long-term)

### Major Features
- [ ] **IDE Integration** - VSCode, PhpStorm plugins
- [ ] **Git Hooks** - Pre-commit hook support
- [ ] **Baseline support** - Read PHPStan baseline and fix only new errors
- [ ] **Multi-file fixes** - Fix issues that span multiple files
- [ ] **Refactoring support** - Go beyond PHPDoc, fix actual code issues

### Architecture
- [ ] **Plugin system** - Allow external fixers via plugins
- [ ] **Rule engine** - More flexible rule matching system
- [ ] **Fixer chains** - Allow fixers to depend on each other
- [ ] **Parallel processing** - Process multiple files in parallel

## Research & Ideas

### Advanced Features
- **ML/AI assistance** (optional) - Learn from code patterns to suggest better fixes
- **Code metrics** - Track code quality improvements over time
- **Integration with other tools** - PHP-CS-Fixer, Rector, etc.
- **Web UI** - Browser-based interface for reviewing fixes
- **API mode** - REST/GraphQL API for CI/CD integration

### Community
- **Extension library** - Collection of community-contributed fixers
- **Fixer marketplace** - Share and discover custom fixers
- **Templates** - Pre-configured setups for different frameworks

### Performance
- **Incremental analysis** - Only analyze changed files
- **Caching** - Cache parsed ASTs and fix results
- **Lazy loading** - Load fixers on demand

## Feedback Welcome

Have ideas or suggestions? Open an issue or discussion on GitHub!

