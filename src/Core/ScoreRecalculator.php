<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

use WP_Post;

/**
 * Recalculates the cached SEO score after a post is saved — never on a plain page
 * load. Runs late (priority 40) so it sees the SEOISTIC metabox fields the same
 * request already persisted.
 */
final class ScoreRecalculator {

	public function register(): void {
		add_action( 'save_post', array( $this, 'maybe_recalculate' ), 40, 2 );
	}

	public function maybe_recalculate( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ), true ) ) {
			return;
		}
		Scorer::recalculate( $post_id );
		DashboardMetrics::flush();
	}
}
