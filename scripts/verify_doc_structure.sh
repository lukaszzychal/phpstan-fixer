#!/bin/bash

# Script to verify structural consistency between English and Polish documentation versions
# Usage: ./scripts/verify_doc_structure.sh <EN_DOCUMENT.md>

set -e

EN_FILE="$1"

if [ -z "$EN_FILE" ]; then
    echo "Usage: $0 <EN_DOCUMENT.md>"
    echo "Example: $0 docs/README.md"
    exit 1
fi

if [ ! -f "$EN_FILE" ]; then
    echo "❌ Error: File not found: $EN_FILE"
    exit 1
fi

# Determine Polish version filename
PL_FILE="${EN_FILE%.md}_PL.md"

if [ ! -f "$PL_FILE" ]; then
    echo "⚠️  Warning: Polish version not found: $PL_FILE"
    echo "   This document may need a Polish translation."
    exit 0
fi

# Extract headers from both files (preserving level)
EN_HEADERS_RAW=$(grep -E '^#{1,6}\s+' "$EN_FILE")
PL_HEADERS_RAW=$(grep -E '^#{1,6}\s+' "$PL_FILE")

# Normalized headers for display/counting
EN_HEADERS=$(echo "$EN_HEADERS_RAW" | sed 's/^#*/#/')
PL_HEADERS=$(echo "$PL_HEADERS_RAW" | sed 's/^#*/#/')

# Count headers
EN_COUNT=$(echo "$EN_HEADERS" | grep -c '^#' || echo "0")
PL_COUNT=$(echo "$PL_HEADERS" | grep -c '^#' || echo "0")

if [ "$EN_COUNT" -ne "$PL_COUNT" ]; then
    echo "❌ Error: Header count mismatch!"
    echo "   English version: $EN_COUNT headers"
    echo "   Polish version:  $PL_COUNT headers"
    echo ""
    echo "English headers:"
    echo "$EN_HEADERS" | head -10
    if [ "$EN_COUNT" -gt 10 ]; then
        echo "   ... ($((EN_COUNT - 10)) more)"
    fi
    echo ""
    echo "Polish headers:"
    echo "$PL_HEADERS" | head -10
    if [ "$PL_COUNT" -gt 10 ]; then
        echo "   ... ($((PL_COUNT - 10)) more)"
    fi
    exit 1
fi

# Check header structure (levels should match)
# Extract level sequence: count # characters for each header
EN_LEVEL_SEQ=$(echo "$EN_HEADERS_RAW" | sed 's/^\(#*\).*/\1/' | while IFS= read -r line; do echo -n "${#line} "; done)
PL_LEVEL_SEQ=$(echo "$PL_HEADERS_RAW" | sed 's/^\(#*\).*/\1/' | while IFS= read -r line; do echo -n "${#line} "; done)

if [ "$EN_LEVEL_SEQ" != "$PL_LEVEL_SEQ" ]; then
    echo "⚠️  Warning: Header structure may differ (level sequence)"
    echo "   This is a soft check - verify manually if needed"
    echo "   English levels: $EN_LEVEL_SEQ"
    echo "   Polish levels:  $PL_LEVEL_SEQ"
fi

echo "✅ Structure is consistent: $EN_COUNT headers in both versions"
exit 0
