<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Core\PostSeo;
use WP_Query;

/**
 * SEO Score / Focus Keyword / SEO Title / Index Status columns on every public post
 * type's list table (Posts, Pages, and any registered CPT with public => true).
 */
final class SeoColumns {

	public function register(): void {
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'sortable' ) );
		}
		add_action( 'pre_get_posts', array( $this, 'sort_by_score' ) );
	}

	/**
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['seoistic_score']         = __( 'SEO Score', 'seoistic' );
				$new['seoistic_focus_keyword'] = __( 'Focus Keyword', 'seoistic' );
				$new['seoistic_title']         = __( 'SEO Title', 'seoistic' );
				$new['seoistic_index_status']  = __( 'Index Status', 'seoistic' );
			}
		}
		return $new;
	}

	public function render( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'seoistic_score':
				$this->render_score( $post_id );
				break;
			case 'seoistic_focus_keyword':
				$this->render_focus_keyword( $post_id );
				break;
			case 'seoistic_title':
				$this->render_title( $post_id );
				break;
			case 'seoistic_index_status':
				$this->render_index_status( $post_id );
				break;
		}
	}

	private function render_score( int $post_id ): void {
		$score = PostSeo::score( $post_id );
		if ( $score < 0 ) {
			echo View::badge( __( 'Not scored', 'seoistic' ), 'neutral' );
			return;
		}
		echo View::score_ring( $score, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private function render_focus_keyword( int $post_id ): void {
		$keyword = PostSeo::focus_keyword( $post_id );
		if ( '' === $keyword ) {
			echo '<span class="seoistic-kw-missing">' . esc_html__( 'Missing', 'seoistic' ) . '</span>';
			return;
		}
		echo esc_html( $keyword );
	}

	private function render_title( int $post_id ): void {
		$title = PostSeo::title( $post_id );
		echo '' !== $title ? esc_html( $title ) : '<span class="seoistic-kw-missing">&#8212;</span>';
	}

	private function render_index_status( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$status = PostSeo::index_status( $post_id, $post->post_status );
		switch ( $status ) {
			case 'indexable':
				echo View::badge( __( 'Indexable', 'seoistic' ), 'indexable' );
				break;
			case 'noindex':
				echo View::badge( __( 'Noindex', 'seoistic' ), 'noindex' );
				break;
			case 'private':
				echo View::badge( __( 'Private', 'seoistic' ), 'draft' );
				break;
			default:
				echo View::badge( __( 'Draft', 'seoistic' ), 'draft' );
				break;
		}
	}

	/**
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function sortable( array $columns ): array {
		$columns['seoistic_score'] = 'seoistic_score';
		return $columns;
	}

	public function sort_by_score( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'seoistic_score' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', '_seoistic_score' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->set( 'orderby', 'meta_value_num' );
	}
}
