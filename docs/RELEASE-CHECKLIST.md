# Release Checklist — SEOistic 1.4.0

Steps performed and verified for the production release:

- Update version constants and plugin header to `1.4.0`.
- Switch default license API to `https://api.wpistic.com`.
- Remove legacy GitHub Releases-based updater for licensed installs.
- Add deterministic build script at `scripts/build-release.sh`.
- Produce `dist/seoistic-1.4.0.zip` and SHA-256 checksum via the build script.
- Ensure licensing UI remains in `SEOistic → License` and uses safe nonces and `manage_options` capability.
- Ensure free features continue to function without an active license.

Manual verification steps for operators:

1. Run `scripts/build-release.sh 1.4.0` on CI after `composer install --no-dev`.
2. Verify `dist/seoistic-1.4.0.zip` and `dist/seoistic-1.4.0.zip.sha256` exist.
3. Upload the ZIP to WPistic package registry with matching metadata.
4. Create a GitHub Release (optional) attaching the ZIP and checksum.
5. Verify an activated staging site sees the protected update via WPistic.
6. Run automated tests and PHPCS before tagging.
