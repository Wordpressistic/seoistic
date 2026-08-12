# SEOistic Entitlements

This document lists SEOistic feature keys and the recommended plan mapping.

- `seoistic.pro.enabled` — unlocks Pro features (Pro plan and above)
- `seoistic.sites.max` — number of sites allowed by the license
- `seoistic.ai.monthly_credits` — AI credits allocation
- `seoistic.schema.advanced` — Schema Pro features

Plan aliases supported for compatibility:

- `starter` => `pro`
- `professional` => `business`

The `Entitlement::can()` adapter maps historical SEOistic checks to WPistic feature keys.
