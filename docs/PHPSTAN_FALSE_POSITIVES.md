# PHPStan False Positives - Solutions

> 🇵🇱 **Polish version**: [PHPStan False Positives - Rozwiązania (PL)](PHPSTAN_FALSE_POSITIVES_PL.md) *(coming soon)*

This document describes strategies for handling PHPStan false positives in the project.

## Current Approach

### 1. PHPStan Configuration (`phpstan.neon`)

We use `ignoreErrors` in `phpstan.neon` for project-wide false positives:

```neon
parameters:
    ignoreErrors:
        - '#Call to method.*assertIsArray\(\) with array.*will always evaluate to true#'
        - '#Call to method.*assertIsBool\(\) with bool will always evaluate to true#'
        - '#Unreachable statement - code above always terminates#'
    reportUnmatchedIgnoredErrors: false
```

### 2. Inline Annotations

For file-specific false positives, we use `@phpstan-ignore-next-line`:

```php
// @phpstan-ignore-next-line - false positive: $typeNode is a union type, instanceof check is valid
if ($typeNode instanceof \PhpParser\Node\UnionType) {
    return implode('|', array_map([$this, 'formatType'], $typeNode->types));
}
```

## Recommended Solutions

### Solution 1: PHPStan Baseline (Recommended for Known False Positives)

**What it is:** A baseline file that records all current PHPStan errors. New errors (not in baseline) will still be reported, but existing errors are ignored.

**Advantages:**
- ✅ Clean way to handle existing false positives
- ✅ Only new errors are reported
- ✅ Automatically maintained
- ✅ Works well in CI/CD

**How to use:**

1. Generate baseline (one-time):
```bash
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

This creates `phpstan-baseline.neon` with all current errors.

2. Update `phpstan.neon` to include baseline:
```neon
parameters:
    level: 5
    baseline: phpstan-baseline.neon
```

3. When fixing errors, regenerate baseline:
```bash
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

**When to use:**
- When you have many existing false positives
- When you want to focus on new errors only
- When false positives are hard to suppress with regex

### Solution 2: Improved ignoreErrors Configuration

**What it is:** Better regex patterns in `phpstan.neon` to catch common false positive patterns.

**Advantages:**
- ✅ No additional files needed
- ✅ Easy to maintain
- ✅ Good for common patterns

**Current patterns we use:**
```neon
ignoreErrors:
    # PHPUnit assertion false positives
    - '#Call to method.*assertIs(Array|Bool|String)\(\) with (array|bool|string).*will always evaluate to true#'
    
    # Test skipping false positives
    - '#Unreachable statement - code above always terminates#'
```

**Improved pattern (more specific):**
```neon
ignoreErrors:
    # PHPUnit assertion false positives (more specific)
    - '#Call to method PHPUnit\\\\Framework\\\\Assert::assertIs(Array|Bool|String|Int|Float)\(\) with (array|bool|string|int|float) will always evaluate to true#'
    
    # Test skipping with conditional logic
    - '#Unreachable statement - code above always terminates#'
        paths:
            - tests
    
    # Reflection false positives (if applicable)
    - '#Call to method Reflection.*::.*\(\) may not exist#'
```

**When to use:**
- For common patterns across the codebase
- When the pattern is specific enough to not hide real errors
- For test-specific false positives

### Solution 3: Combination Approach (Current + Baseline)

**Best practice:** Use both baseline and ignoreErrors:

1. **ignoreErrors** - For project-wide known patterns (PHPUnit, common false positives)
2. **Baseline** - For one-off errors that are false positives but don't fit a pattern
3. **@phpstan-ignore-next-line** - For specific inline cases that need explanation

**Configuration example:**
```neon
parameters:
    level: 5
    baseline: phpstan-baseline.neon
    ignoreErrors:
        # Common patterns (project-wide)
        - '#Call to method.*assertIs(Array|Bool|String)\(\) with (array|bool|string).*will always evaluate to true#'
        - '#Unreachable statement - code above always terminates#'
            paths:
                - tests
    reportUnmatchedIgnoredErrors: false
```

## Implementation Recommendations

### Immediate Actions

1. **Create baseline file** (if not exists):
```bash
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

2. **Update phpstan.neon** to use baseline:
```neon
parameters:
    baseline: phpstan-baseline.neon
```

3. **Improve ignoreErrors patterns** for common false positives (see examples above)

### Long-term Strategy

1. **Regular baseline updates:**
   - Regenerate baseline when fixing legitimate errors
   - Review baseline entries periodically to see if they can be fixed
   - Document why each baseline entry exists

2. **Error collection:**
   - Continue collecting errors in `log-errors-phpstan/`
   - Analyze patterns to identify new fixer opportunities
   - Move from baseline to fixers when possible

3. **Documentation:**
   - Document common false positive patterns
   - Keep `phpstan-errors-analysis.md` updated
   - Add examples of proper ignore usage

## Examples

### Example 1: Test Skip False Positive

**Problem:**
```php
public function testSomething(): void
{
    if (!extension_loaded('yaml')) {
        $this->markTestSkipped('YAML extension required');
        return; // PHPStan: Unreachable statement
    }
    
    // Test code
}
```

**Solutions:**

Option A - Inline ignore:
```php
if (!extension_loaded('yaml')) {
    $this->markTestSkipped('YAML extension required');
    /** @phpstan-ignore-next-line */
    return;
}
```

Option B - Baseline (recommended for multiple occurrences):
```bash
# Generate baseline to include all such cases
vendor/bin/phpstan analyse src tests --level=5 --generate-baseline
```

Option C - ignoreErrors pattern:
```neon
ignoreErrors:
    - '#Unreachable statement - code above always terminates#'
        paths:
            - tests
```

### Example 2: Union Type Instanceof Check

**Problem:**
```php
// PHPStan: Instanceof between UnionType and UnionType will always evaluate to true
if ($typeNode instanceof \PhpParser\Node\UnionType) {
    // ...
}
```

**Solution - Inline ignore with explanation:**
```php
// @phpstan-ignore-next-line - false positive: $typeNode is a union type, instanceof check is valid
if ($typeNode instanceof \PhpParser\Node\UnionType) {
    // ...
}
```

### Example 3: Reflection False Positive

**Problem:**
```php
$reflection = new \ReflectionClass($className);
$method = $reflection->getMethod('someMethod'); // PHPStan: Method may not exist
```

**Solution - Inline ignore:**
```php
$reflection = new \ReflectionClass($className);
/** @phpstan-ignore-next-line - Method existence checked elsewhere */
$method = $reflection->getMethod('someMethod');
```

## Decision Tree

```
Is the false positive:
├─ A common pattern across many files?
│  └─ Use ignoreErrors in phpstan.neon
│
├─ A one-off error in a specific file?
│  └─ Use @phpstan-ignore-next-line with explanation
│
├─ Multiple errors in multiple files?
│  └─ Use baseline file
│
└─ Can it be fixed in code?
   └─ Fix it! (Don't suppress)
```

## Related Documentation

- [PHPStan Baseline Documentation](https://phpstan.org/user-guide/baseline)
- [PHPStan Ignoring Errors](https://phpstan.org/user-guide/ignoring-errors)
- [log-errors-phpstan/phpstan-errors-analysis.md](../log-errors-phpstan/phpstan-errors-analysis.md) - Current error analysis

