<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\License;

/**
 * SEOISTIC plan catalogue + the addon → required-plan map. Single source of truth for
 * pricing and entitlement. Free addons aren't in ADDON_PLANS (default 'free').
 *
 * Aligned 1:1 with the live WPistic commerce catalog (Paddle):
 *  - Seoistic Pro — $79/year — pri_01m1ykarybrkwsnef6dmvmrnb5
 *  - WPistic All-Access — $499/year — pri_01m1ykax1m0cavxy2j7bg5p2my
 *  - WPistic All-Access Lifetime — $999 once — pri_01m1ykawqn9svetfknrf59s0tr
 * A Pro (or higher) license unlocks every premium addon; hosted AI credits and
 * site-seat quotas scale server-side with the plan the license server reports.
 */
final class Plans {

	private const RANK = array( 'free' => 0, 'pro' => 1, 'business' => 2, 'agency' => 3 );

	/** Which paid plan each premium addon belongs to. All unlock with Pro —
	 * higher ranks (All-Access plans) simply grant the same features plus the
	 * rest of the ecosystem. */
	private const ADDON_PLANS = array(
		'ai'                  => 'pro',
		'schema_pro'          => 'pro',
		'performance'         => 'pro',
		'connections'         => 'pro',
		'uptime'              => 'pro',
		'ai_search'           => 'pro',
		'rank_tracker'        => 'pro',
		'gsc'                 => 'pro',
		'business_automator'  => 'pro',
	);

	/** Paddle price IDs the in-plugin checkout opens (client-side overlay; the
	 * client token is public by design). */
	public const CHECKOUT = array(
		'token'        => 'live_223042b83adc74a01d905374cbd',
		'pro'          => 'pri_01m1ykarybrkwsnef6dmvmrnb5',
		'all_access'   => 'pri_01m1ykax1m0cavxy2j7bg5p2my',
		'lifetime'     => 'pri_01m1ykawqn9svetfknrf59s0tr',
		'pricing_page' => 'https://www.wordpressistic.com/pricing/',
	);

	public static function rank( string $plan ): int {
		return self::RANK[ $plan ] ?? 0;
	}

	/** Normalize canonical and legacy plan slugs; unknown plans fail closed. */
	public static function normalize_plan( string $plan ): string {
		return match ( strtolower( trim( $plan ) ) ) {
			'pro', 'starter' => 'pro',
			'business', 'professional' => 'business',
			'agency', 'agency-pro' => 'agency',
			default => 'free',
		};
	}

	public static function addon_plan( string $addon_id ): string {
		return self::ADDON_PLANS[ $addon_id ] ?? 'free';
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function tiers(): array {
		return array(
			'free'       => array(
				'name'     => __( 'Free Forever', 'seoistic' ),
				'price'    => 0,
				'sites'    => __( 'Unlimited personal websites', 'seoistic' ),
				'features' => array(
					__( 'SEO titles, meta, variables, canonicals, robots', 'seoistic' ),
					__( 'XML sitemap, OG/Twitter, breadcrumbs, llms.txt', 'seoistic' ),
					__( 'Schema, Local SEO, WooCommerce SEO, Image SEO', 'seoistic' ),
					__( 'Redirects + 404 monitor, Link Manager, HTML sitemap', 'seoistic' ),
					__( 'Yoast / Rank Math / AIOSEO import, Content Audit Gate', 'seoistic' ),
				),
				'note'     => __( 'Destroys the free-plugin competition.', 'seoistic' ),
			),
			'pro'        => array(
				'name'     => __( 'Pro', 'seoistic' ),
				'price'    => 79,
				'sites'    => 1,
				'featured' => true,
				'price_id' => self::CHECKOUT['pro'],
				'features' => array(
					__( 'Everything in Free', 'seoistic' ),
					__( 'SEOISTIC AI — WPistic AI credits + your own OpenAI-compatible model', 'seoistic' ),
					__( 'AI Search Visibility (AEO) + Rank Tracker + Search Console', 'seoistic' ),
					__( 'Schema Pro builder, Performance/Core Web Vitals, GA4 Connections', 'seoistic' ),
					__( 'Business Automator, Uptime Monitor, fast indexing (Google + IndexNow)', 'seoistic' ),
				),
				'note'     => __( 'Everything SEOistic can do, on one site — $79/year.', 'seoistic' ),
			),
			'all_access' => array(
				'name'     => __( 'WPistic All-Access', 'seoistic' ),
				'price'    => 499,
				'sites'    => __( 'Every WPistic product, included', 'seoistic' ),
				'price_id' => self::CHECKOUT['all_access'],
				'features' => array(
					__( 'SEOistic Pro included — plus every other WPistic product', 'seoistic' ),
					__( 'Insightistic, ChatBotistic, Memberistic, Bridgistic, Verifyistic…', 'seoistic' ),
					__( 'Agency white-label rights across the ecosystem', 'seoistic' ),
					__( 'One account, one license dashboard', 'seoistic' ),
				),
				'note'     => __( 'The whole WordPressistic stack for less than its parts.', 'seoistic' ),
				'bundle'   => true,
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function lifetime(): array {
		return array(
			array(
				'name'     => __( 'All-Access Lifetime', 'seoistic' ),
				'price'    => 999,
				'sites'    => 0,
				'price_id' => self::CHECKOUT['lifetime'],
				'limit'    => __( 'Founding members only — everything, forever.', 'seoistic' ),
			),
		);
	}
}
