<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Module;

use Wpistic\Seoistic\License\LicenseClient;
use Wpistic\Seoistic\License\Plans;

/**
 * Tier-aware entitlement. A premium addon unlocks only when the active license plan
 * (Free → Pro → Business → Agency) ranks at or above the addon's required plan. The
 * plan is resolved from the cached license (its Licenseistic product id → plan map).
 *
 * The `seoistic/entitlement` filter is the seam for the client's Licenseistic /
 * Memberistic plugins (or a same-site membership check) to override the decision.
 */
final class Entitlement {

	public static function can( string $addon_id, string $tier = 'free' ): bool {
		if ( 'free' === $tier ) {
			return true;
		}

		$required = Plans::addon_plan( $addon_id );
		$current  = self::plan();
		$entitled = Plans::rank( $current ) >= Plans::rank( $required );

		/**
		 * Filter the entitlement decision.
		 *
		 * @param bool   $entitled Whether the addon is entitled.
		 * @param string $addon_id Addon id.
		 * @param string $required Required plan.
		 * @param string $current  Current plan.
		 */
		return (bool) apply_filters( 'seoistic/entitlement', $entitled, $addon_id, $required, $current );
	}

	/**
	 * The current plan: 'free' unless a valid license resolves to a paid plan.
	 */
	public static function plan(): string {
		if ( ! self::license_valid() ) {
			return 'free';
		}

		$product = (int) get_option( 'seoistic_license_product_active', 0 );
		$map     = get_option( 'seoistic_plan_map', array() );
		if ( is_array( $map ) && isset( $map[ $product ] ) ) {
			return (string) $map[ $product ];
		}

		/**
		 * Filter the plan a valid (but unmapped) license resolves to.
		 *
		 * @param string $plan    Default plan.
		 * @param int    $product Licenseistic product id.
		 */
		return (string) apply_filters( 'seoistic/license_plan', 'business', $product );
	}

	public static function is_pro(): bool {
		return self::license_valid();
	}

	/**
	 * Delegates to LicenseClient::is_valid() — the single source of truth for
	 * "is this license currently trusted" (status + expiry + the bounded grace
	 * window for a temporarily-unreachable license server). Duplicating that
	 * logic here previously drifted out of sync with it.
	 */
	private static function license_valid(): bool {
		if ( ! ( new LicenseClient() )->is_valid() ) {
			return false;
		}
		return (bool) apply_filters( 'seoistic/license_valid', true );
	}
}
