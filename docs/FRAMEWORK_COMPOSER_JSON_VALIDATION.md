# Framework composer.json Validation

> 🇵🇱 **Polish version**: [Walidacja composer.json dla Frameworków (PL)](FRAMEWORK_COMPOSER_JSON_VALIDATION_PL.md)

This document describes framework-specific `composer.json` configurations that require validation to prevent runtime errors.

## Problem Summary

Some frameworks expect specific data types in `composer.json` `extra` section. Using wrong types (e.g., boolean instead of array) can cause fatal runtime errors.

## Framework-Specific Configurations

### Laravel

**Configuration**: `extra.laravel.dont-discover`

**Expected Type**: `array` (empty array `[]` or array with package names)

**Incorrect**:
```json
{
    "extra": {
        "laravel": {
            "dont-discover": true
        }
    }
}
```

**Correct**:
```json
{
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    }
}
```

**Error if incorrect**:
```
array_merge(): Argument #2 must be of type array, true given
at vendor/laravel/framework/src/Illuminate/Foundation/PackageManifest.php:135
```

**Reference**: [Laravel Package Discovery](https://laravel.com/docs/packages#package-discovery)

---

### Symfony Flex

**Configuration**: `extra.symfony.dont-discover`

**Expected Type**: `array` (array with package names)

**Correct**:
```json
{
    "extra": {
        "symfony": {
            "dont-discover": ["vendor/package-name"]
        }
    }
}
```

**Note**: Symfony Flex uses this to prevent automatic recipe discovery for specific packages.

---

### CodeIgniter 4

**Configuration**: Not in `composer.json`

CodeIgniter 4 does **not** use `composer.json` `extra` section for package discovery. Instead, it uses:
- `app/Config/Modules.php` with `$composerPackages['only']` and `$composerPackages['exclude']` arrays

**No validation needed** for `composer.json` in this case.

---

### CakePHP

**Configuration**: `extra.installer-paths`

**Expected Type**: `object` (key-value pairs mapping paths to package arrays)

**Correct**:
```json
{
    "extra": {
        "installer-paths": {
            "app/Plugin/DebugKit": ["cakephp/debug_kit"]
        }
    }
}
```

**Note**: This is for custom installer paths, not package discovery. Type validation is less critical but should still be an object.

---

### Laminas (Zend Framework)

**Configuration**: `extra.laminas.*`

**Expected Types**: 
- `component`: `string` or `array<string>`
- `module`: `string` or `array<string>`
- `config-provider`: `string` or `array<string>`
- `component-whitelist`: `array<string>`

**Correct**:
```json
{
    "extra": {
        "laminas": {
            "component": "Your\\Component\\Namespace",
            "module": ["Your\\Module\\Namespace"],
            "config-provider": "Your\\ConfigProvider\\Class",
            "component-whitelist": ["laminas/laminas-component1"]
        }
    }
}
```

**Note**: All values are strings or arrays of strings. No boolean values expected.

---

### Yii 3

**Configuration**: `extra.config-plugin` and `extra.config-plugin-options`

**Expected Types**: 
- `config-plugin`: `object` (configuration group mappings)
- `config-plugin-options`: `object` (options like `source-directory`, `package-types`)

**Correct**:
```json
{
    "extra": {
        "config-plugin": {
            "common": "config/common/*.php",
            "web": ["$common", "config/web/*.php"]
        },
        "config-plugin-options": {
            "source-directory": "custom-config",
            "package-types": ["library", "composer-plugin"]
        }
    }
}
```

**Note**: All values are objects/arrays. No boolean values expected.

---

## Validation Rules Summary

| Framework | Configuration | Expected Type | Can Cause Fatal Error? |
|-----------|--------------|---------------|------------------------|
| Laravel | `extra.laravel.dont-discover` | `array` | ✅ Yes |
| Symfony | `extra.symfony.dont-discover` | `array` | ⚠️ Possible |
| CodeIgniter | N/A | N/A | ❌ No |
| CakePHP | `extra.installer-paths` | `object` | ⚠️ Possible |
| Laminas | `extra.laminas.*` | `string` or `array<string>` | ⚠️ Possible |
| Yii | `extra.config-plugin*` | `object` | ⚠️ Possible |

## Recommendations

1. **Always use arrays** for `dont-discover` configurations (Laravel, Symfony)
2. **Validate types** before publishing packages
3. **Test with real framework instances** when possible
4. **Use compatibility testing tools** that validate `composer.json` structure

## Related Issues

- [phpstan-fixer #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [phpstan-fixer #63](https://github.com/lukaszzychal/phpstan-fixer/issues/63) - dont-discover should be array, not boolean
- [php-compatibility-tester #16](https://github.com/lukaszzychal/php-compatibility-tester/issues/16) - Add composer.json validation for framework-specific configs

## Tools

- [PHP Compatibility Tester](https://github.com/lukaszzychal/php-compatibility-tester) - Should validate composer.json (feature request)
- Manual validation scripts can check types before release
