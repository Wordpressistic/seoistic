# SEOistic

A WordPress SEO plugin by [WordPressistic](https://wordpressistic.com): on-page
analysis and scoring, schema/structured data, XML sitemaps, robots and
canonical control, redirects with a 404 monitor, image SEO, fast/instant
search-engine indexing (Google Indexing API + IndexNow, branded
**Indexistic**), and AI-assisted content optimization using a provider of
your choice (OpenRouter, Groq, or your own self-hosted Ollama) — with a
genuinely useful free tier.

**Marketing site:** [seoistic.wpistic.com](https://seoistic.wpistic.com/) ·
**Account & license management:** [app.wpistic.com](https://app.wpistic.com/)

---

## Key features

- **On-page SEO analysis** — a deterministic, versioned 0–100 score per post
  (title, meta description, focus keyword placement, heading structure,
  internal links, image alt text, content length, Open Graph image), with a
  live status indicator while you edit.
- **Search appearance previews** — Google desktop/mobile and social-share
  previews that update as you type.
- **Schema / structured data** — Organization, WebSite, and per-page JSON-LD,
  validated against schema.org's required/recommended properties for the
  type you choose.
- **XML sitemaps, robots.txt, canonical URLs, breadcrumbs, llms.txt.**
- **Redirects & 404 monitor**, with CSV import/export.
- **Fast indexing (Indexistic)** — submit published/updated URLs to Google's
  Indexing API and the free IndexNow protocol (Bing, Yandex, Seznam, Naver),
  automatically on publish (opt-in) or from a bulk console.
- **Content Health** — orphan-page detection and content-decay flagging,
  both linking straight to the editor rather than auto-applying anything.
- **AI-assisted optimization** — generate/improve SEO titles, meta
  descriptions, focus keywords, schema type, image alt text, internal-link
  suggestions, and full-page optimization, using your own API key with
  OpenRouter or Groq, or a self-hosted Ollama server. Every suggestion is
  previewed before/after and requires an explicit Apply — nothing is written
  or published silently.
- **A command palette** (Ctrl/Cmd+K) to jump to any screen or search your
  content by title/score.
- **Importers** for Yoast SEO, Rank Math, and AIOSEO metadata.

### Free

Everything above except the items below ships in the free tier: unlimited
sites, full on-page analysis and scoring, schema, sitemaps, redirects, image
SEO, WooCommerce SEO, local SEO, and the Yoast/Rank Math/AIOSEO importers.

### Premium (license required)

AI-assisted generation (bring your own OpenRouter/Groq/Ollama key or
endpoint), Schema Pro (custom schema builder), Core Web Vitals monitoring,
AI search-visibility / AEO reporting, a keyword rank tracker, a read-only
Google Search Console dashboard, and the WPistic Business Automator
integration. See [seoistic.wpistic.com/#pricing](https://seoistic.wpistic.com/#pricing)
for current plans — pricing is kept up to date there, not duplicated here.

---

## Screenshots

_Add screenshots to this section (e.g. `assets/screenshot-1.png` …) covering
the dashboard, the post-editor SEO workspace, and the license/pricing
screens. Not included in this release — see the release report for details._

---

## Installation

1. Download `seoistic-{version}.zip` from the
   [Releases](https://github.com/) page (or build it yourself — see
   "For developers" below).
2. In your WordPress admin: **Plugins → Add New → Upload Plugin**, choose the
   ZIP, then **Install Now**.
3. Click **Activate**.
4. A new **SEOistic** item appears in the left-hand admin menu.

No Composer install, no `npm run build`, and no additional server
requirements beyond what's listed under Requirements — the ZIP is ready to
run as-is.

## First-time setup

1. Open **SEOistic → Dashboard**. Click **Run Site Audit** to score your
   existing published content — the score is always calculated from real
   on-page checks; nothing is estimated or invented.
2. Open **SEOistic → Settings** to set your title separator, confirm XML
   sitemaps and `llms.txt` are enabled, and (optionally) set a default
   social-share image.
3. If you have a license key, open **SEOistic → License** to activate it —
   see "License activation" below.
4. To use AI-assisted generation, open **SEOistic → Settings → AI**, pick a
   provider, and add your own API key (not needed for a self-hosted Ollama
   server).

## Running a site audit

**SEOistic → Dashboard → Run Site Audit** scores every published post/page
in small batches (so it never times out on a large site) and refreshes the
dashboard's health score, the optimization roadmap, and every post's SEO
score. You can also audit a single post from its own SEO workspace ("Run
Audit" in the Audit tab), or filter **SEOistic → Content** by score band or
issue type afterward.

## Configuring metadata

Every public post type gets an **SEOistic — SEO** panel on its edit screen:
SEO title, meta description, focus keyword, canonical URL, robots
(noindex/nofollow), Open Graph title/description/image, schema type, and a
breadcrumb-title override. The live score and a prioritized list of fixes
update as you type — no need to save and reload to see the effect of a
change.

## License activation

**SEOistic → License**. Enter your license key and click **Activate
License** — that's the entire form; there's nothing else to configure. The
plugin validates your key against the WPistic licensing service, unlocks
the premium addons your plan includes, and re-validates automatically in
the background (see "External services" below for exactly what's sent).
Once active, the page shows your plan, expiry, and a masked version of your
key — the full key is never displayed again after activation. Use
**Deactivate License** to release this site's activation (e.g. before
moving the license to another site).

Don't have a key yet, or need to manage an existing one? See "Account
dashboard" below.

## Account dashboard

Manage your subscription, licenses, and billing at
**[app.wpistic.com](https://app.wpistic.com/)**. The License and Upgrade
screens inside SEOistic both link there directly.

## Pricing

Current plans and pricing live at
**[seoistic.wpistic.com/#pricing](https://seoistic.wpistic.com/#pricing)** —
every "Upgrade" button inside the plugin links there. We keep pricing on
the website rather than hardcoded in the plugin so it's never stale.

---

## Requirements

- WordPress 6.4+
- PHP 8.1+
- MySQL/MariaDB with a standard WordPress `wpdb` connection (used for the
  Redirects/404-monitor tables)

## Compatibility

Tested with the Block Editor (Gutenberg) and the Classic Editor. Works
alongside WooCommerce (adds product-specific SEO fields when WooCommerce is
active) but doesn't require it. No known conflicts with common caching or
security plugins — SEOistic's own admin assets are scoped and only load on
its own screens and the post-edit screen, never site-wide.

## Privacy

SEOistic doesn't send any data anywhere by default beyond what's required
to validate your license once you activate one. AI generation, Google
Indexing/Search Console, and the Business Automator integration are all
opt-in — each requires you to explicitly connect a provider/account first,
and none of them run automatically until you do. See "External services"
for the full, code-verified list of what's contacted and when.

## External services

| Service | When contacted | Data sent |
|---|---|---|
| **WPistic licensing** (`wpistic.com`) | License activation/deactivation, and a daily background check while a license is active | Site URL, a generated installation ID, your license key, and the installed plugin version |
| **Google** (OAuth, Indexing API, Search Console API) | Only if you connect the Indexistic Google integration or the Search Console addon | Your own Google Cloud credentials; the Indexing API receives the URL you publish; Search Console reads (never writes) your existing Analytics/Inspection data |
| **IndexNow** (`api.indexnow.org`) / **Bing** | On publish, only if the relevant free addon is enabled | The published URL / your sitemap URL |
| **Groq** / **OpenRouter** | Only when you click an AI action, and only if you selected that provider | The page title, a content excerpt, and site context, as part of the generation prompt |
| **Your own Ollama server** | Same as above, if you selected Ollama | Same — stays entirely on infrastructure you control |
| **Your own Business Automator instance** | Only if you connect the addon | An API token you generate on your own instance |

Full trigger-by-trigger detail (with file references) is in
`docs/release-audit.md` in the source repository.

## Security reporting

Found a security issue? Please report it privately rather than opening a
public GitHub issue — email **security@wordpressistic.com** with details
and, if possible, steps to reproduce. We'll acknowledge receipt and keep
you updated as we investigate and fix it.

## Support

- Documentation and account management: [app.wpistic.com](https://app.wpistic.com/)
- General questions: [wordpressistic.com](https://wordpressistic.com)
- Bugs and feature requests: open a GitHub issue on this repository

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

Built by [WordPressistic](https://wordpressistic.com).

---

## For developers

This repository **is** the plugin — clone or symlink it directly into
`wp-content/plugins/seoistic`. There is no build step and no Composer
install required at runtime.

```
wp-content/plugins/seoistic/   <- this repo
├── seoistic.php                # plugin bootstrap (headers, constants, autoloader)
├── src/                        # PSR-4, namespace Wpistic\Seoistic\
├── lib/seo-core/               # framework-agnostic SEO domain library, namespace Wpistic\SeoCore\
├── assets/                     # admin CSS/JS (vanilla, no build step)
├── knowledge/                  # markdown guidance files that ground every AI prompt
└── docs/                       # architecture notes, audits, and planning docs
```

`src/autoload.php` maps two PSR-4 prefixes with no Composer involved —
`Wpistic\Seoistic\*` → `src/*`, and `Wpistic\SeoCore\*` (a framework-agnostic
domain library: meta tags, the JSON-LD schema graph, redirect rules, the
pre-publish `Auditor` — zero WordPress function calls, independently
unit-tested) → `lib/seo-core/src/*`. `lib/seo-core/composer.json` exists
only so that sub-package can also be tested/consumed standalone; the plugin
itself needs nothing installed to run.

### Boot sequence (`src/Plugin.php`)

1. Core SEO engine — always on, free, never gated: `Core\Meta`,
   `Core\Schema`, `Core\Sitemaps`, `Core\Robots`, `Core\Breadcrumbs`,
   `Core\LlmsTxt`, `Core\ScoreRecalculator`, `Core\ScheduledAudit`.
2. `License\License` — the entitlement bridge, registered before modules
   boot so `Module\Entitlement` has fresh data.
3. `Module\ModuleRegistry` — discovers and boots the toggleable addons
   (`src/Addon/*Module.php`), each gated by
   `Module\Entitlement::can($id, $tier)`.
4. Admin-only: `Admin\Admin` (dashboard/addons/pricing/settings),
   `Admin\SeoColumns`, `Admin\SeoMetabox`, `Admin\AiSettingsPage`,
   `Admin\AiToolsPage`, `Admin\AutomationSettingsPage`,
   `Admin\ContentHealthPage`, `Admin\ContentInventoryPage`.
5. `AI\RestController` — registers the `seoistic/v1` REST namespace on every
   request (REST requests aren't `is_admin()`).

### Post-meta contract (`src/Core/PostSeo.php`)

All per-post SEO data reads and writes go through `Core\PostSeo` — the
single source of truth for the `_seoistic_*` meta keys, with transparent
fallback to pre-1.1.0 keys on read so upgrading sites never lose data.

### Security conventions (please follow these in every PR)

- Every form: `wp_nonce_field()` + `check_admin_referer()` on the handler.
- Every REST route: a real `permission_callback` (never `__return_true`);
  post-scoped routes also check `current_user_can('edit_post', $post_id)`.
- Sanitize on the way in, escape on the way out.
- Secrets (AI provider keys, the license key, OAuth tokens, the Business
  Automator API token) are encrypted at rest via `Core\Crypto` and never
  echoed back in full — see `docs/release-audit.md`.
- No file writes without an explicit user action + confirmation;
  `.htaccess` writes always back up first.

### Local development

1. Clone/symlink this repo as `wp-content/plugins/seoistic` in a local
   WordPress install.
2. Activate **SEOistic** from Plugins.
3. Use the `seoistic/entitlement` or `seoistic/license_valid` filters to
   unlock premium addons locally without a real license.

```bash
# Lint
find . -name "*.php" -not -path "./lib/seo-core/vendor/*" -exec php -l {} \;
find assets/js -name "*.js" -exec node --check {} \;

# Test the standalone domain library
cd lib/seo-core && composer install && vendor/bin/phpunit
```

### Building a release ZIP

```bash
bash bin/build-release.sh
```

Produces `build/packages/seoistic-{version}.zip` and a matching
`.sha256` checksum — see `docs/implementation-plan.md` and the script
itself for exactly what it does and excludes.

### More documentation

- `docs/ui-architecture.md`, `docs/design-system.md` — the admin UI system
- `docs/rest-api-contracts.md` — every `seoistic/v1` REST route
- `docs/release-audit.md` — the pre-release security/quality audit
- `docs/distribution-model.md` — the licensing/GPL distribution decision
- `docs/test-plan.md` — what's tested and how
- `docs/feature-status.md` — what's connected vs. planned, per feature
