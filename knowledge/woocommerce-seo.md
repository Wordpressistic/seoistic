# WooCommerce / Product SEO Guidance

## Product schema (schema.org/Product)
- Required for rich results: `name`, `image`, `description`, `offers` (Offer: `price`, `priceCurrency`, `availability`, `url`), and ideally `aggregateRating`/`review` when real reviews exist.
- `availability` must accurately reflect real-time stock status (InStock / OutOfStock / PreOrder / LimitedAvailability) — mismatched availability is a common cause of Merchant Center / rich-result disapprovals.
- Never fabricate `aggregateRating` or `review` data — only include it when the store has real, displayed customer reviews. This is one of the most enforced structured-data policies Google has.
- `sku` and `gtin`/`mpn` (when available) improve product matching in Google Shopping-style results.

## Product title & description guidance
- Product SEO titles should lead with the product name, then a distinguishing attribute (size, material, model) — not generic marketing adjectives.
- Product meta descriptions should mention concrete attributes (material, use case, what's included) and, where true, availability/shipping info — these drive CTR from shopping-intent searches.
- Avoid duplicate descriptions across variants of the same product; each variant page (if separately indexed) needs distinguishing copy or a canonical to the parent.

## Category & collection pages
- Category pages benefit from a short, unique intro paragraph above the product grid — pure grids with no text are harder to rank for informational-adjacent queries.
- Use canonical tags correctly on paginated/filtered category views to avoid duplicate-content dilution.

## Open Graph for products
- `og:image` should be the primary product photo; `product:price:amount` / `product:price:currency` Open Graph tags improve link previews on social platforms that support them.
