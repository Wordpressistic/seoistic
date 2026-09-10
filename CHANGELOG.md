# Changelog

All notable changes to SEOistic are documented here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/).

## [1.6.0 "Aurora"] - 2026-09-11

The biggest release in SEOistic history: all four premium addons go live, AI moves to the WPistic AI backend with a monthly AI Credits system, Google Search Console connection gets a real recovery flow, and the entire admin gets the animated Aurora design system.

### Added — Addons

- **AI Search Visibility (AEO)** (Business): llms.txt studio (visual builder + live preview + one-click apply), AI-crawler analytics (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot — visits trend + most-crawled content), AI-scored AEO content audits (answer-first structure, FAQ presence, entity coverage, heading clarity, freshness) with fix suggestions, and a guided citation-readiness checklist. First-mover feature: be visible in AI answers, not just Google.
- **Rank Tracker & Reports** (Business): keyword tracking with daily position checks (scheduled, lock-protected), position history with sparklines and movement badges, locale/device filters, Search Console import mode (labeled delayed data), white-label HTML reports with print-to-PDF and scheduled weekly email.
- **Schema Pro / Custom Builder** (Pro): visual schema block builder (Article, FAQPage, HowTo, Product, LocalBusiness, Event, Review, Course, Recipe, TravelAgency, SoftwareApplication + custom JSON mode), dynamic field mapping with post variables, live JSON-LD preview, validator-gated saves (invalid JSON is never stored), conditional display rules (post type / taxonomy / template / URL pattern), JSON export/import. New table `seoistic_schema_blocks`.
- **Performance & Core Web Vitals** (Pro): PageSpeed Insights through the WPistic proxy (no Google keys needed), weekly metric history (LCP / CLS / INP) with trend arrows, threshold email alerts, and a Quick Wins panel (lazy-load, hero preload, render-blocking removal) with confirm + dry-run before every change.

### Added — AI Credits system (WPistic AI backend)

- New `Core\AI\WpisticAiClient`: every AI feature now runs on the WPistic AI gateway (ai.wpistic.com) — no API keys, no provider accounts, nothing to configure. License-holders just use the features.
- Monthly AI Credits per plan (Free 30 · Pro 500 · Business 1,500 · Agency 5,000), reset monthly. Live credits widget on Dashboard, AI Tools and the editor with usage history.
- Credit costs: title/description/keywords 1 · image-alt batch 1 per 10 · content optimize 3 · full-page optimize 5 · schema generate 2 · AEO audit 10. Identical requests within 10 minutes are cached and cost 0.
- Friendly upgrade card with credits remaining when the balance runs out (HTTP 402 contract), automatic retry with backoff on rate limits (429).
- **Custom AI model** (Business/Agency): bring your own OpenAI-compatible model (base URL + key + model, encrypted at rest). Unmetered — your key, your cost.

### Added — Business Automator (Business)

- Recipe engine: trigger (schedule / content saved / license event) -> audit -> AI draft fix -> approval queue -> apply -> notify. Mutating steps are approval-gated by default with per-recipe auto-apply opt-in, fully audited.
- Run history with per-step status and diff preview before anything is applied.
- Five starter recipes: weekly audit + report, new-post SEO polish, freshness monitor, schema reminder, llms.txt refresh.

### Added — Aurora UI

- New design system (aurora.css/js): modern cards, adaptive light/dark following the WP admin scheme, brand accent.
- Animated score rings with count-up numbers, old-to-new score transitions on re-audit.
- Workflow animations: bulk tools show step-trackers (queued -> item N/M -> done summary), staggered checklist reveals, skeleton loaders, toast notifications.
- Accessibility: every animation respects `prefers-reduced-motion` (content appears instantly); motion budget 400ms.
- Onboarding checklist card on the dashboard (connect license -> run first audit -> set titles) with live checkmarks.

### Fixed

- **Google Search Console 403 access_denied**: dedicated recovery card when Google rejects the connection — exact OAuth testing-mode steps (add your account as Test User), publish guidance, copy-able redirect URI, Search Console property match check against the site URL, and a force re-connect that clears stale tokens. Refresh tokens now rotate; 401 responses trigger automatic refresh before any user-facing error.
- Header handling hardening for LiteSpeed/Hostinger environments shared across WPistic plugins.

### Changed

