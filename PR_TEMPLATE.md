# Per-Error Configuration System Documentation

## Summary
This PR adds comprehensive documentation for the per-error configuration system feature, allowing users to control how each PHPStan error type is handled (fix, ignore, or report).

## Changes
- ✨ Added `CONFIGURATION_FEATURE.md` - Complete feature specification
- 📝 Updated `TODO.md` - Added configuration system implementation tasks
- 🗺️ Updated `ROADMAP.md` - Added as high priority for v1.1.0
- 💡 Updated `FUTURE_IDEAS.md` - Added configuration feature
- 📊 Updated `PROJECT_STATUS.md` - Added to post-release priorities

## Feature Overview

### Three Configuration Actions
1. **`fix`** (default) - Attempts to automatically fix the error
2. **`ignore`** - Silently ignores the error (doesn't fix, doesn't display)
3. **`report`** - Shows error in original PHPStan format without fixing

### Example Configuration
```yaml
rules:
  "Access to an undefined property":
    action: "fix"
  
  "Unknown class":
    action: "ignore"
  
  "Extra arguments passed":
    action: "report"
```

## Related Documentation
- See `CONFIGURATION_FEATURE.md` for complete specification
- Implementation plan included in TODO.md
- Planned for v1.1.0 release (ROADMAP.md)

## Testing
- [ ] Documentation reviewed
- [ ] Examples validated
- [ ] Formatting checked

## Checklist
- [x] Documentation added
- [x] Examples provided
- [x] Implementation plan outlined
- [ ] Feature implementation (future PR)

