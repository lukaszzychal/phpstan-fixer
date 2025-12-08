# PHPStan Error Logs

This directory contains collected PHPStan errors for analysis and potential fixer implementation.

## Purpose

Error logs are collected to:
1. Identify patterns in PHPStan errors
2. Determine which errors could be automatically fixed
3. Prioritize new fixer development
4. Improve existing fixers based on real-world error patterns

## File Naming Convention

- `phpstan-errors-YYYYMMDD-HHMMSS.json` - JSON format (for parsing)
- `phpstan-errors-YYYYMMDD-HHMMSS.txt` - Text format (for readability)

Example: `phpstan-errors-20251208-143022.json`

## How to Collect Errors

Run PHPStan and save output to this directory:

```bash
# JSON format
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
vendor/bin/phpstan analyse src tests --level=5 --memory-limit=512M --error-format=json > log-errors-phpstan/phpstan-errors-${TIMESTAMP}.json 2>&1

# Text format (GitHub format for readability)
vendor/bin/phpstan analyse src tests --level=5 --memory-limit=512M --error-format=github > log-errors-phpstan/phpstan-errors-${TIMESTAMP}.txt 2>&1
```

Or use the automated collection script (if created).

## Analyzing Errors

1. Review error patterns in text files
2. Identify common error types that could be fixed automatically
3. Document findings in TODO.md or create GitHub issues
4. Use error patterns to design new fixers

## Notes

- Error logs should be collected regularly (before/after major changes)
- Keep logs for historical analysis
- Clean up old logs periodically (keep last 10-20 most recent)

