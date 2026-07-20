# Structured Data (schema.org / JSON-LD) Guidance

SEOISTIC emits a single `@graph` of JSON-LD per page: Organization, WebSite, a page-level node (Article/BlogPosting/WebPage or a custom type), and BreadcrumbList when applicable.

## Choosing a schema type
- **Article / BlogPosting** — editorial content with an author and publish date. Use BlogPosting for blog-style posts, Article for more formal editorial/news content.
- **Product** — a single purchasable item with price, availability and (ideally) review data. Never mark up a product page as Article.
- **FAQPage** — only when the page contains real, visible question/answer pairs a user can read without JS interaction. Do not fabricate FAQs that aren't in the content — Google penalizes mismatched structured data.
- **LocalBusiness** — for a business's own site/location pages, not for directory or review-aggregator pages.
- **Event** — a specific, dated, bookable occurrence (not a recurring evergreen "events" landing page).
- **Recipe** — food content with ingredients and steps.
- **VideoObject** — a page whose primary content is a single video.
- **Review** — an actual review of a specific product/service, with a rating.

## FAQ schema rules
- Only include Q&A pairs that literally appear as readable text on the page.
- Keep answers concise (1–3 sentences) — they are shown as an expandable snippet, not the full article.
- 3–8 FAQ pairs is a reasonable range; more than that usually indicates the content isn't really FAQ-shaped.

## General rules
- Never mark up hidden or off-screen content as if it were visible.
- Structured data must accurately reflect what a human sees on the page — mismatches between schema and visible content are a manual-action risk.
- Prefer the narrowest accurate type over a generic one (BlogPosting over WebPage when applicable).
