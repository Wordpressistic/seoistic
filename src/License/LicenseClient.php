<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\License;

use Wpistic\Seoistic\Core\Crypto;

/**
 * Talks to the WPistic control-plane licensing API.
 *
 * Released builds previously posted to the removed WordPress compatibility
 * path at /wp-json/licenseistic/v1/license/*, which made every activation
 * appear to fail with an HTML 404. This client uses the live SDK contract at
 * /api/v1/licenses/* and stores the returned activation credentials encrypted.
 */
final class LicenseClient {

	private const OPT_KEY          = 'seoistic_license_key';
	private const OPT_STATUS       = 'seoistic_license_status';
	private const OPT_EXPIRES      = 'seoistic_license_expires';
	private const OPT_SERVER       = 'seoistic_license_server';   // Legacy fallback only — no longer written.
	private const OPT_PRODUCT      = 'seoistic_license_product';  // Legacy fallback only — no longer written.
	private const OPT_INSTANCE     = 'seoistic_license_instance';
	private const OPT_TOKEN        = 'seoistic_license_activation_token';
	private const OPT_VERIFY       = 'seoistic_license_verification_key';
	private const OPT_LAST_OK      = 'seoistic_license_last_ok';
	private const OPT_LAST_CHECK   = 'seoistic_license_last_check';
	private const OPT_FAIL_COUNT   = 'seoistic_license_fail_count';
	private const OPT_META         = 'seoistic_license_meta';

	private const CRYPTO_CONTEXT        = 'license';
	private const TOKEN_CRYPTO_CONTEXT  = 'license_activation_token';
	private const VERIFY_CRYPTO_CONTEXT = 'license_verification_key';

	/** A confirmed-active license remains trusted during a temporary outage. */
	private const TRUST_WINDOW = 30 * DAY_IN_SECONDS;

	private const BACKOFF_BASE = HOUR_IN_SECONDS;
	private const BACKOFF_MAX  = 12 * HOUR_IN_SECONDS;

	private const RATE_LIMIT_TRANSIENT = 'seoistic_license_rl';
	private const RATE_LIMIT_MAX       = 5;
	private const RATE_LIMIT_WINDOW    = 10 * MINUTE_IN_SECONDS;

	public function server(): string {
		$base = defined( 'SEOISTIC_LICENSE_API_URL' ) && '' !== SEOISTIC_LICENSE_API_URL
			? SEOISTIC_LICENSE_API_URL
			: (string) get_option( self::OPT_SERVER, 'https://api.wpistic.com' );

		/**
		 * Advanced deployment override — not exposed as an editable wp-admin
		 * field. The value is the API origin, without a route suffix.
		 *
		 * @param string $base
		 */
		return untrailingslashit( (string) apply_filters( 'seoistic_license_api_url', $base ) );
	}

	public function product_id(): int {
		$id = defined( 'SEOISTIC_LICENSE_PRODUCT_ID' ) && (int) SEOISTIC_LICENSE_PRODUCT_ID > 0
			? (int) SEOISTIC_LICENSE_PRODUCT_ID
			: (int) get_option( self::OPT_PRODUCT, 0 );

		/**
		 * Legacy compatibility filter. The canonical API resolves the product
		 * from the key prefix, so product_id is no longer sent by this client.
		 *
		 * @param int $id
		 */
		return (int) apply_filters( 'seoistic_license_product_id', $id );
	}

	/**
	 * The decrypted license key. Pre-1.3 installs stored it in plaintext;
	 * detect and transparently re-save that value encrypted on first read.
	 */
	public function key(): string {
		$stored = (string) get_option( self::OPT_KEY, '' );
		if ( '' === $stored ) {
			return '';
		}

		$decrypted = Crypto::decrypt( $stored, self::CRYPTO_CONTEXT );
		if ( $this->looks_like_key( $decrypted ) ) {
			return $decrypted;
		}
		if ( $this->looks_like_key( $stored ) ) {
			update_option( self::OPT_KEY, Crypto::encrypt( $stored, self::CRYPTO_CONTEXT ), false );
			return $stored;
		}
		return '';
	}

