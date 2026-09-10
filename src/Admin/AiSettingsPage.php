<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\AI\AiSettings;
use Wpistic\Seoistic\Core\AI\WpisticAiClient;

/**
 * SEOISTIC → Settings → AI. Uses managed WPistic AI credits; Business and
 * Agency can optionally configure an encrypted custom-model endpoint.
 */
final class AiSettingsPage {

	public function register(): void {
		add_action( 'admin_post_seoistic_save_ai_settings', array( $this, 'save' ) );
	}

	public function render_fields(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = AiSettings::all();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoistic_save_ai_settings">
			<?php wp_nonce_field( 'seoistic_ai_settings' ); ?>
			<div class="seoistic-table-wrap">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable AI Features', 'seoistic' ); ?></th>
					<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Enable AI generators and buttons', 'seoistic' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WPistic AI', 'seoistic' ); ?></th>
					<td><p class="description"><strong><?php esc_html_e( 'No provider API keys needed.', 'seoistic' ); ?></strong> <?php esc_html_e( 'Generation uses your license and monthly AI credits. A cached repeat request costs 0 credits.', 'seoistic' ); ?></p></td>
				</tr></table>

			<?php if ( AiSettings::custom_models_allowed() ) : ?>
				<h2><?php esc_html_e( 'Custom AI Model (Business perk)', 'seoistic' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="seoistic_ai_custom_base_url"><?php esc_html_e( 'OpenAI-compatible base URL', 'seoistic' ); ?></label></th>
						<td><input type="url" id="seoistic_ai_custom_base_url" name="custom_base_url" value="<?php echo esc_attr( (string) $s['custom_base_url'] ); ?>" class="regular-text" placeholder="https://api.example.com/v1">
						<p class="description"><?php esc_html_e( 'Example: https://api.example.com/v1. Requests use /chat/completions and are unmetered with your own API key.', 'seoistic' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="seoistic_ai_custom_key"><?php esc_html_e( 'API key', 'seoistic' ); ?></label></th>
						<td><input type="password" id="seoistic_ai_custom_key" name="custom_api_key" class="regular-text" autocomplete="new-password" placeholder="<?php echo AiSettings::has_api_key( 'custom' ) ? esc_attr( AiSettings::masked_key( 'custom' ) ) : 'sk-…'; ?>">
						<?php if ( AiSettings::has_api_key( 'custom' ) ) : ?><p class="description"><label><input type="checkbox" name="clear_custom_key" value="1"> <?php esc_html_e( 'Remove the saved encrypted key', 'seoistic' ); ?></label></p><?php endif; ?></td>
					</tr>
					<tr>
						<th><label for="seoistic_ai_custom_model"><?php esc_html_e( 'Model', 'seoistic' ); ?></label></th>
						<td><input type="text" id="seoistic_ai_custom_model" name="custom_model" value="<?php echo esc_attr( (string) $s['custom_model'] ); ?>" class="regular-text" placeholder="gpt-4o-mini"></td>
					</tr>
				</table>
				<?php endif; ?>

				<table class="form-table">
				<tr>
					<th><label for="seoistic_ai_temperature"><?php esc_html_e( 'Temperature', 'seoistic' ); ?></label></th>
					<td><input type="number" id="seoistic_ai_temperature" step="0.1" min="0" max="2" name="temperature" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_max_tokens"><?php esc_html_e( 'Max Tokens', 'seoistic' ); ?></label></th>
					<td><input type="number" id="seoistic_ai_max_tokens" step="50" min="100" max="4000" name="max_tokens" value="<?php echo esc_attr( (string) $s['max_tokens'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_business_name"><?php esc_html_e( 'Business Name', 'seoistic' ); ?></label></th>
					<td><input type="text" id="seoistic_ai_business_name" name="business_name" value="<?php echo esc_attr( (string) $s['business_name'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_brand_voice"><?php esc_html_e( 'Brand Voice', 'seoistic' ); ?></label></th>
					<td><textarea id="seoistic_ai_brand_voice" name="brand_voice" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'e.g. warm, expert, no jargon', 'seoistic' ); ?>"><?php echo esc_textarea( (string) $s['brand_voice'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_target_country"><?php esc_html_e( 'Target Country', 'seoistic' ); ?></label></th>
					<td><input type="text" id="seoistic_ai_target_country" name="target_country" value="<?php echo esc_attr( (string) $s['target_country'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_target_audience"><?php esc_html_e( 'Target Audience', 'seoistic' ); ?></label></th>
					<td><input type="text" id="seoistic_ai_target_audience" name="target_audience" value="<?php echo esc_attr( (string) $s['target_audience'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_default_language"><?php esc_html_e( 'Default Language', 'seoistic' ); ?></label></th>
					<td><input type="text" id="seoistic_ai_default_language" name="default_language" value="<?php echo esc_attr( (string) $s['default_language'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="seoistic_ai_kb_mode"><?php esc_html_e( 'Knowledge Base Mode', 'seoistic' ); ?></label></th>
					<td>
						<select id="seoistic_ai_kb_mode" name="kb_mode">
							<option value="strict" <?php selected( $s['kb_mode'], 'strict' ); ?>><?php esc_html_e( 'Strict — follow guidance closely', 'seoistic' ); ?></option>
							<option value="balanced" <?php selected( $s['kb_mode'], 'balanced' ); ?>><?php esc_html_e( 'Balanced', 'seoistic' ); ?></option>
							<option value="creative" <?php selected( $s['kb_mode'], 'creative' ); ?>><?php esc_html_e( 'Creative — more variation', 'seoistic' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			</div>
			<?php submit_button( __( 'Save AI settings', 'seoistic' ) ); ?>
		</form>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_ai_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		AiSettings::save(
			array(
				'enabled'          => isset( $_POST['enabled'] ),
				'temperature'      => (float) ( $_POST['temperature'] ?? 0.4 ),
				'max_tokens'       => (int) ( $_POST['max_tokens'] ?? 900 ),
				'business_name'    => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
				'brand_voice'      => sanitize_textarea_field( wp_unslash( $_POST['brand_voice'] ?? '' ) ),
				'target_country'   => sanitize_text_field( wp_unslash( $_POST['target_country'] ?? '' ) ),
				'target_audience'  => sanitize_text_field( wp_unslash( $_POST['target_audience'] ?? '' ) ),
				'default_language' => sanitize_text_field( wp_unslash( $_POST['default_language'] ?? 'en' ) ),
				'kb_mode'          => sanitize_key( wp_unslash( $_POST['kb_mode'] ?? 'balanced' ) ),
				'custom_base_url'  => AiSettings::custom_models_allowed() ? esc_url_raw( wp_unslash( $_POST['custom_base_url'] ?? '' ) ) : '',
				'custom_model'     => AiSettings::custom_models_allowed() ? sanitize_text_field( wp_unslash( $_POST['custom_model'] ?? '' ) ) : '',
			)
		);

		if ( AiSettings::custom_models_allowed() ) {
			if ( ! empty( $_POST['clear_custom_key'] ) ) {
				AiSettings::clear_api_key( 'custom' );
			} elseif ( ! empty( $_POST['custom_api_key'] ) ) {
				AiSettings::set_api_key( 'custom', sanitize_text_field( wp_unslash( $_POST['custom_api_key'] ) ) );
			}
		}

		if ( AiSettings::custom_models_allowed() ) {
			WpisticAiClient::clear_caches();
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=seoistic-settings&tab=ai' ) ) );
		exit;
	}
}
