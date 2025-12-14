# Contributing to PHPStan Fixer

Thank you for your interest in contributing to PHPStan Fixer! This document provides guidelines and instructions for contributing.

## Code of Conduct

Be respectful, inclusive, and collaborative. Treat everyone with dignity and respect.

## How to Contribute

### Reporting Bugs

1. Check if the issue already exists in [GitHub Issues](https://github.com/lukaszzychal/phpstan-fixer/issues)
2. Create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - PHP version, PHPStan version
   - Sample code if applicable

### Suggesting Enhancements

1. Check existing issues and TODO.md
2. Create an issue describing:
   - The enhancement
   - Use case
   - Proposed solution (if any)

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass (`vendor/bin/phpunit`)
6. Run PHPStan (`vendor/bin/phpstan analyse`)
7. Commit with clear messages
8. Push to your fork
9. Create a Pull Request

## Development Setup

```bash
# Clone the repository
git clone https://github.com/lukaszzychal/phpstan-fixer.git
cd phpstan-fixer

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run PHPStan
vendor/bin/phpstan analyse
```

## Code Style

- Follow PSR-12 coding standard
- Use PHP 8.0+ features (type hints, strict types)
- Add PHPDoc comments for public methods
- Write descriptive commit messages

## Testing

- Add tests for all new features
- Maintain or improve test coverage
- Test edge cases
- Ensure backward compatibility

## Adding New Fixers

1. Create a new class in `src/PhpstanFixer/Strategy/`
2. Implement `FixStrategyInterface`
3. Add tests in `tests/Unit/Strategy/`
4. Register in `PhpstanAutoFixCommand::createDefaultAutoFixService()`
5. Update documentation

## Documentation

- Update README.md for user-facing changes
- Update CHANGELOG.md for notable changes
- Add examples where helpful
- Keep documentation in sync with code

### Bilingual Documentation

This project supports bilingual documentation (English and Polish). When creating or updating documentation:

- **See**: [Bilingual Documentation Guidelines](docs/BILINGUAL_DOCUMENTATION_GUIDELINES.md) for detailed instructions
- **File naming**: Use `DOCUMENT_NAME.md` (EN) and `DOCUMENT_NAME_PL.md` (PL)
- **Always update both versions** when making changes
- **Include language links** at the top of each document
- **Maintain structural consistency** between language versions

## Questions?

Open an issue or start a discussion on GitHub!

