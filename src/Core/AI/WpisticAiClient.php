<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core\AI;

use Wpistic\Seoistic\AI\AiSettings;
use Wpistic\Seoistic\AI\PromptBuilder;
use Wpistic\Seoistic\AI\Providers\OpenAiCompatibleClient;
use Wpistic\Seoistic\License\LicenseClient;
use Wpistic\Seoistic\Module\Entitlement;
use WP_Error;

/**
 * The only AI transport used by SEOistic features. Metered requests go to the
 * WPistic AI gateway; eligible Business/Agency sites may instead use their own
 * OpenAI-compatible endpoint without consuming WPistic credits.
 */
final class WpisticAiClient {

	public const ENDPOINT = 'https://ai.wpistic.com/v1/license/chat';

	private const CACHE_TTL           = 10 * MINUTE_IN_SECONDS;
	private const CUSTOM_TTL          = 10 * MINUTE_IN_SECONDS;
	private const RETRY_DELAY_SECONDS = 2;

	private const TASKS = array(
		'title'              => 'title',
		'description'        => 'description',
		'keywords'           => 'keywords',
		'alt'                => 'alt',
		'optimize_content'   => 'optimize_content',
		'full_optimize'      => 'full_page_optimization',
		'schema'             => 'schema',
		'aeo_audit'          => 'aeo',
		'internal_links'     => 'internal_links',
		'robots'             => 'robots',
		'htaccess'           => 'htaccess',
		'llms'               => 'llms',
		'local_schema'       => 'local_schema',
		'woocommerce_schema' => 'woocommerce_schema',
	);

	public const CREDIT_COSTS = array(
		'title'            => 1,
		'description'      => 1,
		'keywords'         => 1,
		'alt'              => 1,
		'optimize_content' => 3,
		'full_optimize'    => 5,
		'schema'           => 2,
		'aeo_audit'        => 10,
	);

	private LicenseClient $license;

