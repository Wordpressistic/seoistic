<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Flags published pages whose SEO score has dropped since the audit before
 * last (Core\PostSeo::save_score() tracks _seoistic_previous_score) AND
 * haven't been touched in a while — a page that's simply mid-edit isn't
 * "decaying", one nobody has revisited in a year that's now scoring worse is.
 * A cheap meta join, computed live rather than cached — unlike LinkGraph's
 * orphan scan, there's no content parsing involved.
 */
final class ContentDecay {

	private const DEFAULT_MONTHS = 12;
	private const DEFAULT_LIMIT  = 50;

	/**
	 * @return array<int, array{id:int, title:string, score:int, previous_score:int, post_modified:string, edit_url:string}>
	 */
	public static function flagged( int $months = self::DEFAULT_MONTHS, int $limit = self::DEFAULT_LIMIT ): array {
		global $wpdb;

		$public_types = get_post_types( array( 'public' => true ) );
		$placeholders = implode( ',', array_fill( 0, count( $public_types ), '%s' ) );
		$cutoff       = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_modified,
					CAST(score.meta_value AS SIGNED) AS score,
					CAST(prev.meta_value AS SIGNED) AS previous_score
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} score ON score.post_id = p.ID AND score.meta_key = '_seoistic_score'
				 INNER JOIN {$wpdb->postmeta} prev ON prev.post_id = p.ID AND prev.meta_key = '_seoistic_previous_score'
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				   AND p.post_modified < %s
				   AND CAST(score.meta_value AS SIGNED) < CAST(prev.meta_value AS SIGNED)
				 ORDER BY (CAST(prev.meta_value AS SIGNED) - CAST(score.meta_value AS SIGNED)) DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $public_types, array( $cutoff, $limit ) )
			),
			ARRAY_A
		);

		$flagged = array();
		foreach ( (array) $rows as $row ) {
			$flagged[] = array(
				'id'             => (int) $row['ID'],
				'title'          => (string) $row['post_title'],
				'score'          => (int) $row['score'],
				'previous_score' => (int) $row['previous_score'],
				'post_modified'  => (string) $row['post_modified'],
				'edit_url'       => (string) get_edit_post_link( (int) $row['ID'], 'raw' ),
			);
		}
		return $flagged;
	}
}
