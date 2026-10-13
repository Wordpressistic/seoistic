<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Admin\View;
use Wpistic\Seoistic\Core\PostSeo;
use Wpistic\Seoistic\Module\AbstractModule;

/**
 * One-click import from Yoast SEO, Rank Math and AIOSEO — meta, focus keyword,
 * canonical, noindex and Open Graph, plus a best-effort schema-type importer. Each
 * card runs as a batched AJAX import (admin-ajax `seoistic_import_batch`) so large
 * libraries never time out a single request.
 */
final class MigrationModule extends AbstractModule {

	private const BATCH_SIZE = 50;

	/**
	 * @return array<string, array{label:string, constant:string, meta: array<string,string>}>
	 */
	private const SOURCES = array(
		'yoast'    => array(
			'label'    => 'Yoast SEO',
			'constant' => 'WPSEO_VERSION',
			'meta'     => array(
				'title'         => '_yoast_wpseo_title',
				'description'   => '_yoast_wpseo_metadesc',
				'focus_keyword' => '_yoast_wpseo_focuskw',
				'canonical'     => '_yoast_wpseo_canonical',
				'og_title'      => '_yoast_wpseo_opengraph-title',
				'og_description' => '_yoast_wpseo_opengraph-description',
				'og_image'      => '_yoast_wpseo_opengraph-image',
				'noindex'       => '_yoast_wpseo_meta-robots-noindex',
			),
		),
		'rankmath' => array(
			'label'    => 'Rank Math',
			'constant' => 'RANK_MATH_VERSION',
			'meta'     => array(
				'title'         => 'rank_math_title',
				'description'   => 'rank_math_description',
				'focus_keyword' => 'rank_math_focus_keyword',
				'canonical'     => 'rank_math_canonical_url',
				'og_title'      => 'rank_math_facebook_title',
				'og_description' => 'rank_math_facebook_description',
				'og_image'      => 'rank_math_facebook_image',
				'noindex'       => 'rank_math_robots',
			),
		),
		'aioseo'   => array(
			'label'    => 'All in One SEO',
			'constant' => 'AIOSEO_VERSION',
			'meta'     => array(
				'title'         => '_aioseo_title',
				'description'   => '_aioseo_description',
				'focus_keyword' => '_aioseo_keyphrases',
				'canonical'     => '_aioseo_canonical_url',
				'og_title'      => '_aioseo_og_title',
				'og_description' => '_aioseo_og_description',
				'og_image'      => '_aioseo_og_image_custom_url',
				'noindex'       => '_aioseo_robots_noindex',
			),
		),
	);

	public function id(): string {
		return 'migration';
	}

	public function name(): string {
		return __( 'Migration & Import', 'seoistic' );
	}

