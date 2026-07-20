<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Core\Crypto;
use Wpistic\Seoistic\Module\AbstractModule;

final class BusinessAutomatorModule extends AbstractModule {

	/** Shared with Admin\BusinessAutomatorPage — both read/write the same encrypted option. */
	public const CRYPTO_CONTEXT = 'business_automator';

	public function id(): string {
		return 'business_automator';
	}

	public function name(): string {
		return __( 'Business Automator Integration', 'seoistic' );
	}

	public function description(): string {
		return __( 'Connect to WPistic Business Automator to run automated checks, uptime monitoring, and scheduled tasks.', 'seoistic' );
	}

	public function tier(): string {
		return 'premium';
	}

	public function status(): string {
		return 'active';
	}

	public function defaultEnabled(): bool {
		return false;
	}

	public function register(): void {
		if ( is_admin() ) {
			( new \Wpistic\Seoistic\Admin\BusinessAutomatorPage() )->register();
		}

		// Register REST routes
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Cron jobs for automation
		add_action( 'init', array( $this, 'schedule_cron_jobs' ) );
	}


	public function register_rest_routes(): void {
		// Automations list
		register_rest_route(
			'seoistic/v1',
			'/automations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_automations' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Create automation
		register_rest_route(
			'seoistic/v1',
			'/automations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_automation' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Test connection
		register_rest_route(
			'seoistic/v1',
			'/automations/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	public function get_automations( \WP_REST_Request $request ) {
		$automations = get_option( 'seoistic_automations', array() );
		return rest_ensure_response( $automations );
	}

	public function create_automation( \WP_REST_Request $request ) {
		check_admin_referer( 'wp_rest' );

		$data = $request->get_json_params();

		$automation = array(
			'id'              => uniqid( 'auto_' ),
			'name'            => sanitize_text_field( $data['name'] ?? '' ),
			'type'            => sanitize_text_field( $data['type'] ?? '' ),
			'schedule'        => $data['schedule'] ?? array(),
			'config'          => $data['config'] ?? array(),
			'enabled'         => (bool) ( $data['enabled'] ?? true ),
			'created_at'      => gmdate( 'c' ),
		);

		$automations = get_option( 'seoistic_automations', array() );
		$automations[ $automation['id'] ] = $automation;
		update_option( 'seoistic_automations', $automations );

		return rest_ensure_response( array( 'success' => true, 'automation' => $automation ) );
	}

	public function test_connection( \WP_REST_Request $request ) {
		check_admin_referer( 'wp_rest' );

		$data = $request->get_json_params();
		$url = sanitize_url( $data['url'] ?? '' );
		$token = sanitize_text_field( $data['token'] ?? '' );

		if ( ! $url || ! $token ) {
			return rest_ensure_response( array( 'success' => false, 'message' => 'URL and token required' ) );
		}

		// Test connection to Business Automator
		$response = wp_remote_get(
			trailingslashit( $url ) . 'api/datastores/',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => $response->get_error_message() ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status === 200 ) {
			update_option( 'seoistic_automator_url', $url );
			// Encrypted at rest — same pattern as every other provider secret in
			// the plugin (AI provider keys, GSC OAuth secret, Google service
			// account key). Never stored or echoed back in plaintext.
			update_option( 'seoistic_automator_api_token', Crypto::encrypt( $token, self::CRYPTO_CONTEXT ) );
			return rest_ensure_response( array( 'success' => true, 'message' => 'Connection successful' ) );
		}

		return rest_ensure_response( array( 'success' => false, 'message' => "Connection failed (HTTP $status)" ) );
	}

	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function schedule_cron_jobs(): void {
		// Schedule periodic checks if not already scheduled
		if ( ! wp_next_scheduled( 'seoistic_run_automations' ) ) {
			wp_schedule_event( time(), 'hourly', 'seoistic_run_automations' );
		}
	}
}
