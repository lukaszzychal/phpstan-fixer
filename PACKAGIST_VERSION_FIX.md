# Packagist Version Mismatch - Solution

## Problem
Packagist shows: "Some tags were ignored because of a version mismatch in composer.json"

## Root Cause
The `composer.json` file had a hardcoded `"version": "1.0.0"` field. Packagist automatically detects version from Git tags (e.g., `v1.0.0` → `1.0.0`, `v1.0.1` → `1.0.1`), and having a hardcoded version field can cause conflicts.

## Solution Applied
Removed the `"version"` field from `composer.json`. This is the recommended practice for Packagist packages - let Packagist auto-detect version from Git tags.

## What Changed
```diff
-     "version": "1.0.0",
```

## Next Steps

1. **Commit and push the change:**
   ```bash
   git add composer.json
   git commit -m "fix: Remove version field from composer.json (let Packagist auto-detect from tags)"
   git push origin main
   ```

2. **Packagist will auto-update:**
   - Packagist checks for updates periodically (every few minutes)
   - The tags should now be recognized correctly
   - Check: https://packagist.org/packages/lukaszzychal/phpstan-fixer

3. **If tags still don't appear:**
   - Wait 5-10 minutes
   - Manually trigger update on Packagist (Update button)
   - Check Packagist logs for any errors

## Why This Works
- Packagist reads version from Git tag names (e.g., `v1.0.0`, `v1.0.1`)
- Without `version` field in composer.json, there's no conflict
- Tag `v1.0.0` → Package version `1.0.0`
- Tag `v1.0.1` → Package version `1.0.1`

## Best Practice
**Never include `version` field in composer.json** for Packagist packages. Let Packagist detect it automatically from Git tags.

