<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Canonical external destinations: the marketing pricing page and the
 * WPistic account/license-management portal. Single source of truth so every
 * upgrade CTA and every account link across the plugin resolves to the same
 * place. Advanced deployments override the default via the SEOISTIC_*
 * constants (defined once in seoistic.php, guarded so wp-config.php can
 * pre-define them) or the matching filter — never through a visible,
 * editable wp-admin settings field.
 */
final class Links {

	public static function pricing_url(): string {
		$default = defined( 'SEOISTIC_PRICING_URL' ) ? SEOISTIC_PRICING_URL : 'https://seoistic.wpistic.com/#pricing';

		/**
		 * Filter the canonical SEOistic pricing/upgrade URL.
		 *
		 * @param string $url The pricing URL every upgrade CTA links to.
		 */
		return esc_url_raw( (string) apply_filters( 'seoistic_pricing_url', $default ) );
	}

	public static function account_url(): string {
		$default = defined( 'SEOISTIC_ACCOUNT_URL' ) ? SEOISTIC_ACCOUNT_URL : 'https://app.wpistic.com/';

		/**
		 * Filter the canonical WPistic account/license-management URL.
		 *
		 * @param string $url The account portal URL.
		 */
		return esc_url_raw( (string) apply_filters( 'seoistic_account_url', $default ) );
	}
}
