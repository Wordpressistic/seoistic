# robots.txt Guidance

robots.txt controls crawler access, not indexing directly — a disallowed-but-linked URL can still appear in search results without a snippet. Use `noindex` meta tags for indexing control; use robots.txt only for crawl budget and truly private paths.

## Safe defaults
- Always include a `Sitemap:` line pointing to the XML sitemap index.
- Disallow paths that have no public SEO value and waste crawl budget: `/wp-admin/` (but explicitly `Allow: /wp-admin/admin-ajax.php`, since many front-end scripts depend on it).
- Never disallow `/wp-content/uploads/` — this blocks image indexing, which hurts image search traffic.
- Never disallow CSS/JS asset directories (theme/plugin folders) — Google needs to render the page to evaluate Core Web Vitals and mobile-friendliness; blocking assets can hurt rankings.
- Never disallow the entire site (`Disallow: /`) unless the site is intentionally not meant to be indexed at all (staging, dev).

## Things that are commonly over-blocked (avoid)
- Search/filter query parameters — only disallow these if they create genuine duplicate-content crawl traps at scale (e.g. faceted navigation with thousands of parameter combinations), not preemptively.
- Category/tag archives — these usually have real SEO value; don't block them by default.
- `/feed/` — usually fine to leave crawlable; blocking is rarely worth it.

## Format rules
- One directive per line, `User-agent`, `Disallow`/`Allow`, groups separated by blank lines.
- Rules are case-sensitive and path-based (not regex) except for `*` and `$` wildcards, which most major crawlers support.
- Order within a group does not create precedence in the original spec, but Google uses "most specific rule wins" — keep rules simple to avoid ambiguity.

## Warning triggers
Flag as risky: disallowing `/`, disallowing any asset/uploads path, disallowing the sitemap's own path, or a rule that would block a majority of real content URLs.
