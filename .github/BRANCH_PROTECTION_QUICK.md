# Quick Branch Protection Setup

## Single Developer - Recommended Settings

**Direct Link:** https://github.com/lukaszzychal/phpstan-fixer/settings/branches

### Configuration:

**Branch name pattern:** `main`

✅ **Check these:**
- [x] Require a pull request before merging
  - Require approvals: **0**
- [x] Require status checks to pass before merging
  - Require branches to be up to date before merging
- [x] Require conversation resolution before merging
- [x] Do not allow bypassing the above settings

❌ **Uncheck these:**
- [ ] Allow force pushes ⚠️ **IMPORTANT - Uncheck to prevent accidents**
- [ ] Allow deletions ⚠️ **IMPORTANT - Uncheck to prevent accidents**

### Why?
- Protects against accidental `git push --force`
- Protects against accidental branch deletion
- Runs CI checks automatically
- Still allows you to work normally (0 approvals needed)
- Creates good PR workflow even for solo projects

### If You Need to Force Push:
You can temporarily disable protection via GitHub UI (Settings → Branches → Edit → Uncheck "Do not allow bypassing")

