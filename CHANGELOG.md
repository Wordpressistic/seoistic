# Changelog

All notable changes to SEOistic are documented here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/).

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
