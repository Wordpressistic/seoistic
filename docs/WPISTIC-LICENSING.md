# WPistic Licensing Integration — SEOistic

This document describes the WPistic licensing and update integration for SEOistic.

- Product slug: `seoistic`
- Default API endpoint: `https://api.wpistic.com`
- Activation endpoint: `POST /api/v1/licenses/activate`
- Validation endpoint: `POST /api/v1/licenses/validate` (or `ping` semantics)
- Deactivate endpoint: `POST /api/v1/licenses/deactivate`
- Update metadata: `GET /api/v1/products/seoistic/updates`
- Download authorization: `POST /api/v1/downloads/authorize`

Architecture notes:

- The plugin ships with an adapter layer so existing `Entitlement::can()` calls continue to function while relying on WPistic entitlements under the hood.
- Protected update packages are distributed via short-lived authorized URLs returned by WPistic. The SDK must verify SHA-256 prior to installation.
- The plugin's default license API constant is `SEOISTIC_LICENSE_API_URL` and points to `https://api.wpistic.com`.

Security:

- All admin actions use `manage_options` and nonces.
- No raw license keys or activation tokens are output in HTML, logs, or JS.
- Offline grace periods and bounded backoff are respected; network failures do not equal revocation.

For the full end-to-end contract and admin/UI flow see `docs/RELEASE-CHECKLIST.md` and `docs/LEGACY-LICENSE-MIGRATION.md`.