- Local provider options (Ollama / OpenRouter / Groq pickers) are no longer offered to Free/Pro users — the WPistic AI backend replaces them. Existing stored settings are left in place but unused.
- Legacy `AiGateway` / `AutomatorClient` / `ScriptTemplates` removed in favor of the new client and recipe engine.
- Database schema version 1.4.0 (adds `seoistic_schema_blocks`, `seoistic_keywords`, `seoistic_positions`; idempotent upgrade).

### Upgrade notes

- Requires WordPress 6.4+ and PHP 8.1+. All settings and meta are preserved; the database upgrade runs automatically on activation.
- Premium addons require an active license (Pro or Business by addon); without one they show an upgrade card and never fatal.

## [1.5.3] - 2026-09-07

### Fixed

- Treat the server-confirmed license plan as authoritative; a valid Free key
  no longer silently unlocks Business features. Normalize legacy starter and
  professional slugs to Pro and Business; unknown plans resolve to Free.
- Preserve explicit legacy product mappings only when the server omits a plan.
- Require a paid plan for `is_pro()`. Preserve expiry, revocation and bounded
  outage protection; do not inherit a previous key's plan after key replacement.
- Correct activation wording and run 32 isolated licensing regressions in CI.

### Deployment note

Review server-assigned plans before upgrading sites that relied on the old
Business default. Authorized complimentary access must be recorded on WPistic,
not simulated by the plugin. No SEO metadata or database schema changes.

## [1.5.2] - 2026-08-12

### Added

- WPistic-backed licensing integration and protected update support.
- WPistic activation/validation adapter and entitlement compatibility layer.
- `scripts/build-release.sh` — deterministic release packaging.

### Release

- Bump version to 1.5.2 and prepare production release.

### Changed

- Unified licensing architecture to use WPistic platform by default.
- Removed legacy GitHub Releases-based updater for licensed installations.

### Security

- Default license API endpoint now points to `https://api.wpistic.com`.

### Release Notes

- Target version for this release: `1.4.0`.

## [1.3.0] — First public release

### Added

- Premium application shell: grouped sidebar navigation, a topbar with
  breadcrumbs, and a Ctrl/Cmd+K command palette that navigates screens and
  searches content by title/score in real time.
- Dashboard rebuilt as a command center: an animated SEO health score with a
  real "vs. previous scan" delta (tracked scan history), quick actions, and
  an optimization roadmap grouped by severity with real per-issue counts
  and drill-down links — never invented traffic/impact numbers.
- New **Content** screen: a server-paginated inventory of every post/page
  with its score, focus keyword, and index state, filterable by issue and
  score band.
- Post-editor SEO workspace redesign: a live score header, debounced live
  re-analysis of unsaved field values (new `POST /analyze` REST route —
  deterministic, versioned, never persists), and a priority-fixes /
  passed-checks list that updates as you type.
- AI suggestions now render as an explicit before/after preview card with
  Apply / Dismiss / Undo, instead of writing directly into a field.
- `GET /analyze` route's sibling, `GET /search`, powers the command
  palette's content search, permission-filtered per result.
- `Core\Links` — a single source of truth for the pricing
  (`https://seoistic.wpistic.com/#pricing`) and account
  (`https://app.wpistic.com/`) URLs, overridable via the
  `SEOISTIC_PRICING_URL` / `SEOISTIC_ACCOUNT_URL` constants or the
  `seoistic_pricing_url` / `seoistic_account_url` filters.
