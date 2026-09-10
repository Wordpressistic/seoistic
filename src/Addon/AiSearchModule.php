<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Admin\AeoPage;
use Wpistic\Seoistic\AiSearch\AeoAuditService;
use Wpistic\Seoistic\AiSearch\AiCrawlerStore;
use Wpistic\Seoistic\Core\LlmsTxt;
use Wpistic\Seoistic\Module\AbstractModule;
use WP_Error;
use WP_REST_Request;

final class AiSearchModule extends AbstractModule {

	private const REST_NAMESPACE = 'seoistic/v1';

	public function id(): string {
		return 'ai_search';
	}

	public function name(): string {
		return __( 'AI Search Visibility (AEO)', 'seoistic' );
	}

	public function description(): string {
		return __( 'Optimize for ChatGPT, Perplexity, Gemini and Google AI Overviews — and track where you are cited. No other SEO plugin does this.', 'seoistic' );
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
		( new AiCrawlerStore() )->register();
		if ( is_admin() ) {
			( new AeoPage() )->register();
		}
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/aeo/llms',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_llms' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'blueprint' => array( 'required' => true, 'type' => 'object' ),
					'apply' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/aeo/llms/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_llms_reset' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/aeo/audit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_audit' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/aeo/checklist',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_checklist' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/aeo/checklist',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_checklist' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'values' => array( 'required' => true, 'type' => 'object' ),
				),
			)
		);
	}

	public function handle_llms( WP_REST_Request $request ) {
		$blueprint = $request->get_param( 'blueprint' );
		$blueprint = is_array( $blueprint ) ? $blueprint : array();
		$content = LlmsTxt::render_blueprint( $blueprint );
		$saved = LlmsTxt::save_blueprint( $blueprint );
		if ( ! $saved ) {
			return new WP_Error( 'seoistic_llms_save_failed', __( 'Could not save the llms.txt blueprint.', 'seoistic' ), array( 'status' => 500 ) );
		}
		$apply = (bool) $request->get_param( 'apply' );
		if ( $apply ) {
			update_option( LlmsTxt::OPTION_CONTENT, wp_kses( $content, array() ) );
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data' => array(
					'content' => $content,
					'applied' => $apply,
				),
			)
		);
	}

	public function handle_llms_reset( WP_REST_Request $request ) {
		unset( $request );
		LlmsTxt::reset_blueprint();
		return rest_ensure_response( array( 'success' => true, 'data' => array( 'blueprint' => LlmsTxt::blueprint() ) ) );
	}

	public function handle_audit( WP_REST_Request $request ) {
		$result = ( new AeoAuditService() )->audit( (int) $request->get_param( 'post_id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public function handle_checklist( WP_REST_Request $request ) {
		$values = $request->get_param( 'values' );
		$saved = ( new AeoAuditService() )->save_checklist( (int) $request->get_param( 'post_id' ), is_array( $values ) ? $values : null );
		if ( ! $saved ) {
			return new WP_Error( 'seoistic_checklist_save_failed', __( 'Could not save the citation checklist.', 'seoistic' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function handle_get_checklist( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$stored = get_post_meta( $post_id, AeoAuditService::META_CHECKLIST, true );
		return rest_ensure_response(
			array(
				'success' => true,
				'data' => is_array( $stored ) ? $stored : array(),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
