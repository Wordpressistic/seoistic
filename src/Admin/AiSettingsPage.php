<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\AI\AiSettings;

/**
 * SEOISTIC → Settings → AI. Renders as a tab inside Admin::settings(). Every
 * API key field is a blank password input — submitting it blank keeps the
 * existing (encrypted) key; the saved key is never echoed back into the page.
 * Provider-specific fields (OpenRouter/Groq keys, Ollama base URL) are all
 * rendered together and toggled client-side by the provider dropdown.
 */
final class AiSettingsPage {

	public function register(): void {
		add_action( 'admin_post_seoistic_save_ai_settings', array( $this, 'save' ) );
	}

	public function render_fields(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s        = AiSettings::all();
		$provider = AiSettings::provider();
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
					<th><label for="seoistic_ai_provider"><?php esc_html_e( 'AI Provider', 'seoistic' ); ?></label></th>
					<td>
						<select id="seoistic_ai_provider" name="provider" data-seoistic-provider-select>
							<?php foreach ( AiSettings::PROVIDERS as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $provider, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'OpenRouter and Groq both offer free-tier models and need an API key. Ollama is a self-hosted model server on your own network/VPS — free, private, and no key required.', 'seoistic' ); ?></p>
					</td>
				</tr>
			</table>

			<div data-seoistic-provider-panel="openrouter">
				<table class="form-table">
					<tr>
						<th><label for="seoistic_ai_openrouter_key"><?php esc_html_e( 'OpenRouter API Key', 'seoistic' ); ?></label></th>
						<td>
							<input type="password" id="seoistic_ai_openrouter_key" name="openrouter_api_key" class="regular-text" autocomplete="off" placeholder="<?php echo AiSettings::has_api_key( 'openrouter' ) ? esc_attr( AiSettings::masked_key( 'openrouter' ) ) : 'sk-or-…'; ?>">
							<?php if ( AiSettings::has_api_key( 'openrouter' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'A key is already saved and encrypted. Leave blank to keep it, or enter a new one to replace it.', 'seoistic' ); ?>
									<label style="margin-left:8px;"><input type="checkbox" name="clear_openrouter_key" value="1"> <?php esc_html_e( 'Remove saved key', 'seoistic' ); ?></label>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Get a free key at openrouter.ai — many models have a free tier. Stored encrypted; never sent to the browser.', 'seoistic' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="seoistic_ai_model_openrouter"><?php esc_html_e( 'OpenRouter Model', 'seoistic' ); ?></label></th>
						<td>
							<select id="seoistic_ai_model_openrouter" name="model_openrouter">
								<?php foreach ( AiSettings::OPENROUTER_MODELS as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['model_openrouter'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<div data-seoistic-provider-panel="groq">
				<table class="form-table">
					<tr>
						<th><label for="seoistic_ai_groq_key"><?php esc_html_e( 'Groq API Key', 'seoistic' ); ?></label></th>
						<td>
							<input type="password" id="seoistic_ai_groq_key" name="groq_api_key" class="regular-text" autocomplete="off" placeholder="<?php echo AiSettings::has_api_key( 'groq' ) ? esc_attr( AiSettings::masked_key( 'groq' ) ) : 'gsk_…'; ?>">
							<?php if ( AiSettings::has_api_key( 'groq' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'A key is already saved and encrypted. Leave blank to keep it, or enter a new one to replace it.', 'seoistic' ); ?>
									<label style="margin-left:8px;"><input type="checkbox" name="clear_groq_key" value="1"> <?php esc_html_e( 'Remove saved key', 'seoistic' ); ?></label>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Get a free key at console.groq.com — fast inference, generous free rate limits. Stored encrypted; never sent to the browser.', 'seoistic' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="seoistic_ai_model_groq"><?php esc_html_e( 'Groq Model', 'seoistic' ); ?></label></th>
						<td>
							<select id="seoistic_ai_model_groq" name="model_groq">
								<?php foreach ( AiSettings::GROQ_MODELS as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['model_groq'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<div data-seoistic-provider-panel="ollama">
				<table class="form-table">
					<tr>
						<th><label for="seoistic_ai_ollama_base_url"><?php esc_html_e( 'Ollama Base URL', 'seoistic' ); ?></label></th>
						<td>
							<input type="url" id="seoistic_ai_ollama_base_url" name="ollama_base_url" value="<?php echo esc_attr( (string) $s['ollama_base_url'] ); ?>" class="regular-text" placeholder="http://127.0.0.1:11434">
							<p class="description"><?php esc_html_e( 'The address of your Ollama server — e.g. http://127.0.0.1:11434 if it runs on this same server, or your VPS\'s address/port if it runs elsewhere on a private network. No API key needed.', 'seoistic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="seoistic_ai_ollama_model"><?php esc_html_e( 'Ollama Model', 'seoistic' ); ?></label></th>
						<td>
							<input type="text" id="seoistic_ai_ollama_model" name="ollama_model" value="<?php echo esc_attr( (string) $s['ollama_model'] ); ?>" class="regular-text" placeholder="llama3.1">
							<p class="description"><?php esc_html_e( 'The exact model name as it appears in `ollama list` on your server (e.g. llama3.1, qwen2.5, mistral).', 'seoistic' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

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
		<script>
		( function () {
			var select = document.querySelector( '[data-seoistic-provider-select]' );
			if ( ! select ) { return; }
			function sync() {
				document.querySelectorAll( '[data-seoistic-provider-panel]' ).forEach( function ( panel ) {
					panel.style.display = panel.getAttribute( 'data-seoistic-provider-panel' ) === select.value ? '' : 'none';
				} );
			}
			select.addEventListener( 'change', sync );
			sync();
		} )();
		</script>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_ai_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		AiSettings::save(
			array(
				'enabled'          => isset( $_POST['enabled'] ),
				'provider'         => sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ),
				'model_openrouter' => sanitize_text_field( wp_unslash( $_POST['model_openrouter'] ?? '' ) ),
				'model_groq'       => sanitize_text_field( wp_unslash( $_POST['model_groq'] ?? '' ) ),
				'ollama_base_url'  => esc_url_raw( wp_unslash( $_POST['ollama_base_url'] ?? '' ) ),
				'ollama_model'     => sanitize_text_field( wp_unslash( $_POST['ollama_model'] ?? '' ) ),
				'temperature'      => (float) ( $_POST['temperature'] ?? 0.4 ),
				'max_tokens'       => (int) ( $_POST['max_tokens'] ?? 900 ),
				'business_name'    => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
				'brand_voice'      => sanitize_textarea_field( wp_unslash( $_POST['brand_voice'] ?? '' ) ),
				'target_country'   => sanitize_text_field( wp_unslash( $_POST['target_country'] ?? '' ) ),
				'target_audience'  => sanitize_text_field( wp_unslash( $_POST['target_audience'] ?? '' ) ),
				'default_language' => sanitize_text_field( wp_unslash( $_POST['default_language'] ?? 'en' ) ),
				'kb_mode'          => sanitize_key( wp_unslash( $_POST['kb_mode'] ?? 'balanced' ) ),
			)
		);

		foreach ( array( 'openrouter', 'groq' ) as $provider ) {
			if ( ! empty( $_POST[ 'clear_' . $provider . '_key' ] ) ) {
				AiSettings::clear_api_key( $provider );
			} elseif ( ! empty( $_POST[ $provider . '_api_key' ] ) ) {
				AiSettings::set_api_key( $provider, sanitize_text_field( wp_unslash( $_POST[ $provider . '_api_key' ] ) ) );
			}
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=seoistic-settings&tab=ai' ) ) );
		exit;
	}
}
