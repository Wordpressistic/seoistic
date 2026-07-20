<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\License;

use Wpistic\Seoistic\Core\Crypto;

/**
 * Talks to a Licenseistic server (default wpistic.com) over its public REST API
 * — POST {server}/wp-json/licenseistic/v1/license/{activate,deactivate,ping} —
 * to activate / validate / deactivate a SEOistic license. No API key needed
 * (public, IP-rate-limited server-side); this client adds its own rate limit
 * on the activation form so a single site can't hammer the server either.
 *
 * The license key is encrypted at rest (Core\Crypto, same pattern as the AI
 * provider keys) and is never echoed back in full once it has been read once
 * — callers needing to show it in the UI must use masked_key().
 *
 * Server/product configuration is no longer a wp-admin-editable setting (see
 * SEOISTIC_LICENSE_API_URL / SEOISTIC_LICENSE_PRODUCT_ID in seoistic.php and
 * the seoistic_license_api_url / seoistic_license_product_id filters) — the
 * legacy options are still *read* as a fallback so a pre-1.3 install that had
 * customized them keeps working, but nothing writes to them anymore.
 */
final class LicenseClient {

	private const OPT_KEY          = 'seoistic_license_key';
	private const OPT_STATUS       = 'seoistic_license_status';
	private const OPT_EXPIRES      = 'seoistic_license_expires';
	private const OPT_SERVER       = 'seoistic_license_server';   // Legacy fallback only — no longer written.
	private const OPT_PRODUCT      = 'seoistic_license_product';  // Legacy fallback only — no longer written.
	private const OPT_INSTANCE     = 'seoistic_license_instance';
	private const OPT_LAST_OK      = 'seoistic_license_last_ok';
	private const OPT_LAST_CHECK   = 'seoistic_license_last_check';
	private const OPT_FAIL_COUNT   = 'seoistic_license_fail_count';
	private const OPT_META         = 'seoistic_license_meta';

	private const CRYPTO_CONTEXT = 'license';

	/** A confirmed-"active" status stays trusted for this long without a fresh
	 *  server confirmation — a single outage/timeout must never instantly
	 *  downgrade a paying site to Free. A genuine revoke/expiry from the
	 *  server still applies immediately (see validate()). */
	private const TRUST_WINDOW = 30 * DAY_IN_SECONDS;

	private const BACKOFF_BASE = HOUR_IN_SECONDS;
	private const BACKOFF_MAX  = 12 * HOUR_IN_SECONDS;

	private const RATE_LIMIT_TRANSIENT = 'seoistic_license_rl';
	private const RATE_LIMIT_MAX       = 5;
	private const RATE_LIMIT_WINDOW    = 10 * MINUTE_IN_SECONDS;

	public function server(): string {
		$base = defined( 'SEOISTIC_LICENSE_API_URL' ) && '' !== SEOISTIC_LICENSE_API_URL
			? SEOISTIC_LICENSE_API_URL
			: (string) get_option( self::OPT_SERVER, 'https://wpistic.com' );

		/**
		 * Filter the Licenseistic API base URL. Advanced deployment override —
		 * not exposed as an editable wp-admin field.
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
		 * Filter the Licenseistic product id this install validates against.
		 *
		 * @param int $id
		 */
		return (int) apply_filters( 'seoistic_license_product_id', $id );
	}

	/**
	 * The decrypted license key. Pre-1.3 installs stored it in plaintext —
	 * detected and transparently re-saved encrypted on first read (idempotent,
	 * no visible migration step, no data loss).
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

	/**
	 * Last 4 characters visible, everything else masked — the only form the
	 * key may ever appear in once activated.
	 */
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
	 * Any additional fields the server returned on the last successful ping
	 * (e.g. site usage counts) beyond the fields this client explicitly
	 * models. Never fabricated — empty unless the server actually sent it.
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

	/* -------------------------------------------------------------- */
	/* Rate limiting — server-side, enforced before any network call.   */
	/* -------------------------------------------------------------- */

	public function is_rate_limited(): bool {
		return (int) get_transient( self::RATE_LIMIT_TRANSIENT ) >= self::RATE_LIMIT_MAX;
	}

	public function record_attempt(): void {
		$count = (int) get_transient( self::RATE_LIMIT_TRANSIENT );
		set_transient( self::RATE_LIMIT_TRANSIENT, $count + 1, self::RATE_LIMIT_WINDOW );
	}

	/* -------------------------------------------------------------- */
	/* Requests                                                          */
	/* -------------------------------------------------------------- */

