<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

use Wpistic\Seoistic\Core\HtaccessManager;
use Wpistic\Seoistic\Core\PostSeo;
use Wpistic\Seoistic\Core\Scorer;
use Wpistic\Seoistic\Core\Sitemaps;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The `seoistic/v1` REST namespace: every AI generator, the post/site audit
 * actions, and the apply actions for robots.txt / llms.txt / .htaccess. Every
 * route requires at least `edit_posts`; anything site-wide or that writes files
 * requires `manage_options`. WordPress's built-in cookie + X-WP-Nonce check
 * (rest_cookie_check_errors, wired by core) covers CSRF for all of these.
 */
final class RestController {

	private const NS = 'seoistic/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$ai_routes = array(
			'ai/generate-title'          => 'title',
			'ai/generate-description'    => 'description',
			'ai/generate-keywords'       => 'keywords',
			'ai/generate-schema'         => 'schema',
			'ai/generate-alt'            => 'alt',
			'ai/internal-links'          => 'internal_links',
			'ai/optimize-content'        => 'optimize_content',
			'ai/full-page-optimization'  => 'full_page_optimization',
		);
		foreach ( $ai_routes as $route => $type ) {
			register_rest_route(
				self::NS,
				'/' . $route,
				array(
					'methods'             => 'POST',
					'callback'            => fn( WP_REST_Request $r ) => $this->handle_post_ai( $r, $type ),
					'permission_callback' => array( $this, 'can_edit_post_in_request' ),
					'args'                => array(
						'post_id' => array( 'required' => true, 'type' => 'integer' ),
					),
				)
			);
		}

		register_rest_route(
			self::NS,
			'/ai/generate-robots',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_generate_robots' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/generate-htaccess',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_generate_htaccess' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/generate-llms',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_generate_llms' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/audit/post',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_audit_post' ),
				'permission_callback' => array( $this, 'can_edit_post_in_request' ),
				'args'                => array(
					'post_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/audit/site',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_audit_site' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/tools/apply-robots',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_apply_robots' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/tools/apply-llms',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_apply_llms' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/tools/apply-htaccess',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_apply_htaccess' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		foreach ( array( 'bulk-meta', 'bulk-alt', 'bulk-internal-links', 'bulk-aeo' ) as $route ) {
			register_rest_route(
				self::NS,
				'/tools/' . $route,
				array(
					'methods'             => 'POST',
					'callback'            => fn( WP_REST_Request $r ) => $this->handle_bulk( $r, $route ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);
		}

		register_rest_route(
			self::NS,
			'/tools/ping-sitemap',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_ping_sitemap' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/tools/audit-report',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_audit_report' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Live, non-persisting analysis of draft field values (post editor).
		register_rest_route(
			self::NS,
			'/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_analyze' ),
				'permission_callback' => array( $this, 'can_edit_post_in_request' ),
				'args'                => array(
					'post_id'       => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
					'title'         => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'description'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
					'focus_keyword' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					// Raw editor HTML — only ever parsed by the Scorer, never stored or echoed.
					'content'       => array( 'type' => 'string' ),
				),
			)
		);

		// Command-palette content search.
		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'q'        => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && mb_strlen( trim( $value ) ) >= 2 && mb_strlen( $value ) <= 100,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array( 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 20 ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function can_edit_post_in_request( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}
		return true;
	}

	/* -------------------------------------------------------------- */
	/* Post-scoped AI generators                                        */
	/* -------------------------------------------------------------- */

	private function handle_post_ai( WP_REST_Request $request, string $type ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'seoistic_not_found', __( 'Post not found.', 'seoistic' ), array( 'status' => 404 ) );
		}

		$service = new AiService();
		$result  = $service->generate( $type, $service->page_context_from_post( $post_id ) );

		if ( ! $result['success'] ) {
			return new WP_Error( 'seoistic_ai_error', $result['error'], array( 'status' => 502 ) );
		}

		return new WP_REST_Response( array( 'success' => true, 'data' => $result['data'] ), 200 );
	}

	/* -------------------------------------------------------------- */
	/* Site-wide file generators (preview only — apply is separate)     */
	/* -------------------------------------------------------------- */

	public function handle_generate_robots( WP_REST_Request $request ) {
		return $this->generate_site_wide( 'robots' );
	}

	public function handle_generate_htaccess( WP_REST_Request $request ) {
		return $this->generate_site_wide( 'htaccess' );
	}

	public function handle_generate_llms( WP_REST_Request $request ) {
		return $this->generate_site_wide( 'llms' );
	}

	private function generate_site_wide( string $type ) {
		$service = new AiService();
		$result  = $service->generate( $type, array( 'title' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );

		if ( ! $result['success'] ) {
			return new WP_Error( 'seoistic_ai_error', $result['error'], array( 'status' => 502 ) );
		}
		return new WP_REST_Response( array( 'success' => true, 'data' => $result['data'] ), 200 );
	}

	/* -------------------------------------------------------------- */
	/* Audit                                                            */
	/* -------------------------------------------------------------- */

	public function handle_audit_post( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$score   = Scorer::recalculate( $post_id );
		return new WP_REST_Response( array( 'success' => true, 'data' => array( 'score' => $score ) ), 200 );
	}

	/**
	 * Kicks off (does not run inline) — the dashboard's batched admin-ajax runner is
	 * the actual site-wide audit executor; this endpoint exists for parity with the
	 * documented API and for external/automation use.
	 */
	public function handle_audit_site( WP_REST_Request $request ) {
		$ids = get_posts(
			array(
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 20,
			)
		);
		foreach ( $ids as $post_id ) {
			Scorer::recalculate( (int) $post_id );
		}
		return new WP_REST_Response( array( 'success' => true, 'data' => array( 'processed' => count( $ids ) ) ), 200 );
	}

	/* -------------------------------------------------------------- */
	/* Apply actions — nonce + manage_options already enforced by the   */
	/* route's permission_callback; these never write without that.    */
	/* -------------------------------------------------------------- */

	public function handle_apply_robots( WP_REST_Request $request ) {
		$content = (string) $request->get_param( 'content' );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'seoistic_empty', __( 'Nothing to apply.', 'seoistic' ), array( 'status' => 400 ) );
		}
		update_option( 'seoistic_robots_rules', wp_kses( $content, array() ) );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function handle_apply_llms( WP_REST_Request $request ) {
		$content = (string) $request->get_param( 'content' );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'seoistic_empty', __( 'Nothing to apply.', 'seoistic' ), array( 'status' => 400 ) );
		}
		update_option( 'seoistic_llms_txt_content', wp_kses( $content, array() ) );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function handle_apply_htaccess( WP_REST_Request $request ) {
		$content = (string) $request->get_param( 'content' );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'seoistic_empty', __( 'Nothing to apply.', 'seoistic' ), array( 'status' => 400 ) );
		}

		$result = ( new HtaccessManager() )->apply( wp_kses( $content, array() ) );
		if ( ! $result['success'] ) {
			return new WP_Error( 'seoistic_htaccess_error', $result['error'], array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'success' => true, 'data' => array( 'backup' => $result['backup'] ) ), 200 );
	}

	/* -------------------------------------------------------------- */
	/* Bulk AI actions — Meta Bulk Generator, Image Alt Generator,      */
	/* Internal Link Builder, AI Search Visibility Analyzer.            */
	/* Each processes a small batch per request; the client repeats     */
	/* the call until `done`, showing live progress.                    */
	/* -------------------------------------------------------------- */

	private const BULK_BATCH_SIZE = 5;

	public function handle_bulk( WP_REST_Request $request, string $route ) {
		$offset = absint( $request->get_param( 'offset' ) );

		return match ( $route ) {
			'bulk-meta' => $this->bulk_meta( $offset ),
			'bulk-alt' => $this->bulk_alt( $offset ),
			'bulk-internal-links' => $this->bulk_report( $offset, 'internal_links', 'seoistic_report_internal_links' ),
			'bulk-aeo' => $this->bulk_report( $offset, 'aeo', 'seoistic_report_aeo' ),
			default => new WP_Error( 'seoistic_unknown_tool', __( 'Unknown tool.', 'seoistic' ), array( 'status' => 400 ) ),
		};
	}

	/**
	 * @return array{ids:list<int>, total:int}
	 */
	private function candidate_ids( string $criteria, int $offset ): array {
		global $wpdb;
		$limit = self::BULK_BATCH_SIZE;

		if ( 'missing_meta' === $criteria ) {
			$public_types = get_post_types( array( 'public' => true ) );
			$placeholders = implode( ',', array_fill( 0, count( $public_types ), '%s' ) );
			$sql_where    = "p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				AND p.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_seoistic_title' AND meta_value <> '' )";
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$sql_where}", $public_types ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE {$sql_where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", array_merge( $public_types, array( $limit, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array( 'ids' => array_map( 'intval', $ids ), 'total' => $total );
		}

		if ( 'missing_alt' === $criteria ) {
			$sql_where = "p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
				AND p.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_image_alt' AND meta_value <> '' )";
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$sql_where}" ); // phpcs:ignore WordPress.DB
			$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE {$sql_where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore WordPress.DB
			return array( 'ids' => array_map( 'intval', $ids ), 'total' => $total );
		}

		// 'low_score' — public posts scoring under 80 (or never scored), used for the
		// internal-link and AEO advisory reports.
		$public_types = get_post_types( array( 'public' => true ) );
		$placeholders = implode( ',', array_fill( 0, count( $public_types ), '%s' ) );
		$sql_where    = "p.post_status = 'publish' AND p.post_type IN ({$placeholders})
			AND p.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_seoistic_score' AND CAST(meta_value AS UNSIGNED) >= 80 )";
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$sql_where}", $public_types ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE {$sql_where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", array_merge( $public_types, array( $limit, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'ids' => array_map( 'intval', $ids ), 'total' => $total );
	}

	private function bulk_meta( int $offset ) {
		$batch   = $this->candidate_ids( 'missing_meta', $offset );
		$service = new AiService();
		$updated = 0;

		foreach ( $batch['ids'] as $post_id ) {
			$result = $service->generate( 'full_page_optimization', $service->page_context_from_post( $post_id ) );
			if ( ! $result['success'] ) {
				continue;
			}
			$data = $result['data'];
			$fields = array();
			if ( ! empty( $data['title'] ) ) {
				$fields['_seoistic_title'] = sanitize_text_field( (string) $data['title'] );
			}
			if ( ! empty( $data['meta_description'] ) ) {
				$fields['_seoistic_description'] = sanitize_textarea_field( (string) $data['meta_description'] );
			}
			if ( ! empty( $data['focus_keywords'][0] ) ) {
				$fields['_seoistic_focus_keyword'] = sanitize_text_field( (string) $data['focus_keywords'][0] );
			}
			if ( array() !== $fields ) {
				PostSeo::save( $post_id, $fields );
				Scorer::recalculate( $post_id );
				++$updated;
			}
		}

		return $this->bulk_response( $offset, count( $batch['ids'] ), $batch['total'], array( 'updated' => $updated ) );
	}

	private function bulk_alt( int $offset ) {
		$batch   = $this->candidate_ids( 'missing_alt', $offset );
		$service = new AiService();
		$updated = 0;

		foreach ( $batch['ids'] as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment ) {
				continue;
			}
			$context = array(
				'title'     => $attachment->post_title,
				'content'   => (string) ( $attachment->post_excerpt ?: $attachment->post_content ),
				'page_type' => 'image',
				'url'       => (string) wp_get_attachment_url( $attachment_id ),
			);
			$result = $service->generate( 'alt', $context );
			if ( $result['success'] && ! empty( $result['data']['alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $result['data']['alt_text'] ) );
				++$updated;
			}
		}

		return $this->bulk_response( $offset, count( $batch['ids'] ), $batch['total'], array( 'updated' => $updated ) );
	}

	/**
	 * Advisory-only bulk actions: results are collected into a transient report
	 * rather than auto-applied — internal links and AEO suggestions need a human
	 * to place them.
	 */
	private function bulk_report( int $offset, string $ai_type, string $transient_key ) {
		$batch   = $this->candidate_ids( 'low_score', $offset );
		$service = new AiService();
		$report  = get_transient( $transient_key );
		$report  = is_array( $report ) ? $report : array();

		foreach ( $batch['ids'] as $post_id ) {
			$result = $service->generate( $ai_type, $service->page_context_from_post( $post_id ) );
			if ( $result['success'] ) {
				$report[] = array(
					'post_id'     => $post_id,
					'title'       => get_the_title( $post_id ),
					'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
					'suggestions' => $result['data']['suggestions'] ?? array(),
				);
			}
		}
		set_transient( $transient_key, $report, HOUR_IN_SECONDS );

		$done = ( $offset + count( $batch['ids'] ) ) >= $batch['total'] || array() === $batch['ids'];
		$response = $this->bulk_response( $offset, count( $batch['ids'] ), $batch['total'], array() );
		if ( $done && $response instanceof WP_REST_Response ) {
			$data          = $response->get_data();
			$data['report'] = $report;
			$response->set_data( $data );
		}
		return $response;
	}

	private function bulk_response( int $offset, int $processed, int $total, array $extra ) {
		$next_offset = $offset + $processed;
		$done        = 0 === $processed || $next_offset >= $total;
		return new WP_REST_Response(
			array_merge(
				array(
					'success'     => true,
					'processed'   => $next_offset,
					'total'       => $total,
					'percent'     => $total > 0 ? min( 100, ( $next_offset / $total ) * 100 ) : 100,
					'done'        => $done,
					'next_offset' => $next_offset,
				),
				$extra
			),
			200
		);
	}

	/* -------------------------------------------------------------- */
	/* Live analysis + palette search                                   */
	/* -------------------------------------------------------------- */

	/**
	 * Deterministic scoring of draft (unsaved) values — same checks as the
	 * persisted score, nothing is written. Persistence still happens only on
	 * save_post.
	 */
	public function handle_analyze( WP_REST_Request $request ) {
		$post = get_post( absint( $request->get_param( 'post_id' ) ) );
		if ( ! $post ) {
			return new WP_Error( 'seoistic_not_found', __( 'Post not found.', 'seoistic' ), array( 'status' => 404 ) );
		}

		$overrides = array();
		foreach ( array( 'title', 'description', 'focus_keyword', 'content' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$overrides[ $key ] = (string) $value;
			}
		}

		$result = Scorer::analyze( $post, $overrides );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'score'       => $result['score'],
					'checks'      => $result['checks'],
					'version'     => Scorer::VERSION,
					'analyzed_at' => current_time( 'mysql' ),
				),
			),
			200
		);
	}

	/**
	 * Content search for the command palette. Results are filtered per item by
	 * edit_post so the palette never lists content the user can't open.
	 */
	public function handle_search( WP_REST_Request $request ) {
		$term     = (string) $request->get_param( 'q' );
		$per_page = min( 20, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

		$query = new \WP_Query(
			array(
				's'              => $term,
				'post_type'      => array_values( get_post_types( array( 'public' => true ) ) ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => $per_page,
				'no_found_rows'  => true,
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$type_object = get_post_type_object( $post->post_type );
			$results[]   = array(
				'id'         => (int) $post->ID,
				'title'      => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'seoistic' ),
				'type'       => $post->post_type,
				'type_label' => $type_object ? (string) $type_object->labels->singular_name : $post->post_type,
				'status'     => $post->post_status,
				'score'      => PostSeo::score( (int) $post->ID ),
				'edit_url'   => (string) get_edit_post_link( (int) $post->ID, 'raw' ),
			);
		}

		return new WP_REST_Response( array( 'success' => true, 'data' => array( 'results' => $results ) ), 200 );
	}

	/* -------------------------------------------------------------- */
	/* Sitemap ping + audit report                                      */
	/* -------------------------------------------------------------- */

	public function handle_ping_sitemap( WP_REST_Request $request ) {
		$result = Sitemaps::ping();
		return new WP_REST_Response(
			array( 'success' => $result['success'], 'data' => array( 'status' => $result['status'] ?? 0, 'sitemap' => $result['sitemap'] ), 'error' => $result['error'] ?? '' ),
			200
		);
	}

	public function handle_audit_report( WP_REST_Request $request ) {
		global $wpdb;
		$public_types = get_post_types( array( 'public' => true ) );
		$placeholders = implode( ',', array_fill( 0, count( $public_types ), '%s' ) );

		$worst = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, CAST(m.meta_value AS UNSIGNED) AS score FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_seoistic_score'
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				 ORDER BY score ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			),
			ARRAY_A
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'generated_at' => current_time( 'mysql' ),
					'worst_pages'  => array_map(
						static fn( array $row ): array => array(
							'id'    => (int) $row['ID'],
							'title' => $row['post_title'],
							'score' => (int) $row['score'],
						),
						(array) $worst
					),
				),
			),
			200
		);
	}
}