	public function description(): string {
		return __( 'Import titles, descriptions, focus keywords, canonicals, noindex and Open Graph from Yoast, Rank Math or AIOSEO — free.', 'seoistic' );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 31 );
		add_action( 'wp_ajax_seoistic_import_batch', array( $this, 'ajax_import_batch' ) );
		add_action( 'admin_notices', array( $this, 'competitor_notice' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Import', 'seoistic' ), __( 'Import', 'seoistic' ), 'manage_options', 'seoistic-import', array( $this, 'render' ) );
	}

	/**
	 * Warn (once per screen) when a competitor SEO plugin is active alongside
	 * SEOISTIC — running two SEO plugins can duplicate meta tags.
	 */
	public function competitor_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'seoistic' ) ) {
			return;
		}
		$active = array();
		foreach ( self::SOURCES as $source => $config ) {
			if ( defined( $config['constant'] ) ) {
				$active[] = $config['label'];
			}
		}
		if ( array() === $active ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html(
			sprintf(
				/* translators: %s: comma-separated list of active competitor plugins. */
				__( 'Another SEO plugin is active (%s). SEOISTIC can import your data, but running multiple SEO plugins can duplicate meta tags.', 'seoistic' ),
				implode( ', ', $active )
			)
		) . '</p></div>';
	}

	private function is_detected( string $source ): bool {
		$config = self::SOURCES[ $source ];
		if ( defined( $config['constant'] ) ) {
			return true;
		}
		return $this->preview_count( $source ) > 0;
	}

	private function preview_count( string $source ): int {
		global $wpdb;
		$key = self::SOURCES[ $source ]['meta']['title'];
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", // phpcs:ignore WordPress.DB
				$key
			)
		);
	}

	private function schema_preview_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('rank_math_rich_snippet','_aioseo_schema_type') AND meta_value <> ''" // phpcs:ignore WordPress.DB
		);
	}

	private function social_preview_count(): int {
		global $wpdb;
		$keys = array();
		foreach ( self::SOURCES as $config ) {
			$keys[] = $config['meta']['og_title'];
		}
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders}) AND meta_value <> ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$keys
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::header( 'seoistic-import', __( 'Import SEO Data', 'seoistic' ), __( 'Bring your existing SEO data over from another plugin, or migrate redirects. Every import only fills in fields SEOISTIC does not already have — nothing is overwritten.', 'seoistic' ) );

		echo '<div class="seoistic-tool-grid">';
		foreach ( self::SOURCES as $source => $config ) {
			$detected = $this->is_detected( $source );
			$count    = $this->preview_count( $source );
			$this->tool_card(
				'download',
				sprintf(
					/* translators: %s: source plugin name. */
					__( 'Import from %s', 'seoistic' ),
					$config['label']
				),
				__( 'Title, description, focus keyword, canonical, noindex and Open Graph.', 'seoistic' ),
				$detected,
				$count,
				$source
			);
		}

		$this->tool_card(
			'schema',
			__( 'Import schema templates', 'seoistic' ),
			__( 'Best-effort import of the primary schema type chosen in Rank Math or AIOSEO.', 'seoistic' ),
			$this->schema_preview_count() > 0,
			$this->schema_preview_count(),
			'schema'
		);

		$this->tool_card(
			'share',
			__( 'Import social metadata', 'seoistic' ),
			__( 'Open Graph title, description and image from any detected competitor plugin.', 'seoistic' ),
			$this->social_preview_count() > 0,
			$this->social_preview_count(),
			'social'
		);

		$this->redirects_csv_card();

		echo '</div>';
		View::footer();
	}

	private function tool_card( string $icon, string $title, string $desc, bool $detected, int $count, string $type ): void {
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></div><strong>' . esc_html( $title ) . '</strong></div>';
		echo '<p>' . esc_html( $desc ) . '</p>';
		echo '<div class="seoistic-tool-status">' . ( $detected ? View::badge( __( 'Detected', 'seoistic' ), 'good' ) : View::badge( __( 'Not detected', 'seoistic' ), 'neutral' ) );
		echo ' &nbsp;' . esc_html(
			sprintf(
				/* translators: %d: number of posts with importable data. */
				_n( '%d post ready to import', '%d posts ready to import', $count, 'seoistic' ),
				$count
			)
		) . '</div>';
		echo '<div class="seoistic-tool-progress"><div class="seoistic-tool-progress-bar"></div></div>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-import="' . esc_attr( $type ) . '" ' . ( 0 === $count ? 'disabled' : '' ) . '><span class="dashicons dashicons-download"></span> ' . esc_html__( 'Start import', 'seoistic' ) . '</button>';
		echo '<div class="seoistic-tool-result"></div>';
		echo '</div>';
	}

	private function redirects_csv_card(): void {
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-randomize"></span></div><strong>' . esc_html__( 'Import redirects CSV', 'seoistic' ) . '</strong></div>';
		echo '<p>' . esc_html__( 'CSV columns: old_url, bucket, dest_domain, new_url, priority, status_code.', 'seoistic' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="seoistic_import_redirects">';
		wp_nonce_field( 'seoistic_redirect' );
		echo '<input type="file" name="csv" accept=".csv" required style="margin-bottom:10px;">';
		echo '<button class="seoistic-btn seoistic-btn-primary"><span class="dashicons dashicons-upload"></span> ' . esc_html__( 'Upload & import', 'seoistic' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Batched AJAX import — 50 posts per request, only filling empty SEOISTIC fields.
	 */
	public function ajax_import_batch(): void {
		check_ajax_referer( 'seoistic_import_batch', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seoistic' ) ), 403 );
		}

		$type   = sanitize_key( wp_unslash( $_POST['source'] ?? '' ) );
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		if ( isset( self::SOURCES[ $type ] ) ) {
			$this->run_source_batch( $type, $offset );
			return;
		}
		if ( 'schema' === $type ) {
			$this->run_schema_batch( $offset );
			return;
		}
		if ( 'social' === $type ) {
			$this->run_social_batch( $offset );
			return;
		}

		wp_send_json_error( array( 'message' => __( 'Unknown import type.', 'seoistic' ) ) );
	}

	/**
	 * @param array<int, string> $meta_keys
	 * @return array{ids: list<int>, total:int}
	 */
	private function batch_ids( array $meta_keys, int $offset ): array {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders}) AND meta_value <> ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_keys
			)
		);

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders}) AND meta_value <> '' ORDER BY post_id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $meta_keys, array( self::BATCH_SIZE, $offset ) )
			)
		);

		return array( 'ids' => array_map( 'intval', $ids ), 'total' => $total );
	}

	private function run_source_batch( string $source, int $offset ): void {
		$config = self::SOURCES[ $source ];
		$batch  = $this->batch_ids( array( $config['meta']['title'] ), $offset );
		$count  = 0;

		foreach ( $batch['ids'] as $post_id ) {
			if ( $this->import_source_fields( $post_id, $config['meta'] ) ) {
				++$count;
			}
		}

		$this->respond_batch( $offset, count( $batch['ids'] ), $batch['total'], $count );
	}

	/**
	 * @param array<string,string> $meta
	 */
	private function import_source_fields( int $post_id, array $meta ): bool {
		$changed = false;
		$fields  = array();

		$copy = static function ( string $from_key, string $to_key ) use ( $post_id, &$changed, &$fields ): void {
			$value = get_post_meta( $post_id, $from_key, true );
			if ( '' !== $value && '' === get_post_meta( $post_id, $to_key, true ) ) {
				$fields[ $to_key ] = $value;
				$changed           = true;
			}
		};

		$copy( $meta['title'], '_seoistic_title' );
		$copy( $meta['description'], '_seoistic_description' );
		$copy( $meta['focus_keyword'], '_seoistic_focus_keyword' );
		$copy( $meta['canonical'], '_seoistic_canonical' );
		$copy( $meta['og_title'], '_seoistic_og_title' );
		$copy( $meta['og_description'], '_seoistic_og_description' );
		$copy( $meta['og_image'], '_seoistic_og_image' );

		if ( array() !== $fields ) {
			PostSeo::save( $post_id, $fields );
		}

		$noindex = $this->parse_competitor_noindex( get_post_meta( $post_id, $meta['noindex'], true ) );
		if ( null !== $noindex && '' === get_post_meta( $post_id, '_seoistic_noindex', true ) ) {
			PostSeo::save( $post_id, array( '_seoistic_noindex' => $noindex ) );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Competitor noindex encodings differ: Yoast uses '1' = noindex; AIOSEO uses
	 * '2' = noindex ('1' = index, '0' = default); Rank Math stores a serialized
	 * array of robots directives that may contain 'noindex'.
	 */
	private function parse_competitor_noindex( $raw ): ?bool {
		if ( '' === $raw || null === $raw ) {
			return null;
		}
		if ( is_string( $raw ) && str_starts_with( $raw, 'a:' ) ) {
			$unserialized = @unserialize( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions
			if ( is_array( $unserialized ) ) {
				return in_array( 'noindex', $unserialized, true );
			}
			return null;
		}
		return in_array( (string) $raw, array( '1', '2' ), true );
	}

	private function run_schema_batch( int $offset ): void {
		$batch = $this->batch_ids( array( 'rank_math_rich_snippet', '_aioseo_schema_type' ), $offset );
		$count = 0;
		foreach ( $batch['ids'] as $post_id ) {
			if ( '' !== get_post_meta( $post_id, '_seoistic_schema_type', true ) ) {
				continue;
			}
			$type = (string) get_post_meta( $post_id, 'rank_math_rich_snippet', true );
			if ( '' === $type ) {
				$type = (string) get_post_meta( $post_id, '_aioseo_schema_type', true );
			}
			if ( '' !== $type && 'off' !== $type ) {
				PostSeo::save( $post_id, array( '_seoistic_schema_type' => $type ) );
				++$count;
			}
		}
		$this->respond_batch( $offset, count( $batch['ids'] ), $batch['total'], $count );
	}

	private function run_social_batch( int $offset ): void {
		$keys  = array_map( static fn( array $c ) => $c['meta']['og_title'], self::SOURCES );
		$batch = $this->batch_ids( array_values( $keys ), $offset );
		$count = 0;
		foreach ( $batch['ids'] as $post_id ) {
			foreach ( self::SOURCES as $config ) {
				if ( $this->import_social_fields( $post_id, $config['meta'] ) ) {
					++$count;
					break;
				}
			}
		}
		$this->respond_batch( $offset, count( $batch['ids'] ), $batch['total'], $count );
	}

	/**
	 * Copies only the Open Graph fields (title/description/image) from a competitor
	 * plugin's meta into ours — used by the standalone "Import social metadata" card.
	 *
	 * @param array<string,string> $meta
	 */
	private function import_social_fields( int $post_id, array $meta ): bool {
		$changed = false;
		$fields  = array();

		$copy = static function ( string $from_key, string $to_key ) use ( $post_id, &$changed, &$fields ): void {
			$value = get_post_meta( $post_id, $from_key, true );
			if ( '' !== $value && '' === get_post_meta( $post_id, $to_key, true ) ) {
				$fields[ $to_key ] = $value;
				$changed           = true;
			}
		};

		$copy( $meta['og_title'], '_seoistic_og_title' );
		$copy( $meta['og_description'], '_seoistic_og_description' );
		$copy( $meta['og_image'], '_seoistic_og_image' );

		if ( array() !== $fields ) {
			PostSeo::save( $post_id, $fields );
		}

		return $changed;
	}

	private function respond_batch( int $offset, int $processed, int $total, int $imported ): void {
		$next_offset = $offset + $processed;
		$done        = $processed < self::BATCH_SIZE || $next_offset >= $total;
		wp_send_json_success(
			array(
				'imported'    => $imported,
				'next_offset' => $next_offset,
				'percent'     => $total > 0 ? min( 100, ( $next_offset / $total ) * 100 ) : 100,
				'done'        => $done,
				/* translators: %d: number of posts imported. */
				'message'     => sprintf( __( 'Import complete — %d posts updated.', 'seoistic' ), $next_offset ),
			)
		);
	}
}
