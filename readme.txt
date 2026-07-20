=== SEOistic ===
Contributors: [add-your-wordpress-org-username]
Tags: seo, schema, sitemap, redirects, indexing
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

On-page SEO analysis, schema, sitemaps, redirects, fast indexing, and AI-assisted optimization with your own API key.

== Description ==

SEOistic is a WordPress SEO plugin: on-page analysis and scoring, schema
(structured data), XML sitemaps, robots and canonical control, redirects
with a 404 monitor, image SEO, fast/instant search-engine indexing (Google
Indexing API + IndexNow, branded Indexistic), and AI-assisted content
optimization using a provider of your choice — OpenRouter, Groq, or your
own self-hosted Ollama server.

= Free features =

* A deterministic, versioned 0-100 SEO score per post, with live feedback
  while you edit (title, meta description, focus keyword placement, heading
  structure, internal links, image alt text, content length, share image).
* Google desktop/mobile and social-share search-appearance previews.
* Schema.org JSON-LD (Organization, WebSite, per-page types), validated
  against each type's required/recommended properties.
* XML sitemaps, robots.txt, canonical URLs, breadcrumbs, and llms.txt.
* Redirects with a 404 monitor and CSV import/export.
* Fast indexing (Indexistic): submit published/updated URLs to Google's
  Indexing API and the free IndexNow protocol (Bing, Yandex, Seznam,
  Naver), automatically on publish (opt-in) or from a bulk console.
* Content Health: orphan-page detection and content-decay flagging, both
  linking straight to the editor rather than auto-applying anything.
* Importers for Yoast SEO, Rank Math, and AIOSEO metadata.
* WooCommerce and basic local-business SEO fields.

= Premium features (license required) =

AI-assisted generation (bring your own API key/endpoint), a custom schema
builder, Core Web Vitals monitoring, AI search-visibility / AEO reporting,
a keyword rank tracker, a read-only Google Search Console dashboard, and
the WPistic Business Automator integration. Current plans and pricing are
kept up to date at https://seoistic.wpistic.com/#pricing rather than
duplicated here.

= AI generation is always explicit and previewed =

Every AI suggestion — title, description, keywords, schema type, alt text,
content improvements — is shown as a before/after preview that you must
explicitly apply. Nothing is generated automatically, published
automatically, or written to a field without your action.

== Installation ==

1. In your WordPress admin, go to Plugins > Add New > Upload Plugin.
2. Choose the seoistic-{version}.zip file and click Install Now.
3. Click Activate.
4. A new "SEOistic" item appears in your admin menu.

No Composer install and no build step are required — the plugin runs
directly from the ZIP.

== Frequently Asked Questions ==

= Do I need a license to use SEOistic? =

No. The free tier is fully functional on its own: on-page analysis and
scoring, schema, sitemaps, redirects, image SEO, WooCommerce SEO, local
SEO, fast indexing, and the metadata importers all work without a license.
A license only unlocks the premium addons listed above.

= What happens if the license server is temporarily unreachable? =

Nothing changes immediately. A single failed check doesn't disable your
premium features — SEOistic keeps trusting your last confirmed-active
license for a bounded window while it retries with backoff, and only
applies a genuine change (revoked, expired, wrong site) once the server
actually says so.

= Do I need my own API key for AI features? =

Yes. SEOistic doesn't operate its own AI backend — you connect your own
OpenRouter or Groq account (with your own API key) or your own self-hosted
Ollama server, and only pay/use what that provider charges, if anything.

= Does SEOistic phone home or track my site? =

Only what's described in "External Services" below, and only for features
you've actually turned on. There is no analytics/telemetry beacon.

= Where do I manage my license or subscription? =

At https://app.wpistic.com/ — the License screen inside SEOistic links
there directly.

== Screenshots ==

1. Dashboard — SEO health score, optimization roadmap, and quick actions.
2. Post-editor SEO workspace — live score, search preview, and priority fixes.
3. AI suggestion preview — before/after with explicit Apply/Dismiss/Undo.
4. License screen — a two-field activation form.

(Screenshot image files are not included in this release; add them under
this plugin's `/assets` directory following the standard WordPress.org
screenshot-N.png convention before a .org submission.)

== Changelog ==

