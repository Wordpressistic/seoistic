<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\BusinessAutomator;

/**
 * Client for communicating with WPistic Business Automator API.
 */
final class AutomatorClient {

	private string $base_url;
	private string $api_token;

	public function __construct( string $base_url, string $api_token ) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->api_token = $api_token;
	}

	/**
	 * Get all scripts from the automator.
	 *
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function get_scripts(): array {
		return $this->request( 'GET', '/api/scripts/' );
	}

	/**
	 * Get a specific script.
	 *
	 * @param string $script_id Script ID.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function get_script( string $script_id ): array {
		return $this->request( 'GET', "/api/scripts/{$script_id}/" );
	}

	/**
	 * Create a new script.
	 *
	 * @param array<string, mixed> $data Script data.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function create_script( array $data ): array {
		return $this->request( 'POST', '/api/scripts/', $data );
	}

	/**
	 * Update a script.
	 *
	 * @param string              $script_id Script ID.
	 * @param array<string, mixed> $data      Script data.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function update_script( string $script_id, array $data ): array {
		return $this->request( 'PATCH', "/api/scripts/{$script_id}/", $data );
	}

	/**
	 * Delete a script.
	 *
	 * @param string $script_id Script ID.
	 * @return bool
	 * @throws \Exception
	 */
	public function delete_script( string $script_id ): bool {
		$this->request( 'DELETE', "/api/scripts/{$script_id}/" );
		return true;
	}

	/**
	 * Get script runs/history.
	 *
	 * @param string $script_id Script ID.
	 * @param int    $limit     Number of runs to retrieve.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function get_script_runs( string $script_id, int $limit = 10 ): array {
		return $this->request( 'GET', "/api/scripts/{$script_id}/runs/?limit={$limit}" );
	}

	/**
	 * Execute a script immediately.
	 *
	 * @param string $script_id Script ID.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function run_script( string $script_id ): array {
		return $this->request( 'POST', "/api/scripts/{$script_id}/run/" );
	}

	/**
	 * Get datastores (for storing results).
	 *
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function get_datastores(): array {
		return $this->request( 'GET', '/api/datastores/' );
	}

	/**
	 * Get datastore entries.
	 *
	 * @param string $datastore_name Datastore name.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function get_datastore_entries( string $datastore_name ): array {
		return $this->request( 'GET', "/api/datastores/{$datastore_name}/entries/" );
	}

	/**
	 * Get a specific datastore entry.
	 *
	 * @param string $datastore_name Datastore name.
	 * @param string $key            Entry key.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	public function get_datastore_entry( string $datastore_name, string $key ): array {
		return $this->request( 'GET', "/api/datastores/{$datastore_name}/entries/{$key}/" );
	}

	/**
	 * Test connection to the automator.
	 *
	 * @return bool
	 */
	public function test_connection(): bool {
		try {
			$this->request( 'GET', '/api/datastores/' );
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Make a request to the Business Automator API.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $path   API path.
	 * @param array<string, mixed> $data   Request data.
	 * @return array<mixed>
	 * @throws \Exception
	 */
	private function request( string $method, string $path, array $data = array() ): array {
		$url = $this->base_url . $path;

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, array_merge( $args, array( 'method' => $method ) ) );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Request failed: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 ) {
			throw new \Exception( "API error (HTTP {$status}): {$body}" );
		}

		$decoded = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Invalid JSON response from API' );
		}

		return $decoded ?? array();
	}
}
