<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Addon\BusinessAutomatorModule;
use Wpistic\Seoistic\BusinessAutomator\AutomatorClient;
use Wpistic\Seoistic\BusinessAutomator\ScriptTemplates;
use Wpistic\Seoistic\Core\Crypto;

final class BusinessAutomatorPage {

	private const TOKEN_OPTION = 'seoistic_automator_api_token';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_submenu(): void {
		add_submenu_page(
			'seoistic',
			__( 'Business Automator', 'seoistic' ),
			__( 'Business Automator', 'seoistic' ),
			'manage_options',
			'seoistic-business-automator',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting( 'seoistic_business_automator', 'seoistic_automator_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'seoistic_business_automator', self::TOKEN_OPTION, array( 'sanitize_callback' => array( $this, 'sanitize_token' ) ) );
		register_setting( 'seoistic_business_automator', 'seoistic_automator_enabled' );
	}

	/**
	 * Encrypts the submitted token before storage (same at-rest pattern as
	 * every other provider secret in the plugin). The settings form always
	 * renders this field blank (see render_settings_panel()), so an empty
	 * submission means "leave the saved token unchanged" — otherwise saving
	 * the form just to update the URL would silently wipe out an
	 * already-connected token.
	 */
	public function sanitize_token( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			$existing = get_option( self::TOKEN_OPTION, '' );
			return is_string( $existing ) ? $existing : '';
		}
		return Crypto::encrypt( $value, BusinessAutomatorModule::CRYPTO_CONTEXT );
	}

	public function enqueue_assets(): void {
		if ( ! isset( $_GET['page'] ) || 'seoistic-business-automator' !== $_GET['page'] ) {
			return;
		}

		wp_enqueue_style( 'seoistic-admin' );
		wp_enqueue_script( 'seoistic-business-automator', plugins_url( 'assets/js/business-automator.js', SEOISTIC_FILE ), array( 'wp-api-fetch' ), SEOISTIC_VERSION, true );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'seoistic' ) );
		}

		$url          = get_option( 'seoistic_automator_url' );
		$is_connected = $url && '' !== get_option( self::TOKEN_OPTION, '' );

		?>
		<div class="wrap seoistic-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! $is_connected ) : ?>
				<div class="notice notice-info">
					<p><?php echo wp_kses_post( __( 'Connect your WPistic Business Automator to get started with automated monitoring and tasks.', 'seoistic' ) ); ?></p>
				</div>
			<?php endif; ?>

			<div class="seoistic-tabs">
				<nav class="seoistic-tab-list">
					<a href="#settings" class="seoistic-tab-link active"><?php echo esc_html__( 'Settings', 'seoistic' ); ?></a>
					<?php if ( $is_connected ) : ?>
						<a href="#automations" class="seoistic-tab-link"><?php echo esc_html__( 'Automations', 'seoistic' ); ?></a>
						<a href="#templates" class="seoistic-tab-link"><?php echo esc_html__( 'Templates', 'seoistic' ); ?></a>
					<?php endif; ?>
				</nav>

				<div id="settings" class="seoistic-tab-panel active">
					<?php $this->render_settings_panel(); ?>
				</div>

				<?php if ( $is_connected ) : ?>
					<div id="automations" class="seoistic-tab-panel">
						<?php $this->render_automations_panel(); ?>
					</div>

					<div id="templates" class="seoistic-tab-panel">
						<?php $this->render_templates_panel(); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_settings_panel(): void {
		$url        = get_option( 'seoistic_automator_url' );
		$has_token  = '' !== get_option( self::TOKEN_OPTION, '' );
		?>
		<div class="seoistic-card">
			<h2><?php echo esc_html__( 'Connection Settings', 'seoistic' ); ?></h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'seoistic_business_automator' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="seoistic_automator_url">
								<?php echo esc_html__( 'Business Automator URL', 'seoistic' ); ?>
							</label>
						</th>
						<td>
							<input
								type="url"
								id="seoistic_automator_url"
								name="seoistic_automator_url"
								value="<?php echo esc_attr( $url ); ?>"
								placeholder="https://automator.example.com"
								class="regular-text"
								required
							/>
							<p class="description"><?php echo esc_html__( 'The URL of your WPistic Business Automator instance', 'seoistic' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="seoistic_automator_api_token">
								<?php echo esc_html__( 'API Token', 'seoistic' ); ?>
							</label>
						</th>
						<td>
							<input
								type="password"
								id="seoistic_automator_api_token"
								name="<?php echo esc_attr( self::TOKEN_OPTION ); ?>"
								value=""
								autocomplete="off"
								placeholder="<?php echo esc_attr( $has_token ? __( '•••••••••••••••• (saved — enter a new token to replace it)', 'seoistic' ) : __( 'Your API token', 'seoistic' ) ); ?>"
								class="regular-text"
								<?php echo $has_token ? '' : 'required'; ?>
							/>
							<p class="description"><?php echo esc_html__( 'Generate this token in your Business Automator settings. Leave blank to keep the currently saved token.', 'seoistic' ); ?></p>
						</td>
					</tr>
				</table>

				<div style="margin-top: 20px;">
					<?php submit_button( __( 'Save Settings', 'seoistic' ) ); ?>
					<button type="button" id="test-connection-btn" class="button button-secondary" style="margin-left: 10px;">
						<?php echo esc_html__( 'Test Connection', 'seoistic' ); ?>
					</button>
				</div>
			</form>

			<div id="connection-status" style="margin-top: 20px; display: none;"></div>
		</div>
		<?php
	}

	private function render_automations_panel(): void {
		?>
		<div class="seoistic-card">
			<h2><?php echo esc_html__( 'Active Automations', 'seoistic' ); ?></h2>
			<p><?php echo esc_html__( 'Manage your automated tasks and monitoring scripts', 'seoistic' ); ?></p>

			<div id="automations-list" style="margin-top: 20px;">
				<p><?php echo esc_html__( 'Loading automations...', 'seoistic' ); ?></p>
			</div>

			<button type="button" id="create-automation-btn" class="button button-primary" style="margin-top: 20px;">
				<?php echo esc_html__( 'Create Automation', 'seoistic' ); ?>
			</button>
		</div>
		<?php
	}

	private function render_templates_panel(): void {
		$templates = ScriptTemplates::get_all();
		?>
		<div class="seoistic-card">
			<h2><?php echo esc_html__( 'Script Templates', 'seoistic' ); ?></h2>
			<p><?php echo esc_html__( 'Choose from pre-built automation templates for common tasks', 'seoistic' ); ?></p>

			<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
				<?php foreach ( $templates as $template_id => $template ) : ?>
					<div class="seoistic-template-card" style="border: 1px solid #ccc; padding: 15px; border-radius: 5px;">
						<h3><?php echo esc_html( $template['name'] ); ?></h3>
						<p><?php echo esc_html( $template['description'] ); ?></p>
						<p style="font-size: 12px; color: #666;">
							<?php
							echo esc_html__( 'Category:', 'seoistic' ) . ' ' . esc_html( $template['category'] );
							echo ' | ';
							echo esc_html__( 'Tier:', 'seoistic' ) . ' ' . esc_html( $template['tier'] );
							?>
						</p>
						<button
							type="button"
							class="button button-secondary deploy-template"
							data-template-id="<?php echo esc_attr( $template_id ); ?>"
						>
							<?php echo esc_html__( 'Deploy', 'seoistic' ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