See CHANGELOG.md in the plugin's source repository for the full history.
Highlights for this release are below.

= 1.3.0 =
* Premium admin UI redesign: application shell, command palette, dashboard
  roadmap, content inventory, and a live-analysis SEO workspace in the post
  editor.
* Light-mode design system; fixed a CSS specificity bug that rendered some
  button text in a color matching its own background.
* License page simplified to a two-field activation form; license key now
  encrypted at rest; validation distinguishes a temporarily unreachable
  server from an actual revoke/expiry and no longer flips premium features
  off on a single timeout.
* Every upgrade/pricing button now resolves to a real URL by default.
* Fixed a plaintext-secret storage gap in the Business Automator
  integration and a fatal-error bug on its settings screen.
* See CHANGELOG.md for the complete list.

== Upgrade Notice ==

= 1.3.0 =
Admin UI redesign and license-security hardening. No database changes; all
existing SEO metadata, settings, and REST integrations are preserved.

== External Services ==

This plugin connects to the following external services. Each is either
required for a specific feature you've enabled, or (for the license check)
runs only once you've activated a license key.

= WPistic Licensing (wpistic.com) =

Used to activate, validate, and deactivate your SEOistic license. Contacted
when you activate/deactivate a license from the License screen, and once
daily in the background while a license is active, to confirm it's still
valid.

Data sent: your site's home URL, a randomly generated installation ID
(not tied to any personal data), your entered license key, a numeric
product identifier, and the installed plugin version.

This is WordPressistic's own service; see https://wpistic.com/ for
company information. (A direct Terms/Privacy Policy URL for this service
was not available at the time of this release and should be added by the
plugin maintainer before public listing.)

= Google (OAuth, Indexing API, Search Console API) =

Used only if you connect the Indexistic Google Indexing integration
(free) and/or the Search Console addon (premium), each requiring your own
Google Cloud credentials.

Data sent: your own Google service-account or OAuth credentials; the
Indexing API receives the URL of any page you publish/update/delete;
Search Console requests read your existing Search Analytics and URL
Inspection data (never written to).

Google APIs Terms of Service: https://developers.google.com/terms
Google Privacy Policy: https://policies.google.com/privacy

= IndexNow (api.indexnow.org) =

Used only if the free Indexistic addon is enabled, to submit a published
or updated URL for fast indexing by IndexNow's participating engines
(Bing, Yandex, Seznam, Naver).

Data sent: the URL, and a key file this plugin generates and serves at
`/{key}.txt` so the service can verify site ownership.

IndexNow Terms: https://www.indexnow.org/terms

= Bing (www.bing.com) =

Used only if the free Sitemap Extras addon is enabled (pings on publish)
or when you manually use the "ping sitemap" tool, to tell Bing your
sitemap changed.

Data sent: your sitemap's URL.

Microsoft Privacy Statement: https://www.microsoft.com/en-us/privacy/privacystatement

= Groq (api.groq.com) =

Used only when you trigger an AI action and have selected Groq as your AI
provider, with your own Groq API key.

Data sent: the relevant page/post title, a content excerpt, and site
context, as part of the generation prompt. Only ever sent for content you
explicitly ask SEOistic to generate/improve.

Groq Privacy Policy: https://groq.com/privacy-policy
Groq Terms of Use: https://groq.com/terms-of-use

= OpenRouter (openrouter.ai) =

Used only when you trigger an AI action and have selected OpenRouter as
your AI provider, with your own OpenRouter API key. Same data as Groq,
above.

OpenRouter Privacy Policy: https://openrouter.ai/privacy
OpenRouter Terms of Service: https://openrouter.ai/terms

= Your own Ollama server =

If you select the self-hosted Ollama provider, requests go to a server URL
you configure yourself (defaulting to http://127.0.0.1:11434, i.e. your own
WordPress server). Nothing leaves infrastructure you control.

= Your own Business Automator instance =

Used only if you connect the premium Business Automator addon, to a URL
you configure with an API token you generate on your own instance.

== Privacy ==

SEOistic does not collect or transmit analytics, telemetry, or personal
visitor data. Every integration above is opt-in and requires you to
explicitly connect a provider or activate a license before it contacts
anything. See "External Services" for the complete, code-verified list.
