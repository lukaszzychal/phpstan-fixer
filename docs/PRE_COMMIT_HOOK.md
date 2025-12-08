# Pre-commit Hook

This repository includes a Git pre-commit hook that automatically runs code quality checks before allowing commits.

## What It Does

The pre-commit hook runs three checks in sequence:

1. **phpstan-fixer** (suggest mode)
   - Checks for fixable PHPStan issues
   - Blocks commit if fixable issues are found
   - Suggests running `vendor/bin/phpstan-fixer --mode=apply` to fix them

2. **PHPStan** (static analysis)
   - Analyzes code for type errors and other issues
   - Blocks commit if errors are found
   - Uses level 5 analysis

3. **PHPUnit** (tests)
   - Runs all unit and integration tests
   - Blocks commit if any test fails

## Installation

The hook is automatically installed when you clone the repository. If you need to install it manually:

```bash
chmod +x .git/hooks/pre-commit
```

## Usage

The hook runs automatically on every `git commit`. You don't need to do anything special.

### Example Output

```
🔍 Running pre-commit checks...
📋 Step 1/3: Running phpstan-fixer (suggest mode)...
✅ phpstan-fixer: No issues found
📋 Step 2/3: Running PHPStan static analysis...
✅ PHPStan: No errors found
📋 Step 3/3: Running PHPUnit tests...
✅ PHPUnit: All tests passed
✅ All pre-commit checks passed!
```

### If Checks Fail

If any check fails, the commit is blocked:

```
❌ phpstan-fixer found issues that could be fixed!
Run 'vendor/bin/phpstan-fixer --mode=apply' to apply fixes...
```

Fix the issues and try committing again.

## Bypassing the Hook (Not Recommended)

If you absolutely must bypass the hook (e.g., for WIP commits), use:

```bash
git commit --no-verify
```

**Warning**: Only bypass the hook for legitimate reasons. The checks exist to maintain code quality.

## Troubleshooting

### Hook not running?

Make sure the file is executable:
```bash
chmod +x .git/hooks/pre-commit
```

### Dependencies missing?

The hook will automatically run `composer install` if `vendor/bin` doesn't exist.

### Hook fails but code looks fine?

1. Run each check manually:
   ```bash
   vendor/bin/phpstan-fixer --mode=suggest
   vendor/bin/phpstan analyse src tests --level=5
   vendor/bin/phpunit
   ```

2. Check the error messages carefully
3. Fix the issues before committing

## Customization

To modify the hook, edit `.git/hooks/pre-commit`. Changes will only affect your local repository.

## Related Documentation

- [PHPStan Configuration](../phpstan.neon)
- [PHPUnit Configuration](../phpunit.xml)
- [README](../README.md)

