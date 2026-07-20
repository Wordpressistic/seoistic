<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\License;

/**
 * SEOISTIC plan catalogue + the addon → required-plan map. Single source of truth for
 * pricing and entitlement. Free addons aren't in ADDON_PLANS (default 'free').
 */
final class Plans {

	private const RANK = array( 'free' => 0, 'pro' => 1, 'business' => 2, 'agency' => 3 );

	/** Which paid plan each premium addon belongs to. */
	private const ADDON_PLANS = array(
		'ai'                  => 'pro',
		'schema_pro'          => 'pro',
		'performance'         => 'pro',
		'ai_search'           => 'business',
		'rank_tracker'        => 'business',
		'gsc'                 => 'business',
		'business_automator'  => 'business',
	);

	public static function rank( string $plan ): int {
		return self::RANK[ $plan ] ?? 0;
	}

	public static function addon_plan( string $addon_id ): string {
		return self::ADDON_PLANS[ $addon_id ] ?? 'free';
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function tiers(): array {
		return array(
			'free'     => array(
				'name'     => __( 'Free Forever', 'seoistic' ),
				'price'    => 0,
				'sites'    => __( 'Unlimited personal websites', 'seoistic' ),
				'features' => array(
					__( 'SEO titles, meta, variables, canonicals, robots', 'seoistic' ),
					__( 'XML sitemap, OG/Twitter, breadcrumbs, llms.txt', 'seoistic' ),
					__( 'Schema, Local SEO, WooCommerce SEO, Image SEO', 'seoistic' ),
					__( 'Redirects + 404 monitor, Link Manager, News/Video sitemaps', 'seoistic' ),
					__( 'Yoast / Rank Math / AIOSEO import, Content Audit Gate', 'seoistic' ),
				),
				'note'     => __( 'Destroys the free-plugin competition.', 'seoistic' ),
			),
			'pro'      => array(
				'name'     => __( 'Pro', 'seoistic' ),
				'price'    => 49,
				'sites'    => 1,
				'features' => array(
					__( 'Everything in Free', 'seoistic' ),
					__( 'SEOISTIC AI with your own API key (Ollama, Groq, OpenRouter)', 'seoistic' ),
					__( 'Schema Pro / custom schema builder', 'seoistic' ),
					__( 'Performance + Core Web Vitals monitoring', 'seoistic' ),
					__( 'Priority updates, basic reports', 'seoistic' ),
				),
				'note'     => __( 'Solo site owners and small projects.', 'seoistic' ),
			),
			'business' => array(
				'name'     => __( 'Business', 'seoistic' ),
				'price'    => 199,
				'sites'    => 3,
				'featured' => true,
				'features' => array(
					__( 'Everything in Pro', 'seoistic' ),
					__( 'AI Search Visibility / AEO with custom Hermes brain', 'seoistic' ),
					__( 'Rank tracker + GSC & GA4 dashboard', 'seoistic' ),
					__( 'Scheduled + white-label PDF client reports', 'seoistic' ),
					__( 'Core Web Vitals + uptime/index monitor', 'seoistic' ),
				),
				'copy'     => __( 'Everything WordPress businesses need — AI visibility with your custom knowledge base, rank tracking, client reports, schema, WooCommerce SEO — all for $199/year across 3 sites.', 'seoistic' ),
				'note'     => __( 'Freelancers, agencies, and multi-site owners.', 'seoistic' ),
			),
			'agency'   => array(
				'name'     => __( 'Agency Pro', 'seoistic' ),
				'price'    => 499,
				'sites'    => 10,
				'features' => array(
					__( 'Everything in Business', 'seoistic' ),
					__( '10 site licenses', 'seoistic' ),
					__( 'Agency dashboard + client groups', 'seoistic' ),
					__( 'White-label report branding + custom logo', 'seoistic' ),
					__( 'Client export links, priority support, reseller licensing', 'seoistic' ),
				),
				'note'     => __( 'Large agencies and resellers.', 'seoistic' ),
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function lifetime(): array {
		return array(
			array( 'name' => __( 'Business LTD', 'seoistic' ), 'price' => 599, 'sites' => 3, 'limit' => __( 'First 300–500 buyers only', 'seoistic' ) ),
			array( 'name' => __( 'Agency Pro LTD', 'seoistic' ), 'price' => 999, 'sites' => 10, 'limit' => __( 'First 100 buyers only', 'seoistic' ) ),
		);
	}
}
