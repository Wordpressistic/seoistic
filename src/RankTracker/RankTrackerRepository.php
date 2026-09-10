<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\RankTracker;

final class RankTrackerRepository {

	public const SOURCES = array( 'search_api', 'gsc' );

	public function keyword_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_keywords';
	}

	public function position_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_positions';
	}

	/** @return array<int, array<string, mixed>> */
	public function keywords( string $locale = '', string $device = '' ): array {
		global $wpdb;
		$where  = array();
		$values = array();
		if ( '' !== $locale ) {
			$where[]  = 'locale = %s';
			$values[] = $locale;
		}
		if ( '' !== $device ) {
			$where[]  = 'device = %s';
			$values[] = $device;
		}
		$condition = array() === $where ? '' : ' WHERE ' . implode( ' AND ', $where );
		$sql       = 'SELECT * FROM ' . $this->keyword_table() . $condition . ' ORDER BY keyword ASC';
		$rows      = array() === $values
			? $wpdb->get_results( $sql, ARRAY_A )
			: $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		return array_map( array( $this, 'hydrate_keyword' ), is_array( $rows ) ? $rows : array() );
	}

	/** @return array<string, mixed> */
	public function find_keyword( int $id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->keyword_table() . ' WHERE id = %d', $id ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate_keyword( $row ) : array();
	}

	public function save_keyword( string $keyword, string $locale, string $device, string $source = 'search_api' ): int {
		global $wpdb;
		$keyword = sanitize_text_field( $keyword );
		$locale  = $this->sanitize_locale( $locale );
		$device  = $this->sanitize_device( $device );
		$source  = in_array( $source, self::SOURCES, true ) ? $source : 'search_api';

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->keyword_table() . ' WHERE keyword = %s AND locale = %s AND device = %s',
				$keyword,
				$locale,
				$device
			),
			ARRAY_A
		);
		if ( is_array( $existing ) ) {
			return (int) $existing['id'];
		}

		$wpdb->insert(
			$this->keyword_table(),
			array(
				'keyword'   => $keyword,
				'locale'   => $locale,
				'device'   => $device,
				'source'   => $source,
				'added'    => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function delete_keyword( int $id ): void {
		global $wpdb;
		$wpdb->delete( $this->keyword_table(), array( 'id' => $id ) );
		$wpdb->delete( $this->position_table(), array( 'keyword_id' => $id ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $keyword_id, int $limit = 90 ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT position, url, checked_at FROM ' . $this->position_table() . ' WHERE keyword_id = %d ORDER BY checked_at DESC, id DESC LIMIT %d',
				$keyword_id,
				max( 1, min( 365, $limit ) )
			),
			ARRAY_A
		);
		$history = array();
		foreach ( $rows as $row ) {
			$history[] = array(
				'position'   => (float) $row['position'],
				'url'        => (string) $row['url'],
				'checked_at' => (string) $row['checked_at'],
			);
		}
		return $history;
	}

	public function record_position( int $keyword_id, float $position, string $url, ?string $checked_at = null, bool $replace_day = false ): void {
		global $wpdb;
		$position = max( 0.0, min( 100.0, $position ) );
		$url      = esc_url_raw( $url );
		$date     = $checked_at ?? current_time( 'mysql', true );

		if ( $replace_day ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id FROM ' . $this->position_table() . ' WHERE keyword_id = %d AND DATE(checked_at) = DATE(%s) ORDER BY id DESC LIMIT 1',
					$keyword_id,
					$date
				),
				ARRAY_A
			);
			if ( is_array( $existing ) ) {
				$wpdb->update(
					$this->position_table(),
					array( 'position' => $position, 'url' => $url, 'checked_at' => $date ),
					array( 'id' => (int) $existing['id'] )
				);
				return;
			}
		}

		$wpdb->insert(
			$this->position_table(),
			array(
				'keyword_id' => $keyword_id,
				'position'   => $position,
				'url'        => $url,
				'checked_at' => $date,
			)
		);
	}

	/** @return array<string, mixed> */
	public function hydrate_keyword( array $row ): array {
		return array(
			'id'       => (int) $row['id'],
			'keyword'  => (string) $row['keyword'],
			'locale'   => (string) $row['locale'],
			'device'   => (string) $row['device'],
			'source'   => (string) $row['source'],
			'added'    => (string) $row['added'],
		);
	}

	public function sanitize_locale( string $locale ): string {
		$locale = str_replace( '_', '-', sanitize_text_field( $locale ) );
		return preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/i', $locale ) ? $locale : 'en-US';
	}

	public function sanitize_device( string $device ): string {
		return in_array( $device, array( 'desktop', 'mobile' ), true ) ? $device : 'desktop';
	}
}
