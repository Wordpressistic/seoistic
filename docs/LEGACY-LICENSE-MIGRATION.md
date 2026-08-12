# Legacy License Migration

Existing SEOistic installs may have legacy options stored in the database. This release preserves those values and attempts a safe migration where possible.

Legacy keys to inspect (read-only fallback):

- `seoistic_license_key`
- `seoistic_license_status`
- `seoistic_license_expires`
- `seoistic_license_instance`
- `seoistic_license_last_ok`
- `seoistic_license_last_check`
- `seoistic_license_fail_count`
- `seoistic_license_meta`
- `seoistic_license_product_active`

Migration approach:

1. Do not delete legacy options automatically on update.
2. On the first admin visit to the License screen the plugin will detect a legacy key and prompt the administrator to re-activate via WPistic. If the API supports direct exchange/migration, use the canonical SDK to exchange old data for a WPistic activation token.
3. If exchange is not possible, preserve legacy options and surface instructions to the admin on obtaining a new WPistic license key.

This avoids accidental data loss and gives admins a clear path to re-activate without losing site configuration.
