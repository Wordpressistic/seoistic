#!/usr/bin/env bash
set -euo pipefail

# Build a deterministic SEOistic plugin ZIP suitable for distribution and
# WPistic package registration. Usage:
#   ./scripts/build-release.sh 1.7.3

VERSION=${1:-1.7.3}
# Only accept strict semver (major.minor.patch) so the version never injects
# shell metacharacters into the zip/sha256sum invocations below.
if ! printf '%s' "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
	echo "Version must look like 1.2.3 (digits and dots only): got '$VERSION'" >&2
	exit 1
fi
ROOT_DIR=$(pwd)
WORKDIR=$(mktemp -d)
DIST_DIR="$ROOT_DIR/dist"
OUT_DIR="$DIST_DIR"
PKG_NAME="seoistic-${VERSION}.zip"
PKG_PATH="$OUT_DIR/$PKG_NAME"
SHA_PATH="$PKG_PATH.sha256"

# Runtime allowlist — identical to the CI "runtime-only distribution" step in
# .github/workflows/php.yml. Build tooling (scripts/, tests/, docs/) must never
# ship inside the plugin zip: WordPress Plugin Check flags application files.
RUNTIME_ENTRIES=(
	assets
	lib
	src
	knowledge
	languages
	seoistic.php
	uninstall.php
	LICENSE
	readme.txt
	CHANGELOG.md
	README.md
	composer.json
)

echo "Building SEOistic $VERSION"

rm -rf "$WORKDIR"
mkdir -p "$WORKDIR/seoistic"
mkdir -p "$OUT_DIR"

for entry in "${RUNTIME_ENTRIES[@]}"; do
	if [ -e "$entry" ]; then
		rsync -a "$entry" "$WORKDIR/seoistic/"
	fi
done

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
