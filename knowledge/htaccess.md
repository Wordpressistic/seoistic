# .htaccess SEO/Security/Performance Guidance (Apache)

.htaccess changes affect the entire site immediately and can break it if malformed — every rule here must be conservative, reversible, and scoped to what's actually asked for.

## Safe, high-value rules
- **Canonical HTTPS redirect**: force `http://` → `https://` with a 301, using `HTTPS` server variable checks, wrapped in `<IfModule mod_rewrite.c>`.
- **www / non-www canonicalization**: pick one form and 301-redirect the other, consistently with the site's actual canonical domain — never guess which form the site uses.
- **Protect sensitive files**: deny direct access to `wp-config.php`, `.env`, `readme.html`, `xmlrpc.php` (if unused), and `.git`/`.svn` directories if present.
- **Prevent directory browsing**: `Options -Indexes`.
- **Browser caching headers** for static assets (images, CSS, JS, fonts) via `mod_expires` — safe, reversible, real performance/SEO (Core Web Vitals) benefit.
- **GZIP/Brotli compression hints** via `mod_deflate`/`mod_brotli` — wrap in `<IfModule>` checks so it silently no-ops if the module isn't available (never fatal).

## Redirect old URLs
- Only include specific, explicit `Redirect 301 /old-path /new-path` rules for URLs the user has actually specified — never invent guesses at old URLs.

## Hard rules — never do these
- Never remove or modify the existing `# BEGIN WordPress` / `# END WordPress` core rewrite block.
- Never output a rule that would 500 the site if a module is missing — always wrap module-dependent rules in the matching `<IfModule>` block.
- Never disallow access to `/wp-content/uploads/`, `/wp-content/themes/`, or `/wp-content/plugins/` — this breaks the site's own asset loading.
- Never write directly to the file without a backup and an explicit user confirmation — this is a file that can take a site down if wrong.

## Output contract
Only return the additional/custom rules block — never the whole file, and never fabricate the WordPress core block. Flag any HTTPS/www redirect as needing verification against the site's actual domain setup before applying.
