<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Product schema (price + availability) for WooCommerce — free.
 */
final class WooCommerceModule extends AbstractModule {

	public function id(): string {
		return 'woocommerce';
	}

	public function name(): string {
		return __( 'WooCommerce SEO', 'seoistic' );
	}

	public function description(): string {
		return __( 'Product schema with price and stock, plus product Open Graph — free (Yoast charges $178.80/yr).', 'seoistic' );
	}

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_filter( 'seoistic/schema_nodes', array( $this, 'product' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes
	 * @return array<int, array<string, mixed>>
	 */
	public function product( $nodes ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return $nodes;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return $nodes;
		}
		$nodes[] = array(
			'@type'  => 'Product',
			'name'   => $product->get_name(),
			'sku'    => $product->get_sku(),
			'offers' => array(
				'@type'         => 'Offer',
				'price'         => $product->get_price(),
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			),
		);
		return $nodes;
	}
}
