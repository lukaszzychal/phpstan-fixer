# Issue: Compatibility with Symfony Console 7.x - Fatal Error and Deprecation Warnings

## Summary

The package `lukaszzychal/php-compatibility-tester` v1.0.1 has compatibility issues with Symfony Console 7.x that cause fatal errors when running commands. Additionally, there are deprecation warnings related to nullable parameter syntax.

## Problem Description

### Fatal Error: Command Name Not Set

**Error Message:**
```
Fatal error: Uncaught Symfony\Component\Console\Exception\LogicException: 
The command defined in "LukaszZychal\PhpCompatibilityTester\Command\InitCommand" 
cannot have an empty name.
```

**Root Cause:**
In Symfony Console 7.x, the `protected static $defaultName` property is not automatically recognized when commands are instantiated before being added to the Application. The command name must be explicitly set using `setName()` in the `configure()` method.

**Affected Commands:**
- `InitCommand`
- `TestCommand`
- `ReportCommand`

### Deprecation Warning

**Warning Message:**
```
Deprecated: LukaszZychal\PhpCompatibilityTester\Command\InitCommand::__construct(): 
Implicitly marking parameter $packagePath as nullable is deprecated, 
the explicit nullable type must be used instead
```

**Root Cause:**
PHP 8.4+ (and strict deprecation warnings in PHP 8.1+) require explicit nullable type syntax (`?string`) instead of implicit nullable with default `null` (`string $param = null`).

**Location:**
- `src/Command/InitCommand.php:22`

### Additional Issue: Option Name Conflict

**Error Message:**
```
An option named "version" already exists.
```

**Root Cause:**
The `TestCommand` defines an option `--version` which conflicts with Symfony Console's built-in `--version` option for the Application.

**Location:**
- `src/Command/TestCommand.php:29`

## Environment

- **Package Version:** v1.0.1
- **Symfony Console:** 7.4.0 (but issue affects all 7.x versions)
- **PHP Version:** 8.4.7 (deprecation warnings also appear in 8.1+)
- **OS:** macOS / Linux

## Reproduction Steps

1. Install the package:
   ```bash
   composer require --dev lukaszzychal/php-compatibility-tester
   ```

2. Run any command:
   ```bash
   vendor/bin/compatibility-tester --help
   # or
   vendor/bin/compatibility-tester init
   # or
   vendor/bin/compatibility-tester test --help
   ```

3. **Expected:** Command executes successfully
4. **Actual:** Fatal error occurs

## Proposed Solution

### Fix 1: Explicit Command Name Setting

Add `setName()` calls in `configure()` method for all commands:

**File: `src/Command/InitCommand.php`**
```php
protected function configure(): void
{
    $this->setName('init');  // Add this line
    $this->setDescription('Initialize compatibility testing configuration and templates');
}
```

**File: `src/Command/TestCommand.php`**
```php
protected function configure(): void
{
    $this
        ->setName('test')  // Add this line
        ->setDescription('Run compatibility tests')
        ->addOption('framework', 'f', InputOption::VALUE_REQUIRED, 'Filter by framework name')
        // ... rest of options
}
```

**File: `src/Command/ReportCommand.php`**
```php
protected function configure(): void
{
    $this
        ->setName('report')  // Add this line
        ->setDescription('Generate compatibility test report')
        // ... rest of options
}
```

### Fix 2: Explicit Nullable Parameter Syntax

**File: `src/Command/InitCommand.php`**
```php
// Change from:
public function __construct(string $packagePath = null)

// To:
public function __construct(?string $packagePath = null)
```

### Fix 3: Rename Conflicting Option (Optional Enhancement)

**File: `src/Command/TestCommand.php`**
```php
// Change from:
->addOption('version', null, InputOption::VALUE_REQUIRED, 'Filter by framework version')

// To:
->addOption('framework-version', null, InputOption::VALUE_REQUIRED, 'Filter by framework version')
```

**Note:** This is a breaking change. Consider:
- Deprecating `--version` and adding `--framework-version`
- Or keeping `--version` but documenting the conflict with `--version` (application version)

## Compatibility

- ✅ **Backward Compatible:** Yes (Fix 1 and Fix 2 are internal fixes, no API changes)
- ⚠️ **Breaking Change:** Fix 3 (option rename) would be a breaking change if implemented
- ✅ **PHP Version Support:** Maintains support for PHP 8.1+
- ✅ **Symfony Console:** Compatible with both 6.x and 7.x

## Testing

After applying fixes, verify:
1. `vendor/bin/compatibility-tester --help` works
2. `vendor/bin/compatibility-tester list` shows all commands
3. `vendor/bin/compatibility-tester init` executes without errors
4. `vendor/bin/compatibility-tester test --help` works
5. `vendor/bin/compatibility-tester report --help` works
6. No deprecation warnings appear (PHP 8.1+)

## Additional Notes

- The `protected static $defaultName` property can remain for Symfony Console 6.x compatibility, but `setName()` is required for 7.x
- This is a known Symfony Console 7.x change: commands must explicitly set their name in `configure()` method
- All fixes are minimal and maintain full backward compatibility

## Priority

- **Severity:** High (package is unusable with Symfony Console 7.x)
- **Impact:** All users installing with Symfony Console 7.x will experience fatal errors
- **Fix Complexity:** Low (simple code changes, well-documented)

---

**Tested Fixes:**
- ✅ Applied all three fixes locally
- ✅ Verified all commands work correctly
- ✅ No breaking changes for existing functionality
- ✅ Compatible with Symfony Console 7.4.0 and PHP 8.4.7

