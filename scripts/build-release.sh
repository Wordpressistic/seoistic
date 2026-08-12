#!/usr/bin/env bash
set -euo pipefail

# Build a deterministic SEOistic plugin ZIP suitable for distribution and
# WPistic package registration. Usage:
#   ./scripts/build-release.sh 1.4.0

VERSION=${1:-1.4.0}
ROOT_DIR=$(pwd)
WORKDIR=$(mktemp -d)
DIST_DIR="$ROOT_DIR/dist"
OUT_DIR="$DIST_DIR"
PKG_NAME="seoistic-${VERSION}.zip"
PKG_PATH="$OUT_DIR/$PKG_NAME"
SHA_PATH="$PKG_PATH.sha256"

echo "Building SEOistic $VERSION"

rm -rf "$WORKDIR"
mkdir -p "$WORKDIR/seoistic"
mkdir -p "$OUT_DIR"

# Include runtime files
rsync -a --exclude '.git' --exclude '.github' --exclude 'tests' --exclude 'node_modules' --exclude 'vendor/*/tests' --exclude 'dist' --exclude '.env' ./ "$WORKDIR/seoistic/"

# Ensure vendor will be installed by CI prior to running this script. If a
# vendor directory exists, include it; otherwise the package will expect the
# vendor dir to be present after upstream CI builds it.
if [ -d "vendor" ]; then
  rsync -a vendor "$WORKDIR/seoistic/"
fi

# Ensure the top-level directory in zip is 'seoistic'
pushd "$WORKDIR" > /dev/null
zip -r "$PKG_PATH" "seoistic" >/dev/null
popd > /dev/null

# Calculate SHA-256
sha256sum "$PKG_PATH" | awk '{print $1}' > "$SHA_PATH"

echo "Built $PKG_PATH"
echo "SHA-256: $(cat "$SHA_PATH")"

# Clean up
rm -rf "$WORKDIR"

exit 0