	/** Last 4 characters visible, everything else masked. */
	public function masked_key(): string {
		$key = $this->key();
		$len = strlen( $key );
		if ( 0 === $len ) {
			return '';
		}
		if ( $len <= 4 ) {
			return str_repeat( '•', $len );
		}
		return str_repeat( '•', $len - 4 ) . substr( $key, -4 );
	}

	private function looks_like_key( string $value ): bool {
		return '' !== $value && 1 === preg_match( '/^[A-Za-z0-9\-_.]{4,128}$/', $value );
	}

	public function status(): string {
		return (string) get_option( self::OPT_STATUS, 'inactive' );
	}

	public function expires(): string {
		return (string) get_option( self::OPT_EXPIRES, '' );
	}

	public function last_validated(): string {
		$ts = (int) get_option( self::OPT_LAST_OK, 0 );
		if ( $ts <= 0 ) {
			return '';
		}
		return wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $ts );
	}

	/**
	 * Additional fields returned by WPistic, including the authoritative plan
	 * and entitlement map. Secrets and the response signature are excluded.
	 *
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		$meta = get_option( self::OPT_META, array() );
		return is_array( $meta ) ? $meta : array();
	}

	public function is_valid(): bool {
		if ( '' === $this->key() || 'active' !== $this->status() || $this->expired() ) {
			return false;
		}
		$last_ok = (int) get_option( self::OPT_LAST_OK, 0 );
		return $last_ok > 0 && ( time() - $last_ok ) < self::TRUST_WINDOW;
	}

	private function expired(): bool {
		$expires = $this->expires();
		return '' !== $expires && strtotime( $expires . ' UTC' ) < time();
	}

	private function instance(): string {
		$instance = (string) get_option( self::OPT_INSTANCE, '' );
		if ( '' === $instance ) {
			$instance = wp_generate_uuid4();
			update_option( self::OPT_INSTANCE, $instance, false );
		}
		return $instance;
	}

	/**
	 * The decrypted activation token used by first-party WPistic services.
	 * It is never rendered in wp-admin or written to logs; callers should only
	 * send it over HTTPS to an authenticated service endpoint.
	 */
	public function activation_token(): string {
		$stored = (string) get_option( self::OPT_TOKEN, '' );
		return Crypto::decrypt( $stored, self::TOKEN_CRYPTO_CONTEXT );
	}

	public function installation_uuid(): string {
		return $this->instance();
	}

	public function domain(): string {
		$url  = (string) home_url();
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return '' !== (string) $host ? strtolower( (string) $host ) : trim( $url, '/' );
	}

	public function environment(): string {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return in_array( $environment, array( 'production', 'staging', 'development', 'local' ), true ) ? $environment : 'production';
	}

	/* -------------------------------------------------------------- */
	/* Rate limiting — enforced before any network call.              */
	/* -------------------------------------------------------------- */

	public function is_rate_limited(): bool {
		return (int) get_transient( self::RATE_LIMIT_TRANSIENT ) >= self::RATE_LIMIT_MAX;
	}

	public function record_attempt(): void {
		$count = (int) get_transient( self::RATE_LIMIT_TRANSIENT );
		set_transient( self::RATE_LIMIT_TRANSIENT, $count + 1, self::RATE_LIMIT_WINDOW );
	}

	/* -------------------------------------------------------------- */
	/* Requests                                                        */
	/* -------------------------------------------------------------- */

	/** @return array<string, mixed> */
	private function activation_payload( string $key ): array {
		return array(
			'key'               => $key,
			'domain'            => $this->domain(),
			'environment'       => $this->environment(),
			'installation_uuid' => $this->instance(),
			'site_url'          => function_exists( 'site_url' ) ? site_url() : home_url(),
			'home_url'          => home_url(),
			'product_version'   => defined( 'SEOISTIC_VERSION' ) ? SEOISTIC_VERSION : '',
			'wp_version'        => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'php_version'       => PHP_VERSION,
		);
	}

	/** @return array<string, mixed> */
	private function post( string $endpoint, array $body ): array {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( 'activate' === $endpoint ) {
			// Prevent a browser retry from creating duplicate activation events.
			$headers['X-Idempotency-Key'] = 'seoistic-' . wp_generate_uuid4();
		}

		$response = wp_remote_post(
			$this->server() . '/api/v1/licenses/' . $endpoint,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message(), 'code' => 'network' );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $http_code >= 500 ) {
			return array( 'success' => false, 'message' => __( 'The license server is temporarily unavailable.', 'seoistic' ), 'code' => 'network' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array( 'success' => false, 'message' => __( 'The license server returned an unexpected response.', 'seoistic' ), 'code' => 'bad_response' );
		}

		// Canonical API errors are {error:{code,message}}.
		if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$error = $data['error'];
			return array(
				'success' => false,
				'message' => isset( $error['message'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $error['message'] ) ) : '',
				'code'    => isset( $error['code'] ) ? sanitize_key( (string) $error['code'] ) : 'failed',
				'data'    => array(),
			);
		}

		// Keep parsing the old response shape for self-hosted/staging installs.
		if ( array_key_exists( 'success', $data ) ) {
			return array(
				'success' => (bool) $data['success'],
				'message' => isset( $data['message'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $data['message'] ) ) : '',
				'code'    => isset( $data['code'] ) ? sanitize_key( (string) $data['code'] ) : '',
				'data'    => isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array(),
			);
		}

		if ( 'deactivate' === $endpoint && ! empty( $data['deactivated'] ) ) {
			return array( 'success' => true, 'data' => $data );
		}

		// Canonical activation/validation responses contain a valid boolean.
		if ( array_key_exists( 'valid', $data ) ) {
			return array(
				'success' => (bool) $data['valid'],
				'message' => (bool) $data['valid'] ? '' : __( 'The license is not valid for this site.', 'seoistic' ),
				'code'    => (bool) $data['valid'] ? '' : sanitize_key( (string) ( $data['status'] ?? 'invalid_license' ) ),
				'data'    => $data,
			);
		}

		return array( 'success' => false, 'message' => __( 'The license server returned an unexpected response.', 'seoistic' ), 'code' => 'bad_response' );
	}

	/** @param array<string, mixed> $result */
	private function is_transient_failure( array $result ): bool {
		return in_array( (string) ( $result['code'] ?? '' ), array( 'network', 'bad_response' ), true );
	}

	private function normalize_status( string $raw ): string {
		return match ( sanitize_key( $raw ) ) {
			'active', 'valid', 'grace_period' => 'active',
			'expired' => 'expired',
			'inactive' => 'inactive',
			default => 'invalid',
		};
	}

	private function sanitize_date( string $value ): string {
		return '' !== $value && false !== strtotime( $value ) ? $value : '';
	}

	/** @param array<string, mixed> $data */
	private function cache_response( array $data ): void {
		$status = $this->normalize_status( (string) ( $data['status'] ?? ( ! empty( $data['valid'] ) ? 'active' : 'invalid' ) ) );
		update_option( self::OPT_STATUS, $status, false );
		update_option( self::OPT_EXPIRES, $this->sanitize_date( (string) ( $data['expires_at'] ?? '' ) ), false );
		update_option( 'seoistic_license_product_active', absint( $data['product_id'] ?? 0 ), false );

		$meta = array_diff_key( $data, array_flip( array( 'activation_token', 'verification_key', 'signature' ) ) );
		update_option( self::OPT_META, $meta, false );

		if ( ! empty( $data['valid'] ) && 'active' === $status ) {
			update_option( self::OPT_LAST_OK, time(), false );
		}
	}

	/** @param array<string, mixed> $result */
	private function store_activation( array $result ): void {
		$data = (array) ( $result['data'] ?? array() );
		if ( ! empty( $data['activation_token'] ) ) {
			update_option( self::OPT_TOKEN, Crypto::encrypt( (string) $data['activation_token'], self::TOKEN_CRYPTO_CONTEXT ), false );
		}
		if ( ! empty( $data['verification_key'] ) ) {
			update_option( self::OPT_VERIFY, Crypto::encrypt( (string) $data['verification_key'], self::VERIFY_CRYPTO_CONTEXT ), false );
		}
		if ( ! array_key_exists( 'valid', $data ) && ! empty( $result['success'] ) ) {
			$data['valid'] = true;
		}
		$this->cache_response( $data );
	}

	private function clear_validation_state(): void {
		foreach ( array( self::OPT_STATUS, self::OPT_EXPIRES, self::OPT_TOKEN, self::OPT_VERIFY, self::OPT_LAST_OK, self::OPT_LAST_CHECK, self::OPT_FAIL_COUNT, self::OPT_META, 'seoistic_license_product_active' ) as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Immediate, real-time activation. The canonical API response is cached
	 * directly, avoiding a second request and an unnecessary rate-limit hit.
	 *
	 * @return array<string, mixed>
	 */
	public function activate( string $key ): array {
		$previous_key = $this->key();
		$result       = $this->post( 'activate', $this->activation_payload( $key ) );

		if ( ! empty( $result['success'] ) ) {
			if ( $key !== $previous_key ) {
				$this->clear_validation_state();
			}
			update_option( self::OPT_KEY, Crypto::encrypt( $key, self::CRYPTO_CONTEXT ), false );
			update_option( self::OPT_FAIL_COUNT, 0, false );
			update_option( self::OPT_LAST_CHECK, time(), false );
			$this->store_activation( $result );
		}

		return $result;
	}

	/** @return array<string, mixed> */
	public function deactivate(): array {
		$key   = $this->key();
		$token = $this->activation_token();
		if ( '' === $key || '' === $token ) {
			$this->clear_validation_state();
			delete_option( self::OPT_KEY );
			return array( 'success' => true );
		}

		$result = $this->post(
			'deactivate',
			array(
				'activation_token'  => $token,
				'installation_uuid' => $this->instance(),
			)
		);

		if ( ! empty( $result['success'] ) ) {
			$this->clear_validation_state();
			delete_option( self::OPT_KEY );
		}
		return $result;
	}

	/**
	 * Validate the activation token, cache the server response, and preserve a
	 * confirmed active state across temporary network failures.
	 */
	public function validate(): bool {
		$key = $this->key();
		if ( '' === $key ) {
			update_option( self::OPT_STATUS, 'inactive', false );
			return false;
		}

		$fail_count = (int) get_option( self::OPT_FAIL_COUNT, 0 );
		if ( $fail_count > 0 ) {
			$last_check = (int) get_option( self::OPT_LAST_CHECK, 0 );
			$wait       = min( self::BACKOFF_MAX, self::BACKOFF_BASE * $fail_count );
			if ( ( time() - $last_check ) < $wait ) {
				return $this->is_valid();
			}
		}

		$token = $this->activation_token();
		if ( '' === $token ) {
			// Migrate a key-only pre-canonical install through activation so the
			// API can issue its activation token for this installation.
			$result = $this->activate( $key );
			return ! empty( $result['success'] ) && $this->is_valid();
		}

		update_option( self::OPT_LAST_CHECK, time(), false );
		$result = $this->post(
			'validate',
			array(
				'activation_token'  => $token,
				'domain'            => $this->domain(),
				'environment'       => $this->environment(),
				'installation_uuid' => $this->instance(),
				'plugin_version'    => defined( 'SEOISTIC_VERSION' ) ? SEOISTIC_VERSION : '',
			)
		);

		if ( $this->is_transient_failure( $result ) ) {
			update_option( self::OPT_FAIL_COUNT, $fail_count + 1, false );
			return $this->is_valid();
		}
		update_option( self::OPT_FAIL_COUNT, 0, false );

		if ( isset( $result['data'] ) && is_array( $result['data'] ) && array_key_exists( 'valid', $result['data'] ) ) {
			$this->cache_response( $result['data'] );
		}

		if ( ! empty( $result['success'] ) ) {
			return $this->is_valid();
		}

		update_option( self::OPT_STATUS, 'invalid', false );
		return false;
	}
}
