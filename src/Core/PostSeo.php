<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Single source of truth for the SEOISTIC post-meta contract. Reads prefer the
 * current key names but fall back to the pre-upgrade keys ( _seoistic_desc,
 * _seoistic_focus_kw, _seoistic_robots ) so sites upgrading from 1.0.0 keep their
 * saved data. Writes always use the current key names.
 */
final class PostSeo {

	public static function title( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_title', true );
	}

	public static function description( int $post_id ): string {
		$value = (string) get_post_meta( $post_id, '_seoistic_description', true );
		return '' !== $value ? $value : (string) get_post_meta( $post_id, '_seoistic_desc', true );
	}

	public static function focus_keyword( int $post_id ): string {
		$value = (string) get_post_meta( $post_id, '_seoistic_focus_keyword', true );
		return '' !== $value ? $value : (string) get_post_meta( $post_id, '_seoistic_focus_kw', true );
	}

	public static function canonical( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_canonical', true );
	}

	public static function is_noindex( int $post_id ): bool {
		$value = get_post_meta( $post_id, '_seoistic_noindex', true );
		if ( '' !== $value ) {
			return (bool) $value;
		}
		return 'noindex' === get_post_meta( $post_id, '_seoistic_robots', true );
	}

	public static function is_nofollow( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, '_seoistic_nofollow', true );
	}

	public static function og_title( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_og_title', true );
	}

	public static function og_description( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_og_description', true );
	}

	public static function og_image( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_og_image', true );
	}

	public static function schema_type( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_schema_type', true );
	}

	public static function breadcrumb_title( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_breadcrumb_title', true );
	}

	public static function score( int $post_id ): int {
		$value = get_post_meta( $post_id, '_seoistic_score', true );
		return '' === $value ? -1 : (int) $value;
	}

	/**
	 * The score as of the audit before last — the baseline content-decay flagging
	 * compares against to tell "just dropped below the line" from "chronically low".
	 */
	public static function previous_score( int $post_id ): int {
		$value = get_post_meta( $post_id, '_seoistic_previous_score', true );
		return '' === $value ? -1 : (int) $value;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function audit_report( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, '_seoistic_audit_report', true );
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public static function last_audit( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_seoistic_last_audit', true );
	}

	/**
	 * Persist the full SEOISTIC field set for a post. Every value is sanitized here so
	 * callers (metabox save, REST full-page-optimization) only need to pass raw input.
	 *
	 * @param array<string, mixed> $fields
	 */
	public static function save( int $post_id, array $fields ): void {
		$map = array(
			'_seoistic_title'             => 'sanitize_text_field',
			'_seoistic_description'       => 'sanitize_textarea_field',
			'_seoistic_focus_keyword'     => 'sanitize_text_field',
			'_seoistic_canonical'         => 'esc_url_raw',
			'_seoistic_og_title'          => 'sanitize_text_field',
			'_seoistic_og_description'    => 'sanitize_textarea_field',
			'_seoistic_og_image'          => 'esc_url_raw',
			'_seoistic_schema_type'       => 'sanitize_text_field',
			'_seoistic_breadcrumb_title'  => 'sanitize_text_field',
		);

		foreach ( $map as $key => $sanitizer ) {
			if ( array_key_exists( $key, $fields ) ) {
				update_post_meta( $post_id, $key, call_user_func( $sanitizer, (string) $fields[ $key ] ) );
			}
		}

		if ( array_key_exists( '_seoistic_noindex', $fields ) ) {
			$noindex = ! empty( $fields['_seoistic_noindex'] );
			update_post_meta( $post_id, '_seoistic_noindex', $noindex ? '1' : '' );
			// Keep the legacy key in sync so Sitemaps/Meta code paths that still read
			// it directly (third-party filters, older cached queries) stay correct.
			update_post_meta( $post_id, '_seoistic_robots', $noindex ? 'noindex' : '' );
		}
		if ( array_key_exists( '_seoistic_nofollow', $fields ) ) {
			update_post_meta( $post_id, '_seoistic_nofollow', ! empty( $fields['_seoistic_nofollow'] ) ? '1' : '' );
		}

		// Mirror into the legacy keys too, so the description/focus keyword stay
		// correct for any code still reading the pre-upgrade meta directly.
		if ( array_key_exists( '_seoistic_description', $fields ) ) {
			update_post_meta( $post_id, '_seoistic_desc', sanitize_textarea_field( (string) $fields['_seoistic_description'] ) );
		}
		if ( array_key_exists( '_seoistic_focus_keyword', $fields ) ) {
			update_post_meta( $post_id, '_seoistic_focus_kw', sanitize_text_field( (string) $fields['_seoistic_focus_keyword'] ) );
		}
	}

	public static function save_score( int $post_id, int $score, string $report_json ): void {
		$existing = self::score( $post_id );
		if ( -1 !== $existing ) {
			update_post_meta( $post_id, '_seoistic_previous_score', $existing );
		}
		update_post_meta( $post_id, '_seoistic_score', max( 0, min( 100, $score ) ) );
		update_post_meta( $post_id, '_seoistic_audit_report', wp_slash( $report_json ) );
		update_post_meta( $post_id, '_seoistic_last_audit', current_time( 'mysql' ) );
	}

	public static function index_status( int $post_id, string $post_status ): string {
		if ( 'publish' !== $post_status ) {
			return 'private' === $post_status ? 'private' : 'draft';
		}
		return self::is_noindex( $post_id ) ? 'noindex' : 'indexable';
	}
}
