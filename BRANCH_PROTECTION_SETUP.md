# Branch Protection Setup - Step by Step

## Quick Setup Link
**Direct link to branch protection settings:**
https://github.com/lukaszzychal/phpstan-fixer/settings/branches

## Recommended Configuration for Single Developer

### Step 1: Create Protection Rule
1. Click **"Add rule"** button
2. In "Branch name pattern", enter: `main`
3. Press Enter or click outside

### Step 2: Configure Protection Rules

#### ✅ Check These Options:

**1. Require a pull request before merging**
   - [x] ✅ Enable this checkbox
   - Set "Required number of approvals before merging": **0** (since you're solo)
   - [ ] Leave "Dismiss stale pull request approvals..." unchecked
   - [ ] Leave "Require review from Code Owners" unchecked

**2. Require status checks to pass before merging**
   - [x] ✅ Enable this checkbox
   - [x] ✅ Check "Require branches to be up to date before merging"
   - Under "Status checks that are required":
     - These will appear after first CI run, but you can add:
     - `test (PHP 8.0 on ubuntu-latest)`
     - `test (PHP 8.3 on ubuntu-latest)` ← at minimum
     - `Static Analysis (PHPStan)`
     - **Note**: Status check names appear after workflow runs. You may need to run CI once first, then come back and select them.

**3. Require conversation resolution before merging**
   - [x] ✅ Enable this checkbox

**4. Do not allow bypassing the above settings**
   - [x] ✅ Enable this checkbox (for safety)
   - ⚠️ Note: You can temporarily disable this if you need to bypass in emergency

#### ❌ Uncheck These (IMPORTANT!):

**5. Allow force pushes**
   - [ ] ❌ **UNCHECK THIS** - Prevents accidental `git push --force`

**6. Allow deletions**
   - [ ] ❌ **UNCHECK THIS** - Prevents accidental branch deletion

**7. Allow lock branch**
   - [x] ✅ You can leave this checked (allows temporary locks if needed)

### Step 3: Save
Click **"Create"** or **"Save changes"** button

## What This Gives You:

✅ **Protection:**
- Prevents accidental force push (`git push --force` will be blocked)
- Prevents accidental branch deletion
- Requires CI checks to pass before merging PRs
- Creates good workflow even for solo projects

✅ **Flexibility:**
- 0 approvals needed (you can merge your own PRs immediately)
- Can work on feature branches and merge via PR
- Can temporarily disable bypass if needed in emergency

⚠️ **If You Need to Force Push:**
1. Go to Settings → Branches
2. Edit the `main` rule
3. Temporarily uncheck "Do not allow bypassing"
4. Do your force push
5. Re-enable "Do not allow bypassing"

## Status Checks Setup (After First CI Run)

After your first CI workflow runs on a PR, you'll see status check names. Then:

1. Go back to Settings → Branches
2. Edit the `main` rule
3. Scroll to "Require status checks to pass before merging"
4. You'll see a list of available checks - select at minimum:
   - `test (PHP 8.3 on ubuntu-latest)` ← recommended minimum
   - `Static Analysis (PHPStan)` ← highly recommended

**Or select all PHP versions** for maximum protection (slower but safer):
   - `test (PHP 8.0 on ubuntu-latest)`
   - `test (PHP 8.1 on ubuntu-latest)`
   - `test (PHP 8.2 on ubuntu-latest)`
   - `test (PHP 8.3 on ubuntu-latest)`
   - `test (PHP 8.4 on ubuntu-latest)`
   - `test (PHP 8.5 on ubuntu-latest)`
   - `Static Analysis (PHPStan)`

## Minimal Setup (If You Want Less Protection)

If you want only basic protection without PR requirements:

**Enable only:**
- [x] Allow force pushes → **UNCHECK**
- [x] Allow deletions → **UNCHECK**
- Everything else → **UNCHECK**

This gives:
- ✅ Protection against accidental force push
- ✅ Protection against accidental deletion
- ❌ No CI checks required (you can skip if needed)

