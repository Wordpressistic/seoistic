<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Admin\PerformancePage;
use Wpistic\Seoistic\Core\Performance\PerformanceService;
use Wpistic\Seoistic\Module\AbstractModule;
use WP_Error;
use WP_REST_Request;

final class PerformanceModule extends AbstractModule {

	private const REST_NAMESPACE = 'seoistic/v1';

	public function id(): string {
		return 'performance';
	}

	public function name(): string {
		return __( 'Performance & Core Web Vitals', 'seoistic' );
	}

	public function description(): string {
		return __( 'Real Core Web Vitals monitoring (CrUX + lab), per-page PageSpeed, and actionable fixes.', 'seoistic' );
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
		( new PerformanceService() )->register();
		( new PerformancePage() )->register();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/performance/psi',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_psi' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'url'       => array( 'required' => true, 'type' => 'string', 'format' => 'uri', 'sanitize_callback' => 'esc_url_raw' ),
					'strategy'  => array( 'required' => true, 'type' => 'string', 'enum' => array( 'mobile', 'desktop' ), 'sanitize_callback' => 'sanitize_key' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/performance/quick-wins/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_quick_win_preview' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args' => array( 'action' => array( 'required' => true, 'type' => 'string', 'enum' => PerformanceService::quick_win_actions(), 'sanitize_callback' => 'sanitize_key' ) ),
			)
		);
		foreach ( array( 'apply', 'disable' ) as $quick_win_action ) {
			register_rest_route(
				self::REST_NAMESPACE,
				'/performance/quick-wins/' . $quick_win_action,
				array(
					'methods'             => 'POST',
					'callback'            => static fn( WP_REST_Request $request ) => self::handle_quick_win_mutation( $request, 'apply' === $quick_win_action ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args' => array(
						'action' => array( 'required' => true, 'type' => 'string', 'enum' => PerformanceService::quick_win_actions(), 'sanitize_callback' => 'sanitize_key' ),
						'confirmed' => array( 'required' => true, 'type' => 'boolean' ),
						'preview_token' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					),
				)
			);
		}
	}

	public function handle_psi( WP_REST_Request $request ) {
		$result = ( new PerformanceService() )->run( (string) $request->get_param( 'url' ), (string) $request->get_param( 'strategy' ) );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}
		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	public function handle_quick_win_preview( WP_REST_Request $request ) {
		$win = PerformanceService::quick_win( (string) $request->get_param( 'action' ) );
		if ( array() === $win ) {
			return new WP_Error( 'seoistic_invalid_quick_win', __( 'Unknown performance optimization.', 'seoistic' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'data' => $this->quick_win_message( $win ) ) );
	}

	public static function handle_quick_win_mutation( WP_REST_Request $request, bool $apply ) {
		if ( ! $apply ) {
			$result = PerformanceService::set_quick_win( (string) $request->get_param( 'action' ), false );
			return rest_ensure_response( array( 'success' => $result['success'], 'data' => array( 'message' => $result['success'] ? __( 'Optimization disabled.', 'seoistic' ) : $result['error'] ) ) );
		}

		if ( ! (bool) $request->get_param( 'confirmed' ) ) {
			return new WP_Error( 'seoistic_confirmation_required', __( 'Review the dry-run preview before applying this optimization.', 'seoistic' ), array( 'status' => 400 ) );
		}
		$action = (string) $request->get_param( 'action' );
		if ( ! hash_equals( PerformanceService::quick_win_preview_token( $action ), (string) $request->get_param( 'preview_token' ) ) ) {
			return new WP_Error( 'seoistic_preview_required', __( 'Generate and review the dry-run preview before applying this optimization.', 'seoistic' ), array( 'status' => 400 ) );
		}
		$win = PerformanceService::quick_win( $action );
		$result = PerformanceService::set_quick_win( $action, true );
		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'seoistic_htaccess_failed', $result['error'] ?? __( 'Could not apply the optimization.', 'seoistic' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'data' => array( 'message' => sprintf( __( '%1$s applied. Backup: %2$s', 'seoistic' ), $win['title'], $result['backup'] ?? __( 'none', 'seoistic' ) ) ) ) );
	}

	private function quick_win_message( array $win ): array {
		return array(
			'message' => sprintf( __( 'Dry-run preview for %s. No file was changed.', 'seoistic' ), $win['title'] ),
			'rules' => $win['rules'],
			'conflict' => $win['conflict'],
			'preview_token' => PerformanceService::quick_win_preview_token( $win['action'] ),
		);
	}

	private function error_response( WP_Error $error ) {
		$data = $error->get_error_data();
		return new WP_Error( $error->get_error_code(), $error->get_error_message(), is_array( $data ) ? $data : array() );
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
