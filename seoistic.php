<?php
/**
 * Plugin Name:       SEOistic
 * Plugin URI:        https://seoistic.wpistic.com/
 * Description:       WordPress SEO suite: on-page analysis, schema, sitemaps, redirects, image SEO, AI-assisted optimization (your own API key), and fast search-engine indexing — with a genuinely useful free tier.
 * Version:           1.3.0
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOISTIC_VERSION', '1.3.0' );
define( 'SEOISTIC_DB_VERSION', '1.2.0' );
define( 'SEOISTIC_FILE', __FILE__ );
define( 'SEOISTIC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOISTIC_URL', plugin_dir_url( __FILE__ ) );

/*
 * Marketing/account/license configuration. Defined only if not already set,
 * so an operator embedding SEOistic for a client can override any of these
 * from wp-config.php (or the matching filter — see Core\Links and
 * License\LicenseClient) without a visible, editable wp-admin settings field.
 * None of these are user data — they are fixed service endpoints.
 */
if ( ! defined( 'SEOISTIC_PRICING_URL' ) ) {
	define( 'SEOISTIC_PRICING_URL', 'https://seoistic.wpistic.com/#pricing' );
}
if ( ! defined( 'SEOISTIC_ACCOUNT_URL' ) ) {
	define( 'SEOISTIC_ACCOUNT_URL', 'https://app.wpistic.com/' );
}
if ( ! defined( 'SEOISTIC_LICENSE_API_URL' ) ) {
	define( 'SEOISTIC_LICENSE_API_URL', 'https://wpistic.com' );
}
if ( ! defined( 'SEOISTIC_LICENSE_PRODUCT_ID' ) ) {
	define( 'SEOISTIC_LICENSE_PRODUCT_ID', 0 );
}

require_once SEOISTIC_DIR . 'src/autoload.php';

register_activation_hook( __FILE__, array( '\\Wpistic\\Seoistic\\Install\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\Wpistic\\Seoistic\\Install\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Wpistic\Seoistic\Plugin::instance()->boot();
	}
);

/*
 * Simple GitHub Releases update checker.
 * - Checks https://api.github.com/repos/Wordpressistic/seoistic/releases/latest
 * - Adds an update to WordPress update system if a newer release tag is found.
 * - Caches release info in transients to avoid API rate limiting.
 */
add_filter( 'pre_set_site_transient_update_plugins', 'seoistic_check_for_updates' );
function seoistic_check_for_updates( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$cache = get_transient( 'seoistic_github_release' );
	if ( false === $cache ) {
		$request = wp_remote_get(
			'https://api.github.com/repos/Wordpressistic/seoistic/releases/latest',
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'SEOistic Update Checker',
				),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $request ) ) {
			set_transient( 'seoistic_github_release', array( 'error' => true ), 5 * MINUTE_IN_SECONDS );
			return $transient;
		}
		$body = json_decode( wp_remote_retrieve_body( $request ), true );
		if ( empty( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( 'seoistic_github_release', array( 'error' => true ), 5 * MINUTE_IN_SECONDS );
			return $transient;
		}
		$cache = array(
			'tag_name'    => $body['tag_name'],
			'zipball_url' => $body['zipball_url'] ?? '',
			'html_url'    => $body['html_url'] ?? 'https://github.com/Wordpressistic/seoistic/releases',
			'body'        => $body['body'] ?? '',
		);
		set_transient( 'seoistic_github_release', $cache, 12 * HOUR_IN_SECONDS );
	}

	if ( ! empty( $cache['error'] ) ) {
		return $transient;
	}
	$remote_version = ltrim( $cache['tag_name'], 'vV' );
	if ( version_compare( $remote_version, SEOISTIC_VERSION, '>' ) ) {
		$plugin_slug = plugin_basename( __FILE__ ); // e.g. seoistic/seoistic.php
		$update = new stdClass();
		$update->slug = dirname( $plugin_slug );
		$update->new_version = $remote_version;
		$update->url = $cache['html_url'];
		$update->package = $cache['zipball_url'];
		$transient->response[ $plugin_slug ] = $update;
	}
	return $transient;
}

/* Provide plugin information for the "View version details" dialog using GitHub release notes */
add_filter( 'plugins_api', 'seoistic_plugins_api', 10, 3 );
function seoistic_plugins_api( $res, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $res;
	}
	if ( empty( $args->slug ) || 'seoistic' !== $args->slug ) {
		return $res;
	}
	$cache = get_transient( 'seoistic_github_release' );
	if ( false === $cache || ! empty( $cache['error'] ) ) {
		// Trigger a fresh fetch, but avoid recursing into wp_remote_get here — the pre_set_site_transient handler does caching.
		// Fallback to basic info.
		$info = new stdClass();
		$info->name = 'SEOistic';
		$info->slug = 'seoistic';
		$info->version = SEOISTIC_VERSION;
		$info->author = 'WordPressistic';
		$info->homepage = 'https://seoistic.wpistic.com/';
		$info->download_link = 'https://github.com/Wordpressistic/seoistic/releases';
		$info->sections = array(
			'description' => 'SEOistic — SEO toolkit for WordPress.',
			'changelog'   => file_exists( SEOISTIC_DIR . 'CHANGELOG.md' ) ? file_get_contents( SEOISTIC_DIR . 'CHANGELOG.md' ) : '',
		);
		return $info;
	}
	$remote_version = ltrim( $cache['tag_name'], 'vV' );
	$info = new stdClass();
	$info->name = 'SEOistic';
	$info->slug = 'seoistic';
	$info->version = $remote_version;
	$info->author = 'WordPressistic';
	$info->homepage = 'https://seoistic.wpistic.com/';
	$info->download_link = $cache['zipball_url'];
	$info->sections = array(
		'description' => file_exists( SEOISTIC_DIR . 'README.md' ) ? file_get_contents( SEOISTIC_DIR . 'README.md' ) : 'SEOistic — SEO toolkit for WordPress.',
		'changelog'   => $cache['body'],
	);
	return $info;
}
