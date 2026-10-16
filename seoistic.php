<?php

/**
 * Plugin Name:       SEOistic
 * Plugin URI:        https://seoistic.wpistic.com/
 * Description:       WordPress SEO suite: on-page analysis, schema, sitemaps, redirects, image SEO, WPistic AI-assisted optimization, and fast search-engine indexing — with a genuinely useful free tier.
 * Version:           1.7.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seoistic
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package Seoistic
 */

if (! defined('ABSPATH')) {
	exit;
}

define('SEOISTIC_VERSION', '1.7.1');
define('SEOISTIC_DB_VERSION', '1.5.0');
define('SEOISTIC_FILE', __FILE__);
define('SEOISTIC_DIR', plugin_dir_path(__FILE__));
define('SEOISTIC_URL', plugin_dir_url(__FILE__));

/*
 * Marketing/account/license configuration. Defined only if not already set,
 * so an operator embedding SEOistic for a client can override any of these
 * from wp-config.php (or the matching filter — see Core\Links and
 * License\LicenseClient) without a visible, editable wp-admin settings field.
 * None of these are user data — they are fixed service endpoints.
 */
if (! defined('SEOISTIC_PRICING_URL')) {
	define('SEOISTIC_PRICING_URL', 'https://www.wordpressistic.com/pricing/');
}
if (! defined('SEOISTIC_ACCOUNT_URL')) {
	define('SEOISTIC_ACCOUNT_URL', 'https://app.wpistic.com/');
}
if (! defined('SEOISTIC_LICENSE_API_URL')) {
	define('SEOISTIC_LICENSE_API_URL', 'https://api.wpistic.com');
}
if (! defined('SEOISTIC_LICENSE_PRODUCT_ID')) {
	define('SEOISTIC_LICENSE_PRODUCT_ID', 0);
}
if (! defined('SEOISTIC_GITHUB_REPO')) {
	define('SEOISTIC_GITHUB_REPO', 'Wordpressistic/seoistic');
}

require_once SEOISTIC_DIR . 'src/autoload.php';

register_activation_hook(__FILE__, array('\\Wpistic\\Seoistic\\Install\\Activator', 'activate'));
register_deactivation_hook(__FILE__, array('\\Wpistic\\Seoistic\\Install\\Deactivator', 'deactivate'));

add_action(
	'plugins_loaded',
	static function () {
		\Wpistic\Seoistic\Plugin::instance()->boot();
	}
);

/*
 * Update delivery:
 * - The public (free) build ships no bundled updater — get the latest zip from
 *   the GitHub Releases page of Wordpressistic/seoistic.
 * - The paid build wires Core\Updater (self-hosted GitHub Releases checker,
 *   repo configurable via SEOISTIC_GITHUB_REPO) for in-wp-admin updates.
 * - Licensed sites can always pull the current build from app.wpistic.com.
 */
