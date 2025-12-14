#!/bin/bash
# Script to apply FileValidationTrait to multiple fixers

FIXERS=(
    "CollectionGenericDocblockFixer"
    "ImmutableClassFixer"
    "ImpureFunctionFixer"
    "InternalAnnotationFixer"
    "IterableValueTypeFixer"
    "MagicPropertyFixer"
    "MissingPropertyDocblockFixer"
    "MissingThrowsDocblockFixer"
    "MissingUseStatementFixer"
    "MixinFixer"
    "PrefixedTagsFixer"
    "ReadonlyPropertyFixer"
    "RequireExtendsFixer"
    "RequireImplementsFixer"
    "SealedClassFixer"
    "UndefinedMethodFixer"
    "UndefinedPivotPropertyFixer"
)

echo "This is a reference list. Apply manually using search_replace."

