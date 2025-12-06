# Per-Error Configuration Feature

## Overview

Allow users to configure how each PHPStan error type should be handled by the tool. This provides fine-grained control over which errors are fixed, ignored, or simply reported.

## Use Cases

1. **Selective Fixing** - Fix only certain types of errors, ignore others
2. **Silent Ignoring** - Suppress specific error types from output entirely
3. **Reporting Only** - View certain errors without attempting fixes (e.g., complex errors that need manual review)

## Configuration Options

For each error type, users can specify one of three actions:

### 1. `fix` (default)
- Attempts to automatically fix the error
- Current behavior for all errors
- If fix fails, error is reported in unfixed errors section

### 2. `ignore`
- Completely ignores the error
- Does not attempt to fix
- Does not display in output
- Useful for errors that are known/accepted or cannot be fixed automatically

### 3. `report`
- Does not attempt to fix
- Displays error in original PHPStan format in output
- Useful for complex errors that need manual review or errors that are too risky to auto-fix

## Configuration File Format

### YAML Format (`phpstan-fixer.yaml`)

```yaml
# PHPStan Fixer Configuration
version: 1

# Per-error type configuration
rules:
  # Pattern matching error messages
  "Access to an undefined property":
    action: "fix"  # fix, ignore, or report
  
  "Method has no return type specified":
    action: "fix"
  
  "Parameter #\d+ \$[\w]+ has no type specified":
    action: "fix"
  
  "Unknown class":
    action: "ignore"  # Skip fixing and don't show
  
  "Extra arguments passed":
    action: "report"  # Don't fix, but show in output
  
  # Wildcard patterns
  "Call to an undefined method.*":
    action: "fix"
  
  ".*magic.*":
    action: "report"  # All magic-related errors

# Default behavior for unmatched errors
default:
  action: "fix"  # or "ignore" or "report"
```

### JSON Format (`phpstan-fixer.json`)

```json
{
  "version": 1,
  "rules": {
    "Access to an undefined property": {
      "action": "fix"
    },
    "Method has no return type specified": {
      "action": "fix"
    },
    "Unknown class": {
      "action": "ignore"
    },
    "Extra arguments passed": {
      "action": "report"
    }
  },
  "default": {
    "action": "fix"
  }
}
```

## CLI Usage

### Specify Configuration File
```bash
vendor/bin/phpstan-fixer --config=phpstan-fixer.yaml
```

### Inline Configuration (Future)
```bash
vendor/bin/phpstan-fixer \
  --rule="Access to undefined property:ignore" \
  --rule="Method has no return type:fix"
```

## Implementation Plan

### Phase 1: Configuration Loading
- [ ] Create `Configuration` value object
- [ ] Implement YAML parser (Symfony YAML component)
- [ ] Implement JSON parser
- [ ] Configuration file discovery (current dir, project root)
- [ ] Validate configuration schema

### Phase 2: Rule Matching
- [ ] Create `RuleMatcher` class
- [ ] Pattern matching (exact match, regex, wildcards)
- [ ] Priority handling (specific rules override general ones)
- [ ] Default rule application

### Phase 3: Integration with AutoFixService
- [ ] Update `AutoFixService` to accept configuration
- [ ] Check rule before processing each issue
- [ ] Implement "ignore" action (skip issue)
- [ ] Implement "report" action (pass to unfixed issues)
- [ ] Preserve "fix" action (current behavior)

### Phase 4: Command Integration
- [ ] Add `--config` option to CLI command
- [ ] Load configuration on command start
- [ ] Pass configuration to AutoFixService
- [ ] Update output to reflect ignored/reported errors

### Phase 5: Documentation & Testing
- [ ] Update README with configuration examples
- [ ] Create configuration documentation
- [ ] Add unit tests for configuration parsing
- [ ] Add integration tests for all three actions
- [ ] Add examples in documentation

## Technical Considerations

### Pattern Matching
- Support exact string matching
- Support regex patterns (PCRE)
- Support wildcard patterns (simplified regex)
- Pattern priority: exact > regex > wildcard

### Performance
- Cache compiled regex patterns
- Pre-compile rules on configuration load
- Minimize pattern matching overhead

### Configuration Validation
- Validate action values (only: fix, ignore, report)
- Validate pattern syntax
- Provide helpful error messages for invalid config

### Backward Compatibility
- Default behavior remains "fix" for all errors
- If no config file exists, use default behavior
- Config file is optional

## Example Configuration Files

### Conservative Configuration
Only fix safe, simple errors:
```yaml
rules:
  "Method has no return type": { action: "fix" }
  "Parameter.*has no type": { action: "fix" }
  ".*": { action: "report" }  # Report all others
```

### Aggressive Configuration
Try to fix everything:
```yaml
rules:
  ".*": { action: "fix" }  # Fix all errors
default:
  action: "fix"
```

### Minimal Configuration
Ignore most, only fix critical:
```yaml
rules:
  "Unknown class": { action: "ignore" }
  "Access to undefined property": { action: "fix" }
  ".*": { action: "report" }
```

## Future Enhancements

1. **Per-file configuration** - Different rules for different files/directories
2. **Per-fixer configuration** - Enable/disable specific fixers
3. **Configuration inheritance** - Extend base configuration
4. **Environment-specific configs** - Different rules for dev/prod
5. **Configuration presets** - Pre-made configs for common scenarios

## Related Issues

- See TODO.md for implementation tasks
- See ROADMAP.md for version planning

