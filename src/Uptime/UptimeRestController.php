<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Uptime;

/**
 * REST API for the uptime monitor. Every route enforces a capability in its
 * permission_callback, and every state-changing route additionally verifies the
 * wp_rest nonce — matching the plugin-wide convention (BusinessAutomatorModule,
 * AI\RestController). All identifiers are integers and all writes go through the
 * prepared-statement store.
 */
final class UptimeRestController {

	private const NAMESPACE = 'seoistic/v1';

	private UptimeStore $store;
	private UptimeMonitor $monitor;

	public function __construct() {
		$this->store   = new UptimeStore();
		$this->monitor = new UptimeMonitor( $this->store );
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/uptime/monitors',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_manage_write' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/uptime/monitors/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_manage_write' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'can_manage_write' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/uptime/monitors/(?P<id>\d+)/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_now' ),
				'permission_callback' => array( $this, 'can_manage_write' ),
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
	}

	/**
	 * Capability required to manage uptime monitors. Defaults to manage_options
	 * (the plugin-wide admin gate) but is filterable so a site can delegate it to
	 * a dedicated SEO role without weakening anything else.
	 */
	public function capability(): string {
		return (string) apply_filters( 'seoistic/uptime_capability', 'manage_options' );
	}

	public function can_manage(): bool {
		return current_user_can( $this->capability() );
	}

	/**
	 * Write guard: capability + wp_rest nonce. Returning a WP_Error (rather than
	 * bare false) gives the client a precise 403 reason instead of a generic one.
	 */
	public function can_manage_write() {
		if ( ! current_user_can( $this->capability() ) ) {
			return new \WP_Error( 'seoistic_forbidden', __( 'You are not allowed to manage uptime monitors.', 'seoistic' ), array( 'status' => 403 ) );
		}
		if ( ! wp_verify_nonce( $this->request_nonce(), 'wp_rest' ) ) {
			return new \WP_Error( 'seoistic_bad_nonce', __( 'Security check failed. Reload the page and try again.', 'seoistic' ), array( 'status' => 403 ) );
		}
		return true;
	}

	private function request_nonce(): string {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
		return $nonce;
	}

	public function list( \WP_REST_Request $request ) {
		$monitors = array();
		foreach ( $this->store->all_monitors() as $row ) {
			$monitors[] = $this->present( $row );
		}
		return rest_ensure_response( array( 'monitors' => $monitors ) );
	}

	public function create( \WP_REST_Request $request ) {
		$prepared = UptimeValidator::prepare( (array) $request->get_json_params() );
		if ( isset( $prepared['error'] ) ) {
			return new \WP_Error( 'seoistic_invalid', $prepared['error'], array( 'status' => 400 ) );
		}
		$data                       = $prepared['data'];
		$data['current_status']     = 'unknown';
		$id                         = $this->store->insert_monitor( $data );
		if ( 0 === $id ) {
			return new \WP_Error( 'seoistic_db_error', __( 'Could not save the monitor.', 'seoistic' ), array( 'status' => 500 ) );
		}
		// A monitor now exists — ensure the cron engine is scheduled immediately
		// rather than waiting for the next admin_init.
		$this->monitor->maybe_schedule();
		$row = $this->store->get_monitor( $id );
		return rest_ensure_response( array( 'monitor' => $row ? $this->present( $row ) : null ) );
	}

	public function update( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( null === $this->store->get_monitor( $id ) ) {
			return new \WP_Error( 'seoistic_not_found', __( 'Monitor not found.', 'seoistic' ), array( 'status' => 404 ) );
		}
		$prepared = UptimeValidator::prepare( (array) $request->get_json_params() );
		if ( isset( $prepared['error'] ) ) {
			return new \WP_Error( 'seoistic_invalid', $prepared['error'], array( 'status' => 400 ) );
		}
		$this->store->update_monitor( $id, $prepared['data'] );
		$row = $this->store->get_monitor( $id );
		return rest_ensure_response( array( 'monitor' => $row ? $this->present( $row ) : null ) );
	}

	public function delete( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( null === $this->store->get_monitor( $id ) ) {
			return new \WP_Error( 'seoistic_not_found', __( 'Monitor not found.', 'seoistic' ), array( 'status' => 404 ) );
		}
		$this->store->delete_monitor( $id );
		// If that was the last monitor, this clears the now-idle cron.
		$this->monitor->maybe_schedule();
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function check_now( \WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = $this->store->get_monitor( $id );
		if ( null === $row ) {
			return new \WP_Error( 'seoistic_not_found', __( 'Monitor not found.', 'seoistic' ), array( 'status' => 404 ) );
		}
		$outcome = $this->monitor->check_monitor( $row );
		$fresh   = $this->store->get_monitor( $id );
		return rest_ensure_response(
			array(
				'outcome' => $outcome,
				'monitor' => $fresh ? $this->present( $fresh ) : null,
			)
		);
	}

	/**
	 * Shape a raw monitor row for the client: typed fields plus the derived
	 * uptime percentage and a short recent-check history.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function present( array $row ): array {
		$id      = (int) $row['id'];
		$history = array();
		foreach ( $this->store->recent_checks( $id, 20 ) as $check ) {
			$history[] = array(
				'status'      => (string) $check['status'],
				'code'        => null !== $check['code'] ? (int) $check['code'] : null,
				'response_ms' => null !== $check['response_ms'] ? (int) $check['response_ms'] : null,
				'error'       => $check['error'],
				'checked_at'  => (string) $check['checked_at'],
			);
		}

		return array(
			'id'                 => $id,
			'label'              => (string) $row['label'],
			'url'                => (string) $row['url'],
			'method'             => (string) $row['method'],
			'expected_code'      => (int) $row['expected_code'],
			'keyword'            => (string) ( $row['keyword'] ?? '' ),
			'interval_minutes'   => (int) $row['interval_minutes'],
			'timeout_seconds'    => (int) $row['timeout_seconds'],
			'enabled'            => (bool) $row['enabled'],
			'notify_email'       => (string) ( $row['notify_email'] ?? '' ),
			'current_status'     => (string) $row['current_status'],
			'last_checked'       => $row['last_checked'],
			'last_status_change' => $row['last_status_change'],
			'last_code'          => null !== $row['last_code'] ? (int) $row['last_code'] : null,
			'last_response_ms'   => null !== $row['last_response_ms'] ? (int) $row['last_response_ms'] : null,
			'last_error'         => $row['last_error'],
			'ssl_expires'        => $row['ssl_expires'],
			'uptime_30d'         => $this->store->uptime_percent( $id, 30 ),
			'history'            => $history,
		);
	}
}
