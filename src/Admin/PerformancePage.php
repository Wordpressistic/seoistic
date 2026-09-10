<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Core\Performance\PerformanceService;

final class PerformancePage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 31 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_seoistic_save_performance', array( $this, 'save_settings' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'seoistic',
			__( 'Performance', 'seoistic' ),
			__( 'Performance', 'seoistic' ),
			'manage_options',
			'seoistic-performance',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'seoistic-performance' ) ) {
			return;
		}
		wp_enqueue_script(
			'seoistic-performance',
			SEOISTIC_URL . 'assets/js/performance.js',
			array( 'seoistic-admin' ),
			SEOISTIC_VERSION,
			true
		);
		wp_localize_script(
			'seoistic-performance',
			'SeoisticPerformance',
			array(
				'i18n' => array(
					'run' => __( 'Run PageSpeed check', 'seoistic' ),
					'running' => __( 'Checking…', 'seoistic' ),
					'confirm' => __( 'Apply this optimization? Review the dry-run preview first.', 'seoistic' ),
					'preview' => __( 'Preview changes', 'seoistic' ),
					'previewing' => __( 'Building preview…', 'seoistic' ),
				),
			)
		);
	}

	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_save_performance' ) ) {
			wp_die( esc_html__( 'You are not allowed to change performance settings.', 'seoistic' ) );
		}

		PerformanceService::save_settings(
			array(
				'enabled' => isset( $_POST['enabled'] ),
				'strategy' => sanitize_key( wp_unslash( (string) ( $_POST['strategy'] ?? 'mobile' ) ) ),
				'notify_email' => sanitize_email( wp_unslash( (string) ( $_POST['notify_email'] ?? '' ) ) ),
				'hero_url' => esc_url_raw( wp_unslash( (string) ( $_POST['hero_url'] ?? '' ) ) ),
				'lcp' => (float) wp_unslash( ( $_POST['lcp'] ?? PerformanceService::DEFAULT_LCP ) ),
				'cls' => (float) wp_unslash( ( $_POST['cls'] ?? PerformanceService::DEFAULT_CLS ) ),
				'inp' => (float) wp_unslash( ( $_POST['inp'] ?? PerformanceService::DEFAULT_INP ) ),
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-performance&updated=1' ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access Performance.', 'seoistic' ) );
		}

		$settings = PerformanceService::settings();
		$cards = PerformanceService::dashboard_cards();
		View::header( 'seoistic-performance', __( 'Performance', 'seoistic' ), __( 'Monitor Core Web Vitals and apply low-risk speed improvements.', 'seoistic' ) );
		$latest = PerformanceService::latest();
		$latest_url = is_array( $latest ) ? (string) ( $latest['url'] ?? home_url( '/' ) ) : home_url( '/' );
		$this->render_cards( $cards, $latest_url );
		$this->render_check();
		$this->render_quick_wins();
		$this->render_settings( $settings );
		View::footer();
	}

	private function render_cards( array $cards, string $url ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Core Web Vitals', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-cards">';
		foreach ( array( 'lcp', 'cls', 'inp' ) as $metric ) {
			$card = $cards[ $metric ];
			$value = null === $card['value'] ? '—' : PerformanceService::format_metric( $metric, $card['value'] );
			$trend = null === $card['change'] ? __( 'No weekly trend', 'seoistic' ) : sprintf( '%s%+.1f%%', 0.0 === $card['change'] || $card['change'] < 0 ? '' : '+', $card['change'] );
			View::card( 'performance', $value, $card['label'], $card['tone'], $trend, 0.0 === $card['change'] || ( null !== $card['change'] && $card['change'] < 0 ) ? 'good' : 'bad' );
		}
		echo '</div>';
		echo '<p class="description">' . esc_html( sprintf( __( 'Latest checked URL: %s', 'seoistic' ), $url ) ) . '</p>';
	}

	private function render_check(): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'PageSpeed Insights', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-analytics"></span></div><strong>' . esc_html__( 'Run a page check', 'seoistic' ) . '</strong></div>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-psi-url">' . esc_html__( 'URL', 'seoistic' ) . '</label>';
		$default_url = $latest_url;
		echo '<input class="regular-text" type="url" id="seoistic-psi-url" name="psi-url" value="' . esc_url( $default_url ) . '"></div>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-psi-strategy">' . esc_html__( 'Device', 'seoistic' ) . '</label>';
		echo '<select id="seoistic-psi-strategy"><option value="mobile">' . esc_html__( 'Mobile', 'seoistic' ) . '</option><option value="desktop">' . esc_html__( 'Desktop', 'seoistic' ) . '</option></select></div>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-psi-run>' . esc_html__( 'Run PageSpeed check', 'seoistic' ) . '</button>';
		echo '<div class="seoistic-tool-result" data-seoistic-psi-result hidden></div>';
		echo '</div>';
	}

	private function render_quick_wins(): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Quick Wins', 'seoistic' ) . '</div>';
		echo '<p class="description">' . esc_html__( 'Every change is previewed and confirmed before it can modify .htaccess.', 'seoistic' ) . '</p>';
		echo '<div class="seoistic-cards">';
		foreach ( PerformanceService::quick_wins() as $win ) {
			echo '<div class="seoistic-tool-card">';
			echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-controls-play"></span></div><strong>' . esc_html( $win['title'] ) . '</strong>' . ( $win['enabled'] ? View::badge( __( 'Enabled', 'seoistic' ), 'good' ) : '' ) . '</div>';
			echo '<p>' . esc_html( $win['description'] ) . '</p>';
			echo '<p class="description">' . esc_html( $win['conflict'] ) . '</p>';
			echo '<div class="seoistic-tool-result" data-seoistic-quick-win-result hidden></div>';
			echo '<div class="seoistic-hero-actions">';
			echo '<button type="button" class="seoistic-btn" data-seoistic-quick-win-preview="' . esc_attr( $win['action'] ) . '">' . esc_html__( 'Preview changes', 'seoistic' ) . '</button>';
			echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-quick-win-apply="' . esc_attr( $win['action'] ) . '" data-seoistic-confirm="' . esc_attr__( 'Apply this optimization? Review the dry-run preview first.', 'seoistic' ) . '">' . esc_html__( 'Apply', 'seoistic' ) . '</button>';
			if ( $win['enabled'] ) {
				echo '<button type="button" class="seoistic-btn" data-seoistic-quick-win-disable="' . esc_attr( $win['action'] ) . '" data-seoistic-confirm="' . esc_attr__( 'Disable this optimization? Server-side .htaccess rules are not removed automatically.', 'seoistic' ) . '">' . esc_html__( 'Disable', 'seoistic' ) . '</button>';
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	private function render_settings( array $settings ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Monitoring & hero preload', 'seoistic' ) . '</div>';
		echo '<form class="seoistic-tool-card" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seoistic_save_performance">';
		wp_nonce_field( 'seoistic_save_performance' );
		$this->field_checkbox( 'enabled', __( 'Enable weekly monitoring and threshold alerts', 'seoistic' ), $settings['enabled'] );
		$this->field_select( 'strategy', __( 'Device', 'seoistic' ), array( 'mobile' => __( 'Mobile', 'seoistic' ), 'desktop' => __( 'Desktop', 'seoistic' ) ), $settings['strategy'] );
		$this->field_email( 'notify_email', __( 'Alert email', 'seoistic' ), $settings['notify_email'], get_option( 'admin_email' ) );
		$this->field_url( 'hero_url', __( 'Hero image URL for preload', 'seoistic' ), $settings['hero_url'], PerformanceService::hero_url() );
		$this->field_number( 'lcp', __( 'LCP threshold (seconds)', 'seoistic' ), $settings['lcp'], 2.5 );
		$this->field_number( 'cls', __( 'CLS threshold', 'seoistic' ), $settings['cls'], 0.1 );
		$this->field_number( 'inp', __( 'INP threshold (seconds)', 'seoistic' ), $settings['inp'], 0.2 );
		echo '<button type="submit" class="seoistic-btn seoistic-btn-primary">' . esc_html__( 'Save monitoring settings', 'seoistic' ) . '</button>';
		echo '</form>';
	}

	private function field_checkbox( string $name, string $label, bool $checked ): void {
		echo '<div class="seoistic-field"><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label></div>';
	}

	private function field_select( string $name, string $label, array $options, string $value ): void {
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><select id="seoistic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option_value => $option_label ) {
			echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $value, (string) $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></div>';
	}

	private function field_email( string $name, string $label, string $value, string $placeholder ): void {
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input type="email" id="seoistic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"></div>';
	}

	private function field_url( string $name, string $label, string $value, string $placeholder ): void {
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input type="url" class="regular-text" id="seoistic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"></div>';
	}

	private function field_number( string $name, string $label, float $value, float $default ): void {
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input type="number" step="0.001" id="seoistic-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="' . esc_attr( (string) $default ) . '"></div>';
	}
}