	public function __construct( ?LicenseClient $license = null ) {
		$this->license = $license ?? new LicenseClient();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate( string $task, array $payload = array() ) {
		$prompt_task = self::TASKS[ $task ] ?? null;
		if ( null === $prompt_task ) {
			return new WP_Error(
				'seoistic_ai_unknown_task',
				__( 'That AI task is not supported.', 'seoistic' ),
				array( 'status' => 400 )
			);
		}

		if ( ! AiSettings::is_enabled() ) {
			return new WP_Error(
				'seoistic_ai_disabled',
				__( 'AI features are turned off in SEOistic → Settings → AI.', 'seoistic' ),
				array( 'status' => 403 )
			);
		}

		$key       = (string) $this->license->key();
		$cache_key = self::cache_key( $task, $payload, $key, AiSettings::custom_model_signature() );
		$cached     = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['cached'] = true;
			self::record_usage(
				array(
					'task'       => $task,
					'credits'    => 0,
					'cached'     => true,
					'created_at' => time(),
				)
			);
			$cached['usage'] = self::local_snapshot();
			return $cached;
		}

		$messages = PromptBuilder::build( $prompt_task, $payload );
		$result   = AiSettings::is_custom_configured()
			? $this->custom_request( $task, $messages )
			: $this->gateway_request( $task, $payload, $messages, $key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		set_transient( $cache_key, $result, AiSettings::is_custom_configured() ? self::CUSTOM_TTL : self::CACHE_TTL );
		return $result;
	}

	/**
	 * Mirror the server table so interfaces can disclose a cost before a request.
	 *
	 * @return array<string, string>
	 */
	public static function credit_costs(): array {
		$labels = array(
			'title'            => __( 'Title', 'seoistic' ),
			'description'      => __( 'Meta description', 'seoistic' ),
			'keywords'         => __( 'Keywords', 'seoistic' ),
			'alt'              => __( 'Alt-text batch (per 10 images)', 'seoistic' ),
			'optimize_content' => __( 'Content optimization', 'seoistic' ),
			'full_optimize'    => __( 'Full-page optimization', 'seoistic' ),
			'schema'           => __( 'Schema generation', 'seoistic' ),
			'aeo_audit'        => __( 'AEO audit', 'seoistic' ),
		);

		$out = array();
		foreach ( self::CREDIT_COSTS as $task => $credits ) {
			$out[ $task ] = sprintf(
				/* translators: 1: task label, 2: credit count. */
				_n( '%1$s: %2$d credit', '%1$s: %2$d credits', $credits, 'seoistic' ),
				$labels[ $task ],
				$credits
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array{role:string, content:string}> $messages
	 * @return array<string, mixed>|WP_Error
	 */
	private function custom_request( string $task, array $messages ) {
		$plan = Entitlement::plan();
		if ( ! in_array( $plan, array( 'business', 'agency' ), true ) ) {
			return new WP_Error(
				'seoistic_ai_custom_plan',
				__( 'Connect a valid Business or Agency license to use a custom AI model.', 'seoistic' ),
				array( 'status' => 403, 'upgrade_card' => true )
			);
		}

		$result = OpenAiCompatibleClient::chat(
			AiSettings::custom_base_url() . '/chat/completions',
			array( 'Authorization' => 'Bearer ' . AiSettings::custom_api_key() ),
			AiSettings::custom_model(),
			$messages,
			AiSettings::temperature(),
			AiSettings::max_tokens()
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'seoistic_ai_custom_error',
				__( 'Your custom AI model request failed. Check the endpoint URL, model name, and API key.', 'seoistic' ),
				array( 'status' => 502, 'task' => $task )
			);
		}

		self::record_usage(
			array(
				'task'       => $task,
				'credits'    => 0,
				'unmetered'  => true,
				'created_at' => time(),
			)
		);

		return array(
			'success'  => true,
			'data'     => $result['content'],
			'usage'    => self::local_snapshot(),
			'provider' => 'custom',
			'cached'   => false,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<int, array{role:string, content:string}> $messages
	 * @return array<string, mixed>|WP_Error
	 */
	private function gateway_request( string $task, array $payload, array $messages, string $key ) {
		if ( '' === $key ) {
			return new WP_Error(
				'seoistic_ai_license_missing',
				__( 'Connect your SEOistic license to use AI credits.', 'seoistic' ),
				array( 'status' => 402, 'upgrade_card' => true )
			);
		}

		$body = array(
			'license_key' => $key,
			'site_url'    => home_url( '/' ),
			'task'        => $task,
			'payload'     => array(
				'messages'      => $messages,
				'context'       => $payload,
				'temperature'   => AiSettings::temperature(),
				'max_tokens'    => AiSettings::max_tokens(),
				'cache_buster'  => AiSettings::cache_generation(),
				'requested_url' => (string) ( $payload['url'] ?? '' ),
			),
		);

		$response = $this->transport( $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $status && ! defined( 'SEOISTIC_AI_TESTS' ) ) {
			sleep( self::RETRY_DELAY_SECONDS );
			$response = $this->transport( $body );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
		}

		if ( 429 === $status ) {
			return new WP_Error(
				'seoistic_ai_rate_limited',
				__( 'The AI service is still busy after one retry. Please wait a moment and try again.', 'seoistic' ),
				array( 'status' => 503 )
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			return new WP_Error(
				'seoistic_ai_license_invalid',
				__( 'Connect a valid SEOistic license to use WPistic AI.', 'seoistic' ),
				array( 'status' => 402, 'upgrade_card' => true )
			);
		}

		if ( 402 === $status ) {
			$data = is_array( $decoded ) ? $decoded : array();
			return new WP_Error(
				'seoistic_insufficient_credits',
				__( 'You are out of AI credits this month. Upgrade your plan to keep generating SEO content.', 'seoistic' ),
				array(
					'status'       => 402,
					'upgrade_card' => true,
					'credits_left' => is_numeric( $data['credits_left'] ?? null ) ? (int) $data['credits_left'] : 0,
					'plan'         => sanitize_text_field( (string) ( $data['plan'] ?? Entitlement::plan() ) ),
				)
			);
		}

		if ( $status < 200 || $status >= 300 || ! is_array( $decoded ) ) {
			return new WP_Error(
				'seoistic_ai_gateway_error',
				__( 'The WPistic AI service is temporarily unavailable. Please try again shortly.', 'seoistic' ),
				array( 'status' => 502 )
			);
		}

		$content = $decoded['data'] ?? ( $decoded['content'] ?? '' );
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return new WP_Error(
				'seoistic_ai_empty',
				__( 'The AI service returned an empty response. Please try again.', 'seoistic' ),
				array( 'status' => 502 )
			);
		}

		$usage = is_array( $decoded['credits'] ?? null ) ? $decoded['credits'] : array();
		if ( array() !== $usage ) {
			self::save_server_snapshot( $usage );
		}

		self::record_usage(
			array(
				'task'      => $task,
				'credits'   => max( 0, (int) ( $usage['charged'] ?? ( self::CREDIT_COSTS[ $task ] ?? 0 ) ) ),
				'unmetered' => false,
				'created_at' => time(),
			)
		);

		return array(
			'success'  => true,
			'data'     => $content,
			'usage'    => self::local_snapshot(),
			'provider' => 'wpistic',
			'cached'   => false,
		);
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|WP_Error
	 */
	private function transport( array $body ) {
		$response = wp_remote_post(
			(string) apply_filters( 'seoistic/ai/gateway_url', self::ENDPOINT ),
			array(
				'timeout' => 45,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'seoistic_ai_network',
				__( 'Could not reach the WPistic AI service. Check your connection and try again.', 'seoistic' ),
				array( 'status' => 503 )
			);
		}

		return $response;
	}

	private static function cache_key( string $task, array $payload, string $key, string $custom_signature ): string {
		return 'seoistic_ai_' . md5( (string) wp_json_encode( array( $task, $payload, $key, $custom_signature, AiSettings::temperature(), AiSettings::max_tokens(), AiSettings::cache_generation() ) ) );
	}

	private static function record_usage( array $record ): void {
		$records   = self::local_usage();
		$records[] = $record;
		update_option( 'seoistic_ai_recent_usage', array_slice( $records, -20 ), false );
	}

	private static function local_usage(): array {
		$records = get_option( 'seoistic_ai_recent_usage', array() );
		return is_array( $records ) ? array_values( $records ) : array();
	}

	private static function save_server_snapshot( array $usage ): void {
		update_option( 'seoistic_ai_credit_snapshot', $usage, false );
	}

	private static function local_snapshot(): array {
		return array(
			'recent' => self::local_usage(),
			'server' => self::server_snapshot(),
		);
	}

	public static function server_snapshot(): array {
		$snapshot = get_option( 'seoistic_ai_credit_snapshot', array() );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	public static function usage_snapshot(): array {
		return self::local_snapshot();
	}

	public static function clear_caches(): void {
		AiSettings::bump_cache_generation();
	}
}
