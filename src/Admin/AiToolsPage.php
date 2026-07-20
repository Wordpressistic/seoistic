<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\AI\AiSettings;
use Wpistic\Seoistic\Core\Sitemaps;
use Wpistic\Seoistic\Module\Entitlement;

/**
 * SEOISTIC → AI Tools. Animated generator cards for robots.txt, .htaccess, llms.txt,
 * schema, bulk meta, image alt text, internal links, AI search visibility, and the
 * SEO audit report — plus the sitemap exclusion settings. Every generator previews
 * its result before anything is written; file-writing actions (.htaccess) require
 * an extra confirmation and always back up first.
 */
final class AiToolsPage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_seoistic_save_sitemap_settings', array( $this, 'save_sitemap_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'AI Tools', 'seoistic' ), __( 'AI Tools', 'seoistic' ), 'manage_options', 'seoistic-ai-tools', array( $this, 'render' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( (string) $hook, 'seoistic-ai-tools' ) ) {
			return;
		}
		wp_enqueue_script( 'seoistic-ai-tools', SEOISTIC_URL . 'assets/js/ai-tools.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
	}

	private function ai_ready(): bool {
		return Entitlement::can( 'ai', 'premium' ) && AiSettings::is_enabled() && AiSettings::is_configured();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::header( 'seoistic-ai-tools', __( 'AI Tools', 'seoistic' ), __( 'Generate robots.txt, .htaccess rules, llms.txt, schema, bulk meta, alt text, internal-link and AI-visibility reports. Every result is previewed before anything is applied.', 'seoistic' ) );

		if ( ! $this->ai_ready() ) {
			$reason = ! Entitlement::can( 'ai', 'premium' )
				? __( 'SEOISTIC AI is a Pro feature.', 'seoistic' )
				: __( 'Add an OpenRouter API key and enable AI features.', 'seoistic' );
			echo '<div class="seoistic-modal-warning">' . esc_html( $reason ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=seoistic-settings&tab=ai' ) ) . '">' . esc_html__( 'Open AI settings', 'seoistic' ) . '</a></div>';
		}

		echo '<div class="seoistic-tool-grid">';
		$this->generator_card( 'networking', __( 'Robots.txt Generator', 'seoistic' ), __( 'AI-optimized robots.txt with your sitemap reference. Preview before applying.', 'seoistic' ), 'robots' );
		$this->generator_card( 'shield', __( '.htaccess SEO Generator', 'seoistic' ), __( 'HTTPS/www canonicalization, caching, and security rules. Backs up your file before writing.', 'seoistic' ), 'htaccess' );
		$this->generator_card( 'media-text', __( 'llms.txt Generator', 'seoistic' ), __( 'A structured summary of your site for AI assistants (ChatGPT, Perplexity, Gemini).', 'seoistic' ), 'llms' );
		$this->sitemap_card();
		$this->schema_info_card();
		$this->bulk_card( 'index-card', __( 'Meta Bulk Generator', 'seoistic' ), __( 'Fills in SEO title, description and focus keyword for every published post missing them.', 'seoistic' ), 'bulk-meta' );
		$this->bulk_card( 'format-image', __( 'Image Alt Generator', 'seoistic' ), __( 'Generates alt text for media library images that don\'t have any yet.', 'seoistic' ), 'bulk-alt' );
		$this->bulk_card( 'admin-links', __( 'Internal Link Builder', 'seoistic' ), __( 'Advisory report: internal-linking opportunities for your lowest-scoring pages.', 'seoistic' ), 'bulk-internal-links' );
		$this->bulk_card( 'visibility', __( 'AI Search Visibility Analyzer', 'seoistic' ), __( 'Advisory report: AEO recommendations for your lowest-scoring pages.', 'seoistic' ), 'bulk-aeo' );
		$this->audit_report_card();
		echo '</div>';

		$this->modal();
		View::footer();
	}

	private function generator_card( string $icon, string $title, string $desc, string $type ): void {
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></div><strong>' . esc_html( $title ) . '</strong></div>';
		echo '<p>' . esc_html( $desc ) . '</p>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-generate="' . esc_attr( $type ) . '" ' . disabled( $this->ai_ready(), false, false ) . '><span class="dashicons dashicons-superhero"></span> ' . esc_html__( 'Generate', 'seoistic' ) . '</button>';
		echo '</div>';
	}

	private function bulk_card( string $icon, string $title, string $desc, string $tool ): void {
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></div><strong>' . esc_html( $title ) . '</strong></div>';
		echo '<p>' . esc_html( $desc ) . '</p>';
		echo '<div class="seoistic-tool-progress"><div class="seoistic-tool-progress-bar"></div></div>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-bulk="' . esc_attr( $tool ) . '" ' . disabled( $this->ai_ready(), false, false ) . '><span class="dashicons dashicons-superhero"></span> ' . esc_html__( 'Run', 'seoistic' ) . '</button>';
		echo '<div class="seoistic-tool-result"></div>';
		echo '</div>';
	}

	private function schema_info_card(): void {
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-cart"></span></div><strong>' . esc_html__( 'Schema Generator', 'seoistic' ) . '</strong></div>';
		echo '<p>' . esc_html__( 'Schema type and FAQ suggestions are generated per page — open any post and use the Schema tab in the SEOISTIC panel.', 'seoistic' ) . '</p>';
		echo '<a class="seoistic-btn" href="' . esc_url( admin_url( 'edit.php' ) ) . '"><span class="dashicons dashicons-arrow-right-alt"></span> ' . esc_html__( 'Go to Posts', 'seoistic' ) . '</a>';
		echo '</div>';
	}

	private function audit_report_card(): void {
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-analytics"></span></div><strong>' . esc_html__( 'SEO Audit Report Generator', 'seoistic' ) . '</strong></div>';
		echo '<p>' . esc_html__( 'Your 20 lowest-scoring published pages, from cached audit scores — no AI required.', 'seoistic' ) . '</p>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-audit-report><span class="dashicons dashicons-chart-bar"></span> ' . esc_html__( 'Generate report', 'seoistic' ) . '</button>';
		echo '</div>';
	}

	private function sitemap_card(): void {
		$settings = Sitemaps::settings();
		echo '<div class="seoistic-tool-card">';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-networking"></span></div><strong>' . esc_html__( 'XML Sitemap Settings', 'seoistic' ) . '</strong></div>';
		echo '<p>' . esc_html__( 'Exclude post types or specific IDs from the sitemap, and ping search engines after big changes.', 'seoistic' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seoistic_save_sitemap_settings">';
		wp_nonce_field( 'seoistic_sitemap_settings' );
		echo '<div class="seoistic-field"><label class="seoistic-field-label">' . esc_html__( 'Exclude post types', 'seoistic' ) . '</label>';
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			echo '<label style="display:block;font-size:12.5px;margin-bottom:4px;"><input type="checkbox" name="excluded_post_types[]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, $settings['excluded_post_types'], true ), true, false ) . '> ' . esc_html( $post_type->labels->name ) . '</label>';
		}
		echo '</div>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic_excluded_ids">' . esc_html__( 'Exclude post IDs (comma-separated)', 'seoistic' ) . '</label>';
		echo '<input type="text" id="seoistic_excluded_ids" name="excluded_ids" value="' . esc_attr( implode( ', ', $settings['excluded_ids'] ) ) . '"></div>';
		echo '<button type="submit" class="seoistic-btn seoistic-btn-sm">' . esc_html__( 'Save', 'seoistic' ) . '</button>';
		echo '</form>';
		echo '<button type="button" class="seoistic-btn" style="margin-top:8px;" data-seoistic-ping-sitemap><span class="dashicons dashicons-megaphone"></span> ' . esc_html__( 'Ping search engines', 'seoistic' ) . '</button>';
		echo '<div class="seoistic-tool-result"></div>';
		echo '</div>';
	}

	private function modal(): void {
		?>
		<div class="seoistic-modal-overlay" id="seoistic-tools-modal">
			<div class="seoistic-modal">
				<div class="seoistic-modal-head"><h2 id="seoistic-tools-modal-title"><?php esc_html_e( 'Generated result', 'seoistic' ); ?></h2><button type="button" class="seoistic-modal-close" data-seoistic-modal-close>&times;</button></div>
				<div class="seoistic-modal-body">
					<div class="seoistic-modal-warning" id="seoistic-tools-modal-warning" style="display:none;"></div>
					<p id="seoistic-tools-modal-reason" style="color:var(--seoistic-muted);font-size:13px;"></p>
					<textarea class="seoistic-editor" id="seoistic-tools-modal-content"></textarea>
				</div>
				<div class="seoistic-modal-foot">
					<button type="button" class="seoistic-btn" data-seoistic-copy="#seoistic-tools-modal-content"><?php esc_html_e( 'Copy', 'seoistic' ); ?></button>
					<button type="button" class="seoistic-btn" id="seoistic-tools-modal-download"><?php esc_html_e( 'Download', 'seoistic' ); ?></button>
					<button type="button" class="seoistic-btn seoistic-btn-primary" id="seoistic-tools-modal-apply"><?php esc_html_e( 'Apply', 'seoistic' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_sitemap_settings(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_sitemap_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		$excluded_types = isset( $_POST['excluded_post_types'] ) && is_array( $_POST['excluded_post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['excluded_post_types'] ) )
			: array();
		$excluded_ids_raw = sanitize_text_field( wp_unslash( $_POST['excluded_ids'] ?? '' ) );
		$excluded_ids     = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $excluded_ids_raw ) ) ) );

		Sitemaps::save_settings( $excluded_types, array_values( $excluded_ids ) );

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=seoistic-ai-tools' ) ) );
		exit;
	}
}
