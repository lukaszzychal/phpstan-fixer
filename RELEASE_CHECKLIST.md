# Release Checklist for v1.0.0

## Pre-Release Tasks

### Code Quality
- [x] All PHP files have author headers
- [x] All tests pass
- [x] No linter errors
- [x] Code follows PSR-12
- [x] Type hints complete
- [x] PHPDoc comments complete

### Documentation
- [x] README.md complete (EN)
- [x] README_PL.md complete (PL)
- [x] CHANGELOG.md created
- [x] CONTRIBUTING.md created
- [x] ROADMAP.md created
- [x] All documentation files reviewed

### Testing
- [x] All unit tests pass
- [x] Integration tests pass
- [x] Test coverage acceptable (~65%)
- [x] Fixtures available

### CI/CD
- [x] GitHub Actions workflows configured
- [x] Tests run on multiple PHP versions
- [x] Release workflow ready
- [x] Self-test workflow configured

### Package
- [x] composer.json complete
- [x] Version set to 1.0.0
- [x] Dependencies correct
- [x] .gitignore configured
- [x] .editorconfig added
- [x] License file present

### GitHub
- [x] Issue templates created
- [x] Workflows configured
- [x] Dependabot configured

## Release Steps

1. **Final verification**
   ```bash
   composer install
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   ```

2. **Commit all changes**
   ```bash
   git add .
   git commit -m "Prepare v1.0.0 release"
   ```

3. **Create and push tag**
   ```bash
   git tag -a v1.0.0 -m "Release version 1.0.0"
   git push origin main
   git push origin v1.0.0
   ```

4. **GitHub Release**
   - Go to GitHub Releases
   - Create release from tag v1.0.0
   - Copy release notes from CHANGELOG.md
   - Publish release

5. **Packagist**
   - Submit package: https://packagist.org/packages/submit
   - Repository: https://github.com/lukaszzychal/phpstan-fixer
   - Packagist will auto-update on future tags

6. **Post-release**
   - Announce on social media (optional)
   - Monitor for issues
   - Gather user feedback

## Post-Release

- Monitor GitHub Issues
- Respond to feedback
- Plan v1.1.0 based on TODO.md

