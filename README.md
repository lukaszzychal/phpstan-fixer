# PHPStan Auto-Fix

[![CI](https://github.com/lukaszzychal/phpstan-fixer/workflows/CI/badge.svg)](https://github.com/lukaszzychal/phpstan-fixer/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Framework-agnostic PHP library for automatically fixing PHPStan errors using static analysis rules. Works with Laravel, Symfony, CodeIgniter, and native PHP projects.

## Features

- Automatically detects and fixes common PHPStan errors
- Framework-agnostic (works with any PHP project)
- Offline-friendly (no AI or network access required)
- Suggest mode (preview changes) and Apply mode (write changes)
- Supports multiple fix strategies for different error types
- **Configuration system** - Control how each error type is handled (fix, ignore, or report)

## Installation

```bash
composer require --dev lukaszzychal/phpstan-fixer
```

## Usage

### Basic Usage (Default - Suggest Mode)

By default, the tool runs in `suggest` mode - it shows what would be changed without modifying files:

```bash
vendor/bin/phpstan-fixer
```

This is equivalent to:
```bash
vendor/bin/phpstan-fixer --mode=suggest
```

### Suggest Mode (Preview Changes)

Preview proposed fixes without modifying files (same as default):

```bash
vendor/bin/phpstan-fixer --mode=suggest
```

**Note:** Suggest mode is safe to run - it only shows what would be changed and does NOT modify your files.

### Apply Mode (Write Changes)

Apply fixes directly to files:

```bash
vendor/bin/phpstan-fixer --mode=apply
```

**Warning:** Apply mode will modify your source files. Always review changes in suggest mode first!

### Using Existing PHPStan JSON Output

If you already have a PHPStan JSON output file:

```bash
vendor/bin/phpstan-fixer --input=phpstan-output.json --mode=apply
```

### Custom PHPStan Command

Specify a custom PHPStan command:

```bash
vendor/bin/phpstan-fixer --phpstan-command="vendor/bin/phpstan analyse src tests --level=5 --error-format=json" --mode=apply
```

### Configuration File

You can configure how different error types are handled using a configuration file. Create `phpstan-fixer.yaml` or `phpstan-fixer.json` in your project root:

**YAML Format** (`phpstan-fixer.yaml`):

```yaml
rules:
  "Access to an undefined property":
    action: "fix"  # fix, ignore, or report
  
  "Method has no return type":
    action: "fix"
  
  "Unknown class":
    action: "ignore"  # Don't fix and don't show
  
  "Extra arguments":
    action: "report"  # Don't fix, but show in output
  
  # Wildcard patterns
  "Call to an undefined method *":
    action: "fix"
  
  # Regex patterns
  "/.*magic.*/":
    action: "report"

default:
  action: "fix"  # Default action for unmatched errors
```

**JSON Format** (`phpstan-fixer.json`):

```json
{
  "rules": {
    "Access to an undefined property": {
      "action": "fix"
    },
    "Method has no return type": {
      "action": "fix"
    },
    "Unknown class": {
      "action": "ignore"
    },
    "Extra arguments": {
      "action": "report"
    }
  },
  "default": {
    "action": "fix"
  }
}
```

**Configuration Actions:**

- **`fix`** (default) - Attempt to automatically fix the error
- **`ignore`** - Don't fix and don't display the error (silent ignore)
- **`report`** - Don't fix, but display in original PHPStan format

Validation: configuration is validated on load. Invalid actions (anything other than `fix`, `ignore`, `report`) or malformed rule entries will stop execution with a clear error message.

**Using Configuration:**

```bash
# Auto-discover configuration file
vendor/bin/phpstan-fixer

# Specify configuration file explicitly
vendor/bin/phpstan-fixer --config=phpstan-fixer.yaml
```

**Pattern Matching:**

- **Exact match**: `"Access to an undefined property"` - matches exactly
- **Wildcard**: `"Access to an undefined *"` - matches with `*` as wildcard
- **Regex**: `"/Access to an undefined \\w+/"` - full PCRE regex pattern

**YAML Support:**

For YAML configuration files, you need either:
- `ext-yaml` PHP extension (install via `pecl install yaml`), or
- `symfony/yaml` package (add to `composer.json`)

## Supported Fix Strategies

> 📖 **Detailed Guide**: See [PHPStan Fixers Guide](docs/PHPSTAN_FIXERS_GUIDE.md) for comprehensive documentation on each fixer, including problem descriptions, examples, and usage scenarios.

The library automatically fixes the following types of PHPStan errors:

1. **Missing Return Type** (`MissingReturnDocblockFixer`)
   - Adds `@return` annotations when return type is missing

2. **Missing Parameter Type** (`MissingParamDocblockFixer`)
   - Adds `@param` annotations for parameters without types

3. **Undefined Properties** (`MissingPropertyDocblockFixer`)
   - Adds `@property` or `@var` annotations for undefined properties

4. **Eloquent Pivot Property** (`UndefinedPivotPropertyFixer`)
   - Adds `@property-read` annotation for Laravel Eloquent `$pivot` property

5. **Collection Generics** (`CollectionGenericDocblockFixer`)
   - Adds generic type parameters to Collection types (e.g., `Collection<int, mixed>`)

6. **Undefined Variables** (`UndefinedVariableFixer`)
   - Adds inline `@var` annotations for undefined variables

7. **Missing Use Statements** (`MissingUseStatementFixer`)
   - Adds `use` statements for undefined classes

8. **Undefined Methods** (`UndefinedMethodFixer`)
   - Adds `@method` annotations for magic methods

9. **Missing Throws Annotation** (`MissingThrowsDocblockFixer`)
   - Adds `@throws` annotations when exceptions are thrown

10. **Callable Type Invocation** (`CallableTypeFixer`)
    - Adds `@param-immediately-invoked-callable` or `@param-later-invoked-callable` annotations

11. **Mixin Annotation** (`MixinFixer`)
    - Adds `@mixin ClassName` annotation for classes using magic methods (`__call`, `__get`, `__set`) to delegate calls

## Examples

### Example 1: Missing Return Type

**Before:**
```php
function calculateSum($a, $b) {
    return $a + $b;
}
```

**After:**
```php
/**
 * @return mixed
 */
function calculateSum($a, $b) {
    return $a + $b;
}
```

### Example 2: Undefined Property

**Before:**
```php
class User {
    public function getName() {
        return $this->name; // PHPStan error: undefined property
    }
}
```

**After:**
```php
/**
 * @property string $name
 */
class User {
    public function getName() {
        return $this->name;
    }
}
```

### Example 3: Collection Generics

**Before:**
```php
/**
 * @return Collection
 */
function getItems() {
    return collect([]);
}
```

**After:**
```php
/**
 * @return Collection<int, mixed>
 */
function getItems() {
    return collect([]);
}
```

## Configuration

The tool works out of the box with default settings. All fix strategies are enabled by default.

## How It Works

1. Runs PHPStan (or reads existing JSON output)
2. Parses PHPStan JSON output into structured Issue objects
3. Matches each issue to appropriate fix strategies
4. Applies fixes using AST parsing and PHPDoc manipulation
5. Shows preview (suggest mode) or writes changes (apply mode)

## Requirements

- PHP 8.0 or higher
- PHPStan (installed via Composer)
- nikic/php-parser (automatically installed)

## Development

### Running Tests

```bash
vendor/bin/phpunit
```

### CI/CD

The project uses GitHub Actions for continuous integration:

- **CI Workflow**: Runs tests on PHP 8.0-8.3, static analysis, and code style checks
- **Release Workflow**: Automatically creates GitHub releases on version tags
- **Self-Test Workflow**: Tests PHPStan Fixer on its own codebase

See [`.github/workflows/`](.github/workflows/) for details.

### Compatibility Testing

This package uses [PHP Compatibility Tester](https://github.com/lukaszzychal/php-compatibility-tester) to ensure compatibility across different frameworks and PHP versions:

- **Packagist**: [lukaszzychal/php-compatibility-tester](https://packagist.org/packages/lukaszzychal/php-compatibility-tester)
- **GitHub**: [lukaszzychal/php-compatibility-tester](https://github.com/lukaszzychal/php-compatibility-tester)

Compatibility tests run automatically:
- **Monthly**: On the 1st day of each month via GitHub Actions
- **Manually**: Trigger via GitHub Actions UI (workflow_dispatch)
- **Locally**: Run `vendor/bin/compatibility-tester test` to test locally

This automatically tests `phpstan-fixer` against various frameworks (Laravel 11/12, Symfony 7/8, CodeIgniter 4/5) and PHP versions (8.1-8.4) to ensure it works correctly in different environments. Test reports are available as GitHub Actions artifacts.

#### Initializing Compatibility Testing

To set up compatibility testing in your project:

1. **Run the init command**:
   ```bash
   vendor/bin/compatibility-tester init
   ```

2. **The command will**:
   - Create `.compatibility.yml` configuration file
   - Copy PHPUnit test templates to `tests/compatibility/`
   - Copy GitHub Actions workflow to `.github/workflows/compatibility-tests.yml`
   - Copy test scripts to `scripts/`

3. **Edit `.compatibility.yml`** to configure:
   - Your package name
   - PHP versions to test
   - Frameworks and versions
   - Test scripts to run

**Note**: If the example configuration file is not found in the package, you can use the example from `vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml` as a reference.

### Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## FAQ

### What's the difference between the execution modes?

There are three ways to run the tool:

1. **`vendor/bin/phpstan-fixer`** (default, no parameters)
   - Equivalent to `--mode=suggest`
   - Shows what would be changed
   - Does NOT modify files
   - Safe to run - preview only

2. **`vendor/bin/phpstan-fixer --mode=suggest`**
   - Explicit suggest mode (same as default)
   - Shows proposed changes
   - Does NOT modify files
   - Safe to run - preview only

3. **`vendor/bin/phpstan-fixer --mode=apply`**
   - Actually writes changes to files
   - Modifies source code
   - Use with caution - creates permanent changes

**Recommendation:** Always run with `--mode=suggest` first to preview changes before applying them.

### What happens to errors that can't be fixed?

If a fixer strategy cannot automatically fix an error, it will be displayed at the end of the output in PHPStan format. These are errors that require manual intervention or are not yet supported by any fixer strategy.

## License

MIT License - see LICENSE file for details.

## Author

**Łukasz Zychal**

- Email: [lukasz.zychal.dev@gmail.com](mailto:lukasz.zychal.dev@gmail.com)
- GitHub Issues: [Report issues and bugs](https://github.com/lukaszzychal/phpstan-fixer/issues)
- Discussions: [Join the discussion](https://github.com/lukaszzychal/phpstan-fixer/discussions)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/lukaszzychal/phpstan-fixer/issues) page.

