# Local SEO / LocalBusiness Schema Guidance

## LocalBusiness schema essentials
- `name`, `address` (PostalAddress: streetAddress, addressLocality, addressRegion, postalCode, addressCountry), `telephone`, and `openingHoursSpecification` are the core fields worth getting right — incomplete or inconsistent data is worse than omitting a field.
- Use the most specific applicable subtype when known (Restaurant, Dentist, Plumber, Store, etc.) instead of the generic `LocalBusiness` — it's still valid schema and more precise.
- `geo` (latitude/longitude) helps map-based results; only include it if the coordinates are actually accurate for the business location.
- NAP consistency (Name, Address, Phone) between the schema, the visible page content, and any external listings (Google Business Profile, directories) matters more than any single field — inconsistency is a known local-ranking risk factor.

## Content recommendations for local pages
- Mention the service area / city names naturally in headings and body copy — not stuffed, but present where a local searcher's query would match.
- Include real, specific details: service area, hours, what makes this location distinct — generic template copy repeated across multiple location pages hurts all of them.
- For multi-location businesses, each location should have its own unique page with its own schema — never a single shared LocalBusiness block for multiple physical addresses.

## Common mistakes to flag
- Listing hours, address, or phone in schema that don't match what's visible on the page.
- Using LocalBusiness schema on a page that isn't actually about a specific physical business location (e.g. a blog post).
- Missing `openingHoursSpecification` when hours are prominently displayed on the page.
