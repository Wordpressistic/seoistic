<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Core\PostSeo;

/**
 * SEOISTIC → Content: the site-wide content inventory. A server-side paginated
 * WP_Query table (never the whole library in browser memory) with post-type,
 * issue and score-band filters, search, and sortable columns. The dashboard
 * roadmap drills into this screen via the seo_filter query arg.
 */
final class ContentInventoryPage {

	private const PER_PAGE = 20;

	/** Issue/score-band filters mapped in filter_meta_query(). */
	private const FILTERS = array( 'missing_meta', 'missing_title', 'missing_keyword', 'unscored', 'critical', 'needs_work', 'good', 'excellent' );

	private const SORTABLE = array( 'score', 'modified', 'title' );

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 10 );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Content', 'seoistic' ), __( 'Content', 'seoistic' ), 'manage_options', 'seoistic-content', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Read-only listing filters — no state change, so no nonce required.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filter    = isset( $_GET['seo_filter'] ) ? sanitize_key( wp_unslash( $_GET['seo_filter'] ) ) : '';
		$filter    = in_array( $filter, self::FILTERS, true ) ? $filter : '';
		$post_type = isset( $_GET['seo_type'] ) ? sanitize_key( wp_unslash( $_GET['seo_type'] ) ) : '';
		$search    = isset( $_GET['seo_search'] ) ? sanitize_text_field( wp_unslash( $_GET['seo_search'] ) ) : '';
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$orderby   = in_array( $orderby, self::SORTABLE, true ) ? $orderby : '';
		$order     = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$public_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $public_types['attachment'] );
		if ( '' !== $post_type && ! isset( $public_types[ $post_type ] ) ) {
			$post_type = '';
		}

		$query = new \WP_Query( $this->build_query_args( $filter, $post_type, $search, $orderby, $order, $paged, array_keys( $public_types ) ) );

		View::header( 'seoistic-content', __( 'Content', 'seoistic' ), __( 'Every post, page and product with its SEO score, focus keyword and index state — filter, sort, and jump straight into the SEO workspace.', 'seoistic' ) );

		$this->render_toolbar( $filter, $post_type, $search, $public_types );

		if ( ! $query->have_posts() ) {
			if ( '' !== $filter || '' !== $search || '' !== $post_type ) {
				View::empty_state(
					'filter',
					__( 'No content matches', 'seoistic' ),
					__( 'Nothing matches the current filters. Clear them to see the full inventory.', 'seoistic' ),
					__( 'Clear filters', 'seoistic' ),
					admin_url( 'admin.php?page=seoistic-content' )
				);
			} else {
				View::empty_state(
					'admin-page',
					__( 'No content yet', 'seoistic' ),
					__( 'Publish your first post or page and it will appear here with a live SEO score.', 'seoistic' ),
					__( 'Create a post', 'seoistic' ),
					admin_url( 'post-new.php' )
				);
			}
			View::footer();
			return;
		}

		$this->render_table( $query, $orderby, $order );
		$this->render_pagination( $query, $paged );

		wp_reset_postdata();
		View::footer();
	}

	/**
	 * @param list<string> $types
	 * @return array<string, mixed>
	 */
	private function build_query_args( string $filter, string $post_type, string $search, string $orderby, string $order, int $paged, array $types ): array {
		$args = array(
			'post_type'      => '' !== $post_type ? $post_type : $types,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$meta_query = $this->filter_meta_query( $filter );
		if ( array() !== $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( 'score' === $orderby ) {
			$args['meta_key'] = '_seoistic_score'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $order;
		} elseif ( '' !== $orderby ) {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		} else {
			$args['orderby'] = 'modified';
			$args['order']   = 'DESC';
		}

		return $args;
	}

	/**
	 * @return array<int|string, mixed>
	 */
	private function filter_meta_query( string $filter ): array {
		$missing = static fn( string $key ): array => array(
			'relation' => 'OR',
			array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
			array( 'key' => $key, 'value' => '', 'compare' => '=' ),
		);

		return match ( $filter ) {
			'missing_meta'    => array( $missing( '_seoistic_description' ) ),
			'missing_title'   => array( $missing( '_seoistic_title' ) ),
			'missing_keyword' => array( $missing( '_seoistic_focus_keyword' ) ),
			'unscored'        => array( array( 'key' => '_seoistic_score', 'compare' => 'NOT EXISTS' ) ),
			'critical'        => array( array( 'key' => '_seoistic_score', 'value' => 50, 'compare' => '<', 'type' => 'NUMERIC' ) ),
			'needs_work'      => array( array( 'key' => '_seoistic_score', 'value' => array( 50, 79 ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ) ),
			'good'            => array( array( 'key' => '_seoistic_score', 'value' => array( 80, 89 ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ) ),
			'excellent'       => array( array( 'key' => '_seoistic_score', 'value' => 90, 'compare' => '>=', 'type' => 'NUMERIC' ) ),
			default           => array(),
		};
	}

	/**
	 * @param array<string, \WP_Post_Type> $public_types
	 */
	private function render_toolbar( string $filter, string $post_type, string $search, array $public_types ): void {
		$filters = array(
			''                => __( 'All content', 'seoistic' ),
			'critical'        => __( 'Score below 50', 'seoistic' ),
			'needs_work'      => __( 'Score 50–79', 'seoistic' ),
			'good'            => __( 'Score 80–89', 'seoistic' ),
			'excellent'       => __( 'Score 90+', 'seoistic' ),
			'unscored'        => __( 'Never analyzed', 'seoistic' ),
			'missing_meta'    => __( 'Missing meta description', 'seoistic' ),
			'missing_title'   => __( 'Missing SEO title', 'seoistic' ),
			'missing_keyword' => __( 'Missing focus keyword', 'seoistic' ),
		);
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="seoistic-inventory-toolbar">
			<input type="hidden" name="page" value="seoistic-content">
			<label class="screen-reader-text" for="seoistic-inv-filter"><?php esc_html_e( 'Filter by issue', 'seoistic' ); ?></label>
			<select class="seoistic-filter-select" id="seoistic-inv-filter" name="seo_filter" onchange="this.form.submit()">
				<?php foreach ( $filters as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="seoistic-inv-type"><?php esc_html_e( 'Filter by type', 'seoistic' ); ?></label>
			<select class="seoistic-filter-select" id="seoistic-inv-type" name="seo_type" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All types', 'seoistic' ); ?></option>
				<?php foreach ( $public_types as $slug => $object ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $post_type, $slug ); ?>><?php echo esc_html( $object->labels->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="seoistic-search">
				<label class="screen-reader-text" for="seoistic-inv-search"><?php esc_html_e( 'Search content', 'seoistic' ); ?></label>
				<input type="search" id="seoistic-inv-search" name="seo_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search content…', 'seoistic' ); ?>">
			</div>
		</form>
		<?php
	}

	private function render_table( \WP_Query $query, string $orderby, string $order ): void {
		echo '<div class="seoistic-inventory-wrap">';
		echo '<table class="seoistic-inventory">';
		echo '<thead><tr>';
		echo '<th scope="col">' . $this->sort_link( __( 'Content', 'seoistic' ), 'title', $orderby, $order ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th scope="col">' . $this->sort_link( __( 'SEO score', 'seoistic' ), 'score', $orderby, $order ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th scope="col">' . esc_html__( 'Focus keyword', 'seoistic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Index state', 'seoistic' ) . '</th>';
		echo '<th scope="col">' . $this->sort_link( __( 'Updated', 'seoistic' ), 'modified', $orderby, $order ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'seoistic' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id   = (int) get_the_ID();
			$score     = PostSeo::score( $post_id );
			$keyword   = PostSeo::focus_keyword( $post_id );
			$type      = get_post_type_object( (string) get_post_type() );
			$status    = (string) get_post_status();
			$index     = PostSeo::index_status( $post_id, $status );
			$edit_link = (string) get_edit_post_link( $post_id, 'raw' );

			echo '<tr>';
			echo '<td><a class="seoistic-inventory-title" href="' . esc_url( $edit_link ) . '">' . esc_html( get_the_title() ?: __( '(no title)', 'seoistic' ) ) . '</a>';
			echo '<span class="seoistic-inventory-sub">' . esc_html( $type ? $type->labels->singular_name : (string) get_post_type() );
			if ( 'publish' !== $status ) {
				echo ' · ' . esc_html( ucfirst( $status ) );
			}
			echo '</span></td>';

			echo '<td>';
			if ( $score >= 0 ) {
				echo View::score_ring( $score, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo View::badge( __( 'Not scored', 'seoistic' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</td>';

			echo '<td>' . ( '' !== $keyword ? esc_html( $keyword ) : '<span class="seoistic-kw-missing">' . esc_html__( 'None set', 'seoistic' ) . '</span>' ) . '</td>';

			$index_badge = match ( $index ) {
				'indexable' => View::badge( __( 'Indexable', 'seoistic' ), 'indexable' ),
				'noindex'   => View::badge( __( 'Noindex', 'seoistic' ), 'noindex' ),
				'private'   => View::badge( __( 'Private', 'seoistic' ), 'draft' ),
				default     => View::badge( __( 'Draft', 'seoistic' ), 'draft' ),
			};
			echo '<td>' . $index_badge . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput

			echo '<td><span class="seoistic-inventory-sub">' . esc_html( (string) get_the_modified_date() ) . '</span></td>';
			echo '<td><a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( $edit_link . '#seoistic_seo_panel' ) . '">' . esc_html__( 'Optimize', 'seoistic' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private function sort_link( string $label, string $key, string $current_orderby, string $current_order ): string {
		$next_order = ( $key === $current_orderby && 'DESC' === $current_order ) ? 'asc' : 'desc';
		$url        = add_query_arg( array( 'orderby' => $key, 'order' => $next_order ) );
		$arrow      = $key === $current_orderby ? ( 'DESC' === $current_order ? ' ↓' : ' ↑' ) : '';
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $label . $arrow ) . '</a>';
	}

	private function render_pagination( \WP_Query $query, int $paged ): void {
		$total_pages = (int) $query->max_num_pages;
		$total_items = (int) $query->found_posts;

		echo '<div class="seoistic-pagination">';
		echo '<span class="seoistic-pagination-info">' . esc_html(
			sprintf(
				/* translators: 1: current page, 2: total pages, 3: total items. */
				__( 'Page %1$d of %2$d · %3$d items', 'seoistic' ),
				$paged,
				max( 1, $total_pages ),
				$total_items
			)
		) . '</span>';

		if ( $total_pages > 1 ) {
			echo '<span class="seoistic-pagination-links">';
			if ( $paged > 1 ) {
				echo '<a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( add_query_arg( 'paged', $paged - 1 ) ) . '">' . esc_html__( '← Previous', 'seoistic' ) . '</a>';
			}
			if ( $paged < $total_pages ) {
				echo '<a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( add_query_arg( 'paged', $paged + 1 ) ) . '">' . esc_html__( 'Next →', 'seoistic' ) . '</a>';
			}
			echo '</span>';
		}
		echo '</div>';
	}
}