- A lightweight plan-summary block on the Upgrade screen (current plan,
  license status, one primary "View Plans and Pricing" CTA, "Manage
  Account" link) above the existing detailed plan-comparison cards.
- `docs/release-audit.md`, `docs/distribution-model.md`,
  `docs/ui-audit.md`, `docs/ui-architecture.md`, `docs/design-system.md`,
  `docs/rest-api-contracts.md`, `docs/implementation-plan.md`,
  `docs/test-plan.md`, `docs/migration-notes.md`, `docs/feature-status.md`.
- `bin/build-release.sh` — a reproducible, allowlist-based release build
  that produces `build/packages/seoistic-{version}.zip` and a matching
  `.sha256` checksum.
- `readme.txt` (WordPress.org-format, including a full "External Services"
  disclosure) and this changelog.

### Changed

- **License screen simplified.** The inactive form is now exactly two
  controls: a license key field and an Activate button. License-server and
  product-ID configuration moved from editable settings fields to
  deployment constants (`SEOISTIC_LICENSE_API_URL`,
  `SEOISTIC_LICENSE_PRODUCT_ID`) with filter overrides — never a visible
  wp-admin setting. The active state shows a masked key, plan, expiry, and
  last-validated time, plus Deactivate and Manage Account actions.
- **License validation now distinguishes an unreachable server from an
  actual revoke/expiry.** A transient failure (network error, timeout,
  malformed response) backs off with capped exponential delay and never
  overwrites the last known-good status; a real rejection from the server
  still applies immediately. A confirmed-active license stays trusted for
  up to 30 days without a fresh confirmation, so a single outage can't
  silently downgrade a paying site to Free.
- `Module\Entitlement`'s validity check now delegates to
  `LicenseClient::is_valid()` instead of duplicating (and having drifted
  out of sync with) its own simpler logic.
- Every plan/LTD "Upgrade" and "Get the deal" button now defaults to the
  real marketing pricing URL instead of a dead `#` link (the
  `seoistic_upgrade_url` filter is preserved for backward compatibility —
  only its default changed).
- Sidebar navigation switched from a dark-navy theme to light mode: white
  surface, navy/slate text, pale-blue hover/selected states, and a blue
  selection indicator.
- Plugin header: `Plugin URI` updated, `License URI` added, `Update URI:
  false` added (this plugin is not distributed via WordPress.org), and the
  description shortened to an accurate, current summary.
- README.md restructured to lead with user-facing setup/usage/privacy
  documentation, with the existing architecture notes kept as a
  "For developers" section further down.

### Fixed

- **Button text contrast.** A CSS specificity bug made some primary/AI
  button text render in the same color family as its own background
  (blue-on-blue, purple-on-purple) — root cause was a single overly broad
  link-color rule; fixed with a zero-specificity `:where()` selector so it
  can never outrank a component's own color.
- Disabled buttons now get a real neutral disabled treatment (background,
  border, and text color) instead of relying on opacity alone; every
  button variant now has its own visually distinct hover *and* pressed
  (`:active`) state.
- Featured pricing card's "Main plan" flag no longer crowds the heading
  directly below it.
- Score-column rings on post-list tables (`edit.php`) were previously
  unstyled because the stylesheet never loaded there.

### Security

- License key is now encrypted at rest (previously stored in plaintext) —
  a pre-1.3 plaintext key is transparently migrated on first read, no
  visible steps, no data loss.
- Business Automator API token is now encrypted at rest and never echoed
  back into its settings form in plaintext (previously the one secret in
  the plugin that didn't follow the encrypted-at-rest pattern already used
  everywhere else).
- License activation is now rate-limited server-side (5 attempts / 10
  minutes) independent of anything client-side.
- License-validation cron is now unscheduled on plugin deactivation.

### Fixed (pre-existing bugs found during the release audit)

- `Admin\BusinessAutomatorPage::enqueue_assets()` referenced an undefined
  PHP constant, which fataled (PHP 8+) every time that admin screen loaded.
- `Addon\SitemapExtrasModule` fired an outbound request to a Google
  sitemap-ping endpoint Google retired in 2023, on every publish.

## Earlier history (pre-1.3.0, consolidated from commit history)

These were not released as versioned public builds; consolidated here for
reference rather than reconstructed into specific version numbers that
can't be verified against an actual tag or release.

- Initial SEOISTIC plugin import, then upgraded into a full SEO suite
  (schema, sitemaps, redirects, image SEO) with the WPistic-style admin UI.
- Multi-provider AI integration (OpenRouter, Groq, self-hosted Ollama) for
  title/description/keyword/schema/alt-text generation and full-page
  optimization, with preview-then-apply for every file-writing generator
  (robots.txt, .htaccess, llms.txt).
- Indexistic: fast/instant indexing via the Google Indexing API and
  IndexNow, with auto-submit, a bulk console, and a submission history log.
- Scheduled site audits, sitemap-ping-on-submit, Content Health (orphan
  pages, content decay), and schema auto-validation.
- A Business-tier Google Search Console addon (OAuth-connected Search
  Analytics + URL Inspection).
- Business Automator integration (WPistic automation/monitoring platform).
- Ongoing pricing-tier revisions.

[1.3.0]: #

<!--
When this repository starts tagging releases, replace the [1.3.0]: # link
target above with the actual GitHub tag/release comparison URL.
-->