	/**
	 * @return array<string, mixed>
	 */
	private function payload( string $key ): array {
		$payload = array(
			'license_key' => $key,
			'site_url'    => home_url(),
			'instance_id' => $this->instance(),
		);
		if ( $this->product_id() > 0 ) {
			$payload['product_id'] = $this->product_id();
		}
		return $payload;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array{success:bool, message?:string, code?:string, data?:array<string,mixed>}
	 */
	private function post( string $endpoint, array $body ): array {
		$response = wp_remote_post(
			$this->server() . '/wp-json/licenseistic/v1/license/' . $endpoint,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
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
		if ( ! is_array( $data ) || ! array_key_exists( 'success', $data ) ) {
			return array( 'success' => false, 'message' => __( 'Unexpected response from the license server.', 'seoistic' ), 'code' => 'bad_response' );
		}

		return array(
			'success' => (bool) $data['success'],
			'message' => isset( $data['message'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $data['message'] ) ) : '',
			'code'    => isset( $data['code'] ) ? sanitize_key( (string) $data['code'] ) : '',
			'data'    => isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array(),
		);
	}

	/**
	 * A request the server never received/answered (network failure, 5xx, or
	 * an unparseable body) — ambiguous, must never be treated the same as an
	 * explicit rejection from the server.
	 *
	 * @param array<string, mixed> $result
	 */
	private function is_transient_failure( array $result ): bool {
		return in_array( (string) ( $result['code'] ?? '' ), array( 'network', 'bad_response' ), true );
	}

	private function normalize_status( string $raw ): string {
		return match ( sanitize_key( $raw ) ) {
			'active', 'valid' => 'active',
			'expired' => 'expired',
			'inactive' => 'inactive',
			default => 'invalid', // revoked, cancelled, not_found, wrong_product, site_limit, …
		};
	}

	private function sanitize_date( string $value ): string {
		return '' !== $value && false !== strtotime( $value ) ? $value : '';
	}

	/**
	 * Immediate, real-time activation — no grace/backoff (a fresh user action
	 * must reflect the true, current server answer).
	 *
	 * @return array<string, mixed>
	 */
	public function activate( string $key ): array {
		$result = $this->post( 'activate', $this->payload( $key ) );
		if ( ! empty( $result['success'] ) ) {
			update_option( self::OPT_KEY, Crypto::encrypt( $key, self::CRYPTO_CONTEXT ) );
			update_option( self::OPT_FAIL_COUNT, 0, false );
			update_option( self::OPT_LAST_CHECK, time(), false );
			$this->validate();
		}
		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function deactivate(): array {
		$key = $this->key();
		if ( '' === $key ) {
			return array( 'success' => true );
		}
		$result = $this->post( 'deactivate', array( 'license_key' => $key, 'site_url' => home_url(), 'instance_id' => $this->instance() ) );

		foreach ( array( self::OPT_KEY, self::OPT_STATUS, self::OPT_EXPIRES, self::OPT_LAST_OK, self::OPT_LAST_CHECK, self::OPT_FAIL_COUNT, self::OPT_META, 'seoistic_license_product_active' ) as $option ) {
			delete_option( $option );
		}
		return $result;
	}

	/**
	 * Ping the server, cache status/expiry, and return whether the license is
	 * valid. Called from the daily cron and right after activate(). A single
	 * unreachable server backs off (capped exponential) and never overwrites
	 * the last authoritative status — only an explicit server response
	 * (success or an explicit rejection) changes the stored status.
	 */
	public function validate(): bool {
		$key = $this->key();
		if ( '' === $key ) {
			update_option( self::OPT_STATUS, 'inactive' );
			return false;
		}

		$fail_count = (int) get_option( self::OPT_FAIL_COUNT, 0 );
		if ( $fail_count > 0 ) {
			$last_check = (int) get_option( self::OPT_LAST_CHECK, 0 );
			$wait       = min( self::BACKOFF_MAX, self::BACKOFF_BASE * $fail_count );
			if ( ( time() - $last_check ) < $wait ) {
				return $this->is_valid(); // Still backing off — trust the cached state.
			}
		}

		update_option( self::OPT_LAST_CHECK, time(), false );

		$body                      = $this->payload( $key );
		$body['installed_version'] = defined( 'SEOISTIC_VERSION' ) ? SEOISTIC_VERSION : '';
		$result                    = $this->post( 'ping', $body );

		if ( $this->is_transient_failure( $result ) ) {
			update_option( self::OPT_FAIL_COUNT, $fail_count + 1, false );
			return $this->is_valid(); // Leave the last authoritative status untouched.
		}
		update_option( self::OPT_FAIL_COUNT, 0, false );

		if ( ! empty( $result['success'] ) ) {
			$data   = (array) ( $result['data'] ?? array() );
			$status = $this->normalize_status( (string) ( $data['status'] ?? '' ) );

			update_option( self::OPT_STATUS, $status );
			update_option( self::OPT_EXPIRES, $this->sanitize_date( (string) ( $data['expires_at'] ?? '' ) ) );
			update_option( 'seoistic_license_product_active', absint( $data['product_id'] ?? 0 ) );
			update_option( self::OPT_META, array_diff_key( $data, array_flip( array( 'status', 'expires_at', 'product_id' ) ) ), false );

			if ( 'active' === $status ) {
				update_option( self::OPT_LAST_OK, time(), false );
			}
			return $this->is_valid();
		}

		// The server responded but explicitly rejected the key (revoked, wrong
		// product, not found) — authoritative, applied immediately.
		update_option( self::OPT_STATUS, 'invalid' );
		return false;
	}
}
