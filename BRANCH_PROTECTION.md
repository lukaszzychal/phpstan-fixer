# Branch Protection for Main Branch

## Recommended Settings for Single Developer

Since you're the only person working on this repository, here are the recommended settings that protect against accidents while not blocking your normal workflow.

## Step-by-Step Instructions

1. **Go to Repository Settings:**
   - Navigate to: https://github.com/lukaszzychal/phpstan-fixer
   - Click "⚙️ Settings" (top right)

2. **Navigate to Branch Protection:**
   - Click "Branches" in the left sidebar
   - Click "Add rule" or "Add branch protection rule"

3. **Configure Branch Name Pattern:**
   - Branch name pattern: `main`

4. **Recommended Settings (Single Developer):**

   ✅ **Enable these:**
   
   - [x] **Require a pull request before merging**
     - [x] Require approvals: **0** (since you're solo)
     - [ ] Dismiss stale pull request approvals when new commits are pushed
     - [ ] Require review from Code Owners
   
   - [x] **Require status checks to pass before merging**
     - [x] Require branches to be up to date before merging
     - [ ] Status checks found in the last week (select your CI checks):
       - CI / PHP 8.0
       - CI / PHP 8.1
       - CI / PHP 8.2
       - CI / PHP 8.3
       - CI / PHP 8.4
       - CI / PHP 8.5
       - CI / PHPStan
   
   - [x] **Require conversation resolution before merging**
   
   - [x] **Do not allow bypassing the above settings**
     - ⚠️ Note: Since you're solo, you might want to UNCHECK this so you can bypass if needed
     - **Recommendation**: Keep it checked for safety, but you can bypass via GitHub CLI if needed
   
   - [x] **Restrict who can push to matching branches**
     - Leave empty (allows you to push normally)
   
   - [x] **Allow force pushes** → **UNCHECK THIS** (important!)
     - This prevents accidental `git push --force`
   
   - [x] **Allow deletions** → **UNCHECK THIS** (important!)
     - This prevents accidental branch deletion
   
   - [x] **Allow lock branch**
     - Keep this for temporary locks if needed

5. **Click "Create" or "Save changes"**

## Alternative: Less Restrictive (Maximum Flexibility)

If you want maximum flexibility while still having basic protection:

✅ **Minimal Protection:**
- [x] Allow force pushes → **UNCHECK**
- [x] Allow deletions → **UNCHECK**
- [ ] Everything else → **UNCHECK**

This gives you:
- ✅ Protection against accidental force push
- ✅ Protection against accidental deletion
- ✅ No workflow blocking

## Force Push Workaround (If Needed)

If you need to force push in emergency (and bypass is disabled):

**Option 1: Use GitHub CLI**
```bash
gh api repos/lukaszzychal/phpstan-fixer/branches/main/protection --method DELETE
git push --force origin main
gh api repos/lukaszzychal/phpstan-fixer/branches/main/protection --method PUT -f required_status_checks=null -f enforce_admins=false -f restrictions=null
```

**Option 2: Temporarily disable protection via GitHub UI**
- Settings → Branches → Edit rule → Temporarily uncheck "Do not allow bypassing"

## Recommended: Balanced Approach

For a single developer, I recommend this **balanced configuration**:

### ✅ Enable:
- [x] Require a pull request before merging (with 0 approvals)
- [x] Require status checks to pass before merging
- [x] Require conversation resolution before merging
- [x] Do not allow bypassing the above settings
- [ ] Allow force pushes → **UNCHECK** ⚠️
- [ ] Allow deletions → **UNCHECK** ⚠️

### Why this setup?
- ✅ Prevents accidental force push (you can still push normally)
- ✅ Prevents accidental branch deletion
- ✅ Runs CI checks before merge (catches errors early)
- ✅ Allows you to merge your own PRs (0 approvals)
- ✅ Still requires PR workflow (good practice even solo)
- ⚠️ Bypass disabled (but you can temporarily enable if needed)

This gives you protection without blocking your normal workflow!

