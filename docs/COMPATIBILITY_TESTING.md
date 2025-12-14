# Compatibility Testing

> 🇵🇱 **Polish version**: [Testy Kompatybilności (PL)](COMPATIBILITY_TESTING_PL.md)

This document explains how to set up and use compatibility testing for your PHP package using `php-compatibility-tester`.

## Overview

Compatibility testing ensures your package works correctly across different PHP versions and frameworks. The `php-compatibility-tester` package automates this process by:

- Testing your package against multiple PHP versions (8.1, 8.2, 8.3, 8.4)
- Testing integration with popular frameworks (Laravel, Symfony, CodeIgniter, etc.)
- Running custom test scripts to verify functionality
- Generating reports for CI/CD integration

## Installation

Add `php-compatibility-tester` as a dev dependency:

```bash
composer require --dev lukaszzychal/php-compatibility-tester
```

## Initialization

### Quick Start

Run the initialization command:

```bash
vendor/bin/compatibility-tester init
```

This command will:

1. **Create `.compatibility.yml`** - Main configuration file
2. **Copy PHPUnit test templates** to `tests/compatibility/`:
   - `FrameworkCompatibilityTest.php`
   - `ComposerCompatibilityTest.php`
3. **Copy GitHub Actions workflow** to `.github/workflows/compatibility-tests.yml`
4. **Copy test scripts** to `scripts/compatibility-test.sh`

### Configuration File

The `.compatibility.yml` file is created in your project root. If the example template is not found in the package, you can reference the example from:

```
vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml
```

## Configuration

### Basic Configuration

Edit `.compatibility.yml` to configure your testing:

```yaml
package_name: "vendor/package-name"

php_versions: ['8.1', '8.2', '8.3', '8.4']

frameworks:
  laravel:
    versions: ['11.*', '12.*']
    install_command: 'composer create-project laravel/laravel'
    php_min_version: '8.1'
  
  symfony:
    versions: ['7.4.*', '8.0.*']
    install_command: 'composer create-project symfony/skeleton'
    php_min_version: '8.1'
  
  codeigniter:
    versions: ['4.*', '5.*']
    install_command: 'composer create-project codeigniter4/appstarter'
    php_min_version: '8.1'

test_scripts:
  - name: "Autoload test"
    command: "composer dump-autoload && php -r \"require 'vendor/autoload.php'; echo 'Autoload OK';\""
  
  - name: "Binary test"
    command: "vendor/bin/your-binary --help"
  
  - name: "Basic functionality test"
    command: "php -r \"require 'vendor/autoload.php'; use YourNamespace\\YourClass; echo 'Classes loaded OK';\""

github_actions:
  enabled: true
```

### Configuration Options

#### `package_name`
Your Composer package name (e.g., `lukaszzychal/phpstan-fixer`)

#### `php_versions`
Array of PHP versions to test against (e.g., `['8.1', '8.2', '8.3', '8.4']`)

#### `frameworks`
Framework configurations. Each framework can specify:
- `versions`: Framework versions to test (supports wildcards like `11.*`)
- `install_command`: Command to create a new framework project
- `php_min_version`: Minimum PHP version required

#### `test_scripts`
Array of test scripts to run. Each script has:
- `name`: Descriptive name for the test
- `command`: Shell command to execute

#### `github_actions`
GitHub Actions integration:
- `enabled`: Enable/disable GitHub Actions workflow

## Running Tests

### Locally

Run compatibility tests locally:

```bash
vendor/bin/compatibility-tester test
```

### Filter by Framework

Test only specific frameworks:

```bash
vendor/bin/compatibility-tester test --framework=laravel
```

### Filter by PHP Version

Test only specific PHP versions:

```bash
vendor/bin/compatibility-tester test --php-version=8.3
```

## CI/CD Integration

### GitHub Actions

The init command automatically creates `.github/workflows/compatibility-tests.yml`. This workflow:

- Runs monthly (1st day of each month at 2 AM UTC)
- Can be triggered manually via `workflow_dispatch`
- Tests against all configured PHP versions
- Generates test reports as artifacts

### Manual CI Integration

You can also integrate into your existing CI pipeline:

```yaml
# .github/workflows/compatibility.yml
name: Compatibility Tests

on:
  schedule:
    - cron: '0 2 1 * *'  # Monthly
  workflow_dispatch:

jobs:
  compatibility:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3', '8.4']
    
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      
      - name: Run compatibility tests
        run: vendor/bin/compatibility-tester test
```

## Custom Test Scripts

### Creating Test Scripts

Create custom test scripts in `tests/compatibility/`:

```php
<?php
// tests/compatibility/check-autoload.php

require __DIR__ . '/../../vendor/autoload.php';

use YourNamespace\YourClass;

// Test that classes can be loaded
$instance = new YourClass();
echo "Autoload OK\n";
```

### Adding to Configuration

Reference your test scripts in `.compatibility.yml`:

```yaml
test_scripts:
  - name: "Autoload test"
    script: "tests/compatibility/check-autoload.php"
    description: "Test class autoloading"
  
  - name: "Basic functionality"
    script: "tests/compatibility/check-basic.php"
    description: "Test basic library functionality"
```

## Troubleshooting

### Configuration File Not Found

If `init` command fails to find the example configuration:

1. Check if the file exists:
   ```bash
   ls vendor/lukaszzychal/php-compatibility-tester/templates/config/.compatibility.yml.example
   ```

2. If not found, use the fixture example:
   ```bash
   cp vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml .compatibility.yml
   ```

3. Edit the copied file to match your package

### Tests Failing

Common issues:

1. **Framework installation fails**: Check `install_command` in configuration
2. **PHP version mismatch**: Verify `php_min_version` matches framework requirements
3. **Autoload errors**: Ensure your package is properly configured in `composer.json`
4. **Missing dependencies**: Check that all required dependencies are in `composer.json`

### GitHub Actions Not Running

1. Check workflow file exists: `.github/workflows/compatibility-tests.yml`
2. Verify `github_actions.enabled: true` in `.compatibility.yml`
3. Check GitHub Actions tab for errors

## Example Configuration

See the example configuration used by `phpstan-fixer`:

- **Location**: `.compatibility.yml` in this repository
- **Reference**: `vendor/lukaszzychal/php-compatibility-tester/tests/fixtures/test-package/.compatibility.yml`

## Related Documentation

- [PHP Compatibility Tester GitHub](https://github.com/lukaszzychal/php-compatibility-tester)
- [PHP Compatibility Tester Packagist](https://packagist.org/packages/lukaszzychal/php-compatibility-tester)
- [README.md](../README.md) - Main project documentation

