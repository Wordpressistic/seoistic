<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\SeoCore\Audit\AuditReport;
use Wpistic\SeoCore\Audit\Auditor;
use Wpistic\SeoCore\Audit\Finding;
use Wpistic\SeoCore\Audit\PageInput;
use Wpistic\SeoCore\Audit\Severity;
use Wpistic\Seoistic\Core\PostSeo;
use Wpistic\Seoistic\Module\AbstractModule;

/**
 * The pre-publish Content Audit Gate: blocks Publish (moves the post to Pending
 * Review instead) when hard-fail checks exist — title/meta length, single H1,
 * image alt text, and banned words. The scored checklist itself is shown in the
 * SEOISTIC panel's Audit tab (Admin\SeoMetabox); this module only enforces the gate.
 */
final class AuditGateModule extends AbstractModule {

	private const MIN_WORDS = array(
		'wpistic_tour'        => 1500,
		'wpistic_destination' => 2500,
		'wpistic_experience'  => 800,
	);

	public function id(): string {
		return 'audit';
	}

	public function name(): string {
		return __( 'Content Audit Gate', 'seoistic' );
	}

	public function description(): string {
		return __( 'Blocks Publish when hard-fail SEO checks exist — title/meta length, single H1, image alt text, banned words. Unique to SEOISTIC.', 'seoistic' );
	}

	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'block_non_compliant_publish' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'blocked_notice' ) );
	}

	/**
	 * Prevent publish when hard-fail audit findings exist. This is intentionally
	 * server-side; the editor UI score is not a launch gate by itself.
	 *
	 * @param array<string, mixed> $data    Sanitized post data.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public function block_non_compliant_publish( array $data, array $postarr ): array {
		if ( is_admin() && ! empty( $data['post_status'] ) && 'publish' === $data['post_status'] && ! empty( $data['post_type'] ) ) {
			$post_type = (string) $data['post_type'];
			if ( in_array( $post_type, get_post_types( array( 'public' => true ) ), true ) ) {
				$post_id   = absint( $postarr['ID'] ?? 0 );
				$seo_title = $post_id ? PostSeo::title( $post_id ) : '';
				$seo_desc  = $post_id ? PostSeo::description( $post_id ) : '';
				$focus_kw  = $post_id ? PostSeo::focus_keyword( $post_id ) : '';

				// The SEOISTIC metabox submits its fields on the same request, before
				// save_post persists them — read those in-flight values when present.
				if ( isset( $_POST['seoistic_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$seo_title = sanitize_text_field( wp_unslash( $_POST['seoistic_title'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}
				if ( isset( $_POST['seoistic_description'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$seo_desc = sanitize_textarea_field( wp_unslash( $_POST['seoistic_description'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}
				if ( isset( $_POST['seoistic_focus_keyword'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$focus_kw = sanitize_text_field( wp_unslash( $_POST['seoistic_focus_keyword'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}

				$report  = $this->audit_values(
					$post_id,
					$post_type,
					(string) ( $data['post_title'] ?? '' ),
					(string) ( $data['post_content'] ?? '' ),
					$seo_title,
					$seo_desc,
					$focus_kw
				);
				if ( ! $report->passed() ) {
					$data['post_status'] = 'pending';
					set_transient( 'seoistic_publish_blocked_' . get_current_user_id(), $this->format_failures( $report ), 60 );
				}
			}
		}

		return $data;
	}

	public function blocked_notice(): void {
		$message = get_transient( 'seoistic_publish_blocked_' . get_current_user_id() );
		if ( ! $message ) {
			return;
		}
		delete_transient( 'seoistic_publish_blocked_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'SEOISTIC blocked publishing.', 'seoistic' ) . '</strong> ' . esc_html( (string) $message ) . '</p></div>';
	}

	private function audit_values( int $post_id, string $post_type, string $post_title, string $content, string $seo_title, string $desc, string $keyword ): AuditReport {
		$title = '' !== $seo_title ? $seo_title : $post_title;

		// The theme renders the title as the page H1, so effective H1 = 1 + any in content.
		$h1 = 1 + (int) preg_match_all( '/<h1[\s>]/i', $content );

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $links );
		$internal = 0;
		foreach ( $links[1] as $href ) {
			$link_host = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $link_host || $link_host === $host ) {
				++$internal;
			}
		}

		preg_match_all( '/<img\b[^>]*>/i', $content, $images );
		$total   = count( $images[0] );
		$missing = 0;
		foreach ( $images[0] as $img ) {
			if ( ! preg_match( '/\balt=["\'][^"\']+["\']/i', $img ) ) {
				++$missing;
			}
		}

		$page = new PageInput(
			title: $title,
			metaDescription: $desc,
			content: $content,
			focusKeyword: $keyword,
			h1Count: $h1,
			internalLinks: $internal,
			imagesTotal: $total,
			imagesMissingAlt: $missing,
			hasSchema: true,
			hasOpenGraph: true,
			minWords: self::MIN_WORDS[ $post_type ] ?? 300
		);

		$banned = $this->banned_words();

		$report = ( new Auditor() )->run( $page, $banned );
		return new AuditReport( array_merge( $report->findings, $this->brother_tours_findings( $content, $title ) ) );
	}

	/**
	 * @return list<string>
	 */
	private function banned_words(): array {
		$defaults = array(
			'authentic',
			'immersive',
			'meaningful',
			'local expertise',
			'stunning',
			'magical',
			'hidden gem',
			'discover',
			'boutique',
			'curated',
			'bespoke',
			'unforgettable',
			'passionate',
			'world-class',
			'premier',
			'award-winning',
			'luxury',
			'explore',
			'journey of a lifetime',
			'once-in-a-lifetime',
			'your budget',
			'envelope',
			'professional',
			'expert',
			'expertise',
		);
		$custom = array_values( array_filter( array_map( 'trim', explode( ',', (string) get_option( 'seoistic_banned_words', '' ) ) ) ) );
		return array_values( array_unique( array_merge( $defaults, $custom ) ) );
	}

	/**
	 * @return list<Finding>
	 */
	private function brother_tours_findings( string $content, string $title ): array {
		$text = wp_strip_all_tags( $title . ' ' . $content );

		$aggregate = false === stripos( $content, 'AggregateRating' );
		$pricing   = ! preg_match( '/From\s+USD\s+\d+/i', $text ) || (bool) preg_match( '/From\s+USD\s+\d+[^.\\n]*price confirmed on request/i', $text );
		$capacity  = ! preg_match( '/\b(500|five hundred)\b[^.\\n]*(guest|traveler|people|visitor)/i', $text );
		$we_do_not = ! preg_match( '/\bWe do not\b/i', $text ) || false !== stripos( $text, 'We do not sell journeys. We share the country we were born in.' );

		return array(
			new Finding(
				'no_aggregate_rating',
				'No AggregateRating before review threshold',
				$aggregate ? Severity::Pass : Severity::Fail,
				$aggregate ? '' : 'AggregateRating is blocked until 100+ verified reviews.'
			),
			new Finding(
				'pricing_phrase',
				'Pricing phrase',
				$pricing ? Severity::Pass : Severity::Fail,
				$pricing ? '' : 'Use: From USD X per person · price confirmed on request.'
			),
			new Finding(
				'capacity_scope',
				'Capacity scope',
				$capacity ? Severity::Pass : Severity::Fail,
				$capacity ? '' : 'Use per-tour departure limits, not a company-wide guest count.'
			),
			new Finding(
				'banned_construction_we_do_not',
				'Banned construction check',
				$we_do_not ? Severity::Pass : Severity::Fail,
				$we_do_not ? '' : 'Only the locked Brother Tours phrase may use "We do not".'
			),
		);
	}

	private function format_failures( AuditReport $report ): string {
		$labels = array_map(
			static fn ( Finding $finding ): string => $finding->label,
			$report->fails()
		);
		return sprintf(
			/* translators: %s: comma-separated failed audit items. */
			__( 'The post was moved to Pending Review. Failed checks: %s', 'seoistic' ),
			implode( ', ', $labels )
		);
	}
}
