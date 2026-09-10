<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AiSearch;

use Wpistic\Seoistic\AI\AiService;
use Wpistic\Seoistic\Core\PostSeo;
use WP_Error;

/**
 * Gateway-backed AEO audits plus deterministic snapshots so scores and fix
 * suggestions survive independent of a single AI response.
 */
final class AeoAuditService {

	public const META_SCORE = '_seoistic_aeo_score';
	public const META_REPORT = '_seoistic_aeo_report';
	public const META_CHECKLIST = '_seoistic_aeo_checklist';

	public function __construct( private ?AiService $ai = null ) {}

	public function audit( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'seoistic_post_not_found', __( 'Post not found.', 'seoistic' ), array( 'status' => 404 ) );
		}

		$snapshot = $this->snapshot( $post_id );
		$service = $this->ai ?? new AiService();
		$result = $service->generate( 'aeo', $service->page_context_from_post( $post_id ) );
		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				(string) ( $result['error_code'] ?? 'seoistic_aeo_failed' ),
				(string) ( $result['error'] ?? __( 'The AEO audit could not be completed.', 'seoistic' ) ),
				(array) ( $result['error_data'] ?? array( 'status' => 502 ) )
			);
		}

		$ai_report = is_array( $result['data'] ?? null ) ? $result['data'] : array();
		$report = $this->merge_report( $snapshot, $ai_report );
		update_post_meta( $post_id, self::META_SCORE, $report['score'] );
		update_post_meta( $post_id, self::META_REPORT, $report );
		update_post_meta( $post_id, self::META_CHECKLIST, $this->default_checklist() );

		return array(
			'success' => true,
			'data' => $report,
			'usage' => is_array( $result['usage'] ?? null ) ? $result['usage'] : array(),
			'cached' => (bool) ( $result['cached'] ?? false ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function snapshot( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$content = (string) $post->post_content;
		$text = wp_strip_all_tags( $content );
		$headings = array();
		preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$heading = trim( wp_strip_all_tags( (string) $match[2] ) );
			if ( '' !== $heading ) {
				$headings[] = array( 'level' => (int) $match[1], 'text' => $heading );
			}
		}

		$updated = strtotime( (string) $post->post_modified_gmt );
		$freshness_days = false !== $updated ? (int) floor( ( time() - $updated ) / DAY_IN_SECONDS ) : null;
		$checks = array(
			'answer_first' => $this->check_answer_first( $text ),
			'faq_presence' => (bool) preg_match( '/(?:^|\n)#{0,6}\s*(?:faq|frequently asked questions)/i', $text ) || false !== stripos( $content, 'faq' ),
			'entity_coverage' => '' !== trim( (string) PostSeo::focus_keyword( $post_id ) ),
			'heading_clarity' => $this->check_heading_clarity( $headings ),
			'freshness' => null !== $freshness_days && $freshness_days <= 180,
		);

		return array(
			'post_id' => $post_id,
			'title' => (string) $post->post_title,
			'edit_url' => (string) get_edit_post_link( $post_id, 'raw' ),
			'score' => (int) round( array_sum( array_map( 'intval', $checks ) ) * 20 ),
			'checks' => $checks,
			'headings' => $headings,
			'freshness_days' => $freshness_days,
			'analyzed_at' => current_time( 'mysql', true ),
			'version' => '1.0',
		);
	}

	public function load_report( int $post_id ): array {
		$report = get_post_meta( $post_id, self::META_REPORT, true );
		return is_array( $report ) ? $report : $this->snapshot( $post_id );
	}

	public function save_checklist( int $post_id, ?array $values ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		$values = is_array( $values ) ? $values : array();
		$checklist = array();
		foreach ( $this->default_checklist() as $key => $item ) {
			$checklist[ $key ] = array(
				'label' => $item['label'],
				'help' => $item['help'],
				'completed' => isset( $values[ $key ] ) && (bool) $values[ $key ],
			);
		}
		return (bool) update_post_meta( $post_id, self::META_CHECKLIST, $checklist );
	}

	/**
	 * @return array<string, array{label:string, help:string}>
	 */
	public static function default_checklist(): array {
		return array(
			'answer_block' => array(
				'label' => __( 'Add a 40–60 word direct answer near the top.', 'seoistic' ),
				'help' => __( 'State who, what, where, when, why, or how before the detailed explanation.', 'seoistic' ),
			),
			'question_headings' => array(
				'label' => __( 'Use natural-language question headings.', 'seoistic' ),
				'help' => __( 'AI systems extract semantically clear sections; avoid clever or vague headings.', 'seoistic' ),
			),
			'faq_schema' => array(
				'label' => __( 'Add an FAQ section with schema-ready questions.', 'seoistic' ),
				'help' => __( 'Pair each concise question with a factual, self-contained answer.', 'seoistic' ),
			),
			'entities' => array(
				'label' => __( 'Define brand, product, and topical entities.', 'seoistic' ),
				'help' => __( 'Explain abbreviations and distinguish similar names or product versions.', 'seoistic' ),
			),
			'fresh_date' => array(
				'label' => __( 'Verify dates, prices, and time-sensitive facts.', 'seoistic' ),
				'help' => __( 'Update the content and visibly communicate its review date.', 'seoistic' ),
			),
			'sources' => array(
				'label' => __( 'Cite primary sources for claims.', 'seoistic' ),
				'help' => __( 'Link the original research, documentation, standard, or official statement.', 'seoistic' ),
			),
			'citation_readiness' => array(
				'label' => __( 'Confirm the page is easy to quote and attribute.', 'seoistic' ),
				'help' => __( 'Use stable URLs, clear page titles, and unambiguous section anchors.', 'seoistic' ),
			),
			'manual_review' => array(
				'label' => __( 'Manually review at least one AI answer for citation quality.', 'seoistic' ),
				'help' => __( 'This checklist records your review; it does not automatically discover citations.', 'seoistic' ),
			),
		);
	}

	private function merge_report( array $snapshot, array $ai_report ): array {
		$score = isset( $ai_report['score'] ) && is_numeric( $ai_report['score'] ) ? max( 0, min( 100, (int) $ai_report['score'] ) ) : (int) $snapshot['score'];
		$suggestions = array();
		$raw_suggestions = isset( $ai_report['suggestions'] ) && is_array( $ai_report['suggestions'] ) ? $ai_report['suggestions'] : array();
		foreach ( $raw_suggestions as $suggestion ) {
			$text = sanitize_text_field( is_array( $suggestion ) ? (string) ( $suggestion['suggestion'] ?? $suggestion['fix'] ?? $suggestion['message'] ?? '' ) : (string) $suggestion );
			if ( '' !== $text ) {
				$suggestions[] = $text;
			}
		}
		foreach ( $snapshot['checks'] as $id => $pass ) {
			if ( ! $pass ) {
				$suggestions[] = $this->local_suggestion( (string) $id );
			}
		}

		$snapshot['score'] = $score;
		$snapshot['ai_score'] = $score;
		$snapshot['snapshot_score'] = (int) $snapshot['score'];
		$snapshot['suggestions'] = array_slice( array_values( array_unique( $suggestions ) ), 0, 12 );
		$snapshot['ai_review'] = $ai_report;
		return $snapshot;
	}

	private function local_suggestion( string $id ): string {
		return match ( $id ) {
			'answer_first' => __( 'Add a concise answer block before the long-form explanation.', 'seoistic' ),
			'faq_presence' => __( 'Add a short FAQ section with the questions users actually ask.', 'seoistic' ),
			'entity_coverage' => __( 'Set a focus keyword and explicitly define the page’s key entities.', 'seoistic' ),
			'heading_clarity' => __( 'Rewrite vague headings as specific question or topic statements.', 'seoistic' ),
			'freshness' => __( 'Review and update time-sensitive facts, then refresh the modified date.', 'seoistic' ),
			default => __( 'Improve the page’s answer structure for AI systems.', 'seoistic' ),
		};
	}

	private function check_answer_first( string $text ): bool {
		$first = trim( (string) mb_substr( $text, 0, 1000 ) );
		$words = count( preg_split( '/\s+/', $first ) ?: array() );
		return $words >= 12 && (
			false !== stripos( $first, ' is ' ) ||
			false !== stripos( $first, ' are ' ) ||
			false !== strpos( $first, ':' ) ||
			(bool) preg_match( '/\b(direct answer|in short|key takeaway|quick answer)\b/i', $first )
		);
	}

	private function check_heading_clarity( array $headings ): bool {
		$useful = 0;
		foreach ( $headings as $heading ) {
			$text = (string) $heading['text'];
			if ( str_word_count( $text ) >= 2 && ! preg_match( '/^(untitled|section|heading|more|conclusion)$/i', $text ) ) {
				++$useful;
			}
		}
		return $useful >= 2;
	}
}
