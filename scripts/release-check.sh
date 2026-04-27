#!/usr/bin/env bash
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
MAIN_VERSION=$(grep -m1 '^ \* Version:' ultracache-pro.php | awk '{print $3}')
STABLE=$(grep -m1 '^Stable tag:' readme.txt | awk '{print $3}')
TITLE=$(head -n1 readme.txt)
if [ "$MAIN_VERSION" != "$STABLE" ]; then echo "Version mismatch: $MAIN_VERSION vs $STABLE" >&2; exit 1; fi
echo "$TITLE" | grep -q "$MAIN_VERSION" || { echo "Readme title does not contain $MAIN_VERSION" >&2; exit 1; }
find . -name '*.php' -not -path './tests/*' -print0 | xargs -0 -n1 php -l >/dev/null
echo "Release checks passed for $MAIN_VERSION"
