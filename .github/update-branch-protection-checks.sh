#!/bin/bash

# Script to update branch protection with required status checks
# Run this after first CI workflow completes to add status checks

REPO="lukaszzychal/phpstan-fixer"
BRANCH="main"

echo "Updating branch protection for $BRANCH with status checks..."

# Update branch protection to require specific status checks
# Adjust these check names based on what appears in your CI workflow
gh api repos/$REPO/branches/$BRANCH/protection/required_status_checks \
  --method PATCH \
  --field strict=true \
  --field contexts[]='test (PHP 8.3 on ubuntu-latest)' \
  --field contexts[]='Static Analysis (PHPStan)'

echo "✅ Branch protection updated!"
echo ""
echo "To verify, check: https://github.com/$REPO/settings/branches"
echo ""
echo "To add more status checks, edit this script and add contexts[] entries."

