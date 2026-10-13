<?php
/**
 * GA4 Data API service for the Connections module — traffic overview, top
 * pages, channels and countries for the selected property, plus a unified
 * SEO+traffic snapshot the AI agents can reason over.
 *
 * @package Seoistic
 */

declare(strict_types=1);

namespace Wpistic\Seoistic\Connections;

final class Ga4Service {

	public function __construct( private ConnectionsClient $client = new ConnectionsClient() ) {
	}

	/**
	 * @return array|\WP_Error
	 */
	public function overview( string $property_id, int $days = 28 ) {
		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) );
		$body  = $this->report(
			$property_id,
			array( 'date' ),
			array( 'sessions', 'activeUsers', 'screenPageViews' ),
			$start,
			$end
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$rows = $this->extract_rows( $body );

		$sessions = 0;
		$users    = 0;
		$views    = 0;
		$series   = array();
		foreach ( $rows as $row ) {
			$sessions += (int) ( $row['metrics']['sessions'] ?? 0 );
			$users    += (int) ( $row['metrics']['activeUsers'] ?? 0 );
			$views    += (int) ( $row['metrics']['screenPageViews'] ?? 0 );
			$series[]  = array(
				'date'     => $row['dimensions']['date'] ?? '',
				'sessions' => (int) ( $row['metrics']['sessions'] ?? 0 ),
				'users'    => (int) ( $row['metrics']['activeUsers'] ?? 0 ),
			);
		}
		return array(
			'range'    => array( $start, $end ),
			'sessions' => $sessions,
			'users'    => $users,
			'views'    => $views,
			'series'   => $series,
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public function top_pages( string $property_id, int $days = 28, int $limit = 10 ) {
		$body = $this->report(
			$property_id,
			array( 'pagePath' ),
			array( 'screenPageViews', 'sessions', 'averageSessionDuration' ),
			gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) ),
			gmdate( 'Y-m-d' ),
			$limit
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$out = array();
		foreach ( $this->extract_rows( $body ) as $row ) {
			$out[] = array(
				'path'       => (string) ( $row['dimensions']['pagePath'] ?? '' ),
				'views'      => (int) ( $row['metrics']['screenPageViews'] ?? 0 ),
				'sessions'   => (int) ( $row['metrics']['sessions'] ?? 0 ),
				'avg_session' => (float) ( $row['metrics']['averageSessionDuration'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @return array|\WP_Error
	 */
	public function channels( string $property_id, int $days = 28 ) {
		$body = $this->report(
			$property_id,
			array( 'sessionDefaultChannelGroup' ),
			array( 'sessions' ),
			gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) ),
			gmdate( 'Y-m-d' )
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$out = array();
		foreach ( $this->extract_rows( $body ) as $row ) {
			$out[ (string) ( $row['dimensions']['sessionDefaultChannelGroup'] ?? 'unknown' ) ] = (int) ( $row['metrics']['sessions'] ?? 0 );
		}
		return $out;
	}

	/**
	 * @return array|\WP_Error
	 */
	public function search_snapshot( string $gsc_site, int $days = 28 ) {
		// Search Console Search Analytics.
		$body = $this->client->api(
			'POST',
			'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $gsc_site ) . '/searchAnalytics/query',
			array(
				'body' => array(
					'startDate' => gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) ),
					'endDate'   => gmdate( 'Y-m-d' ),
					'dimensions' => array( 'query' ),
					'rowLimit'  => 20,
				),
			)
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$out = array(
			'clicks' => 0,
			'impressions' => 0,
			'top_queries' => array(),
		);
		foreach ( (array) ( $body['rows'] ?? array() ) as $row ) {
			$out['clicks']      += (int) ( $row['clicks'] ?? 0 );
			$out['impressions'] += (int) ( $row['impressions'] ?? 0 );
			$out['top_queries'][] = array(
				'query'       => (string) ( $row['keys'][0] ?? '' ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => round( (float) ( $row['position'] ?? 0 ), 1 ),
			);
		}
		return $out;
	}

	/**
	 * @return array|\WP_Error
	 */
	private function report( string $property_id, array $dimensions, array $metrics, string $start, string $end, ?int $limit = null ) {
		$body = array(
			'dateRanges'    => array( array( 'startDate' => $start, 'endDate' => $end ) ),
			'dimensions'    => array_map( static fn( string $d ): array => array( 'name' => $d ), $dimensions ),
			'metrics'       => array_map( static fn( string $m ): array => array( 'name' => $m ), $metrics ),
			'limit'         => $limit ?? 1000,
		);
		return $this->client->api(
			'POST',
			'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( $property_id ) . ':runReport',
			array( 'body' => $body )
		);
	}

	/**
	 * @return array<int, array{dimensions:array<string,string>,metrics:array<string,float|int>}>
	 */
	private function extract_rows( array $body ): array {
		$rows   = array();
		$dim_headers = array();
		foreach ( (array) ( $body['dimensionHeaders'] ?? array() ) as $d ) {
			$dim_headers[] = (string) ( $d['name'] ?? '' );
		}
		$met_headers = array();
		foreach ( (array) ( $body['metricHeaders'] ?? array() ) as $m ) {
			$met_headers[] = (string) ( $m['name'] ?? '' );
		}
		foreach ( (array) ( $body['rows'] ?? array() ) as $row ) {
			$entry = array( 'dimensions' => array(), 'metrics' => array() );
			foreach ( (array) ( $row['dimensionValues'] ?? array() ) as $i => $v ) {
				$entry['dimensions'][ $dim_headers[ $i ] ?? $i ] = (string) ( $v['value'] ?? '' );
			}
			foreach ( (array) ( $row['metricValues'] ?? array() ) as $i => $v ) {
				$entry['metrics'][ $met_headers[ $i ] ?? $i ] = $v['value'] ?? 0;
			}
			$rows[] = $entry;
		}
		return $rows;
	}
}
