---
name: New Fixer Request
about: Request a new fixer strategy
title: '[FIXER] '
labels: enhancement, fixer
assignees: ''
---

## PHPStan Error
Describe the PHPStan error message this fixer should handle.

**Example:**
```
Access to an undefined property $foo
```

## Current Behavior
What happens now when this error is encountered?

## Desired Fix
Describe what PHPDoc/code change should be made.

**Example:**
```php
// Before:
class Test {
    public function test() {
        return $this->foo;
    }
}

// After:
/**
 * @property mixed $foo
 */
class Test {
    public function test() {
        return $this->foo;
    }
}
```

## PHPStan Level
What PHPStan level detects this error? (0-8)

## Priority
- [ ] High (frequently encountered)
- [ ] Medium (sometimes encountered)
- [ ] Low (rarely encountered)

## Additional Context
Any other information that might be helpful.

