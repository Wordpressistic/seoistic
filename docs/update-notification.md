Update notifications for SEOistic

How updates are delivered

- This repository uses GitHub Releases as the distribution mechanism.
- The plugin contains a lightweight update checker (in seoistic.php) that queries the GitHub Releases API for the latest release tag and compares it with the installed plugin version.
- If a newer version exists, WordPress will show an update notification and users can update using the provided ZIP from GitHub releases.

Caching and rate limits

- The checker caches GitHub response in a transient for 12 hours to avoid exceeding GitHub's unauthenticated API rate limits.
- In high-volume or private-repo cases, consider using an authenticated API token or a dedicated update server.

How to release a new version

1. Create a new Git tag (vX.Y.Z) and push it.
2. Open a GitHub Release and upload the ZIP or use the repository tag to auto-generate a release.
3. WordPress sites will detect the new tag (within caching window) and notify users.

Security notes

- The update checker fetches release notes and the zip download URL from GitHub. Users should verify release notes and changelog when updating.
- Do not embed secret tokens in the plugin code.

Advanced: plugin-update-checker

For more advanced features (private releases, authentication, validation), consider integrating the plugin-update-checker library by YahnisElsts: https://github.com/YahnisElsts/plugin-update-checker
