<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\AiSearch\AeoAuditService;
use Wpistic\Seoistic\AiSearch\AiCrawlerStore;
use Wpistic\Seoistic\Core\LlmsTxt;
use Wpistic\Seoistic\Core\AI\WpisticAiClient;

final class AeoPage {

	private AiCrawlerStore $crawlers;
	private AeoAuditService $audits;

	public function __construct( ?AiCrawlerStore $crawlers = null, ?AeoAuditService $audits = null ) {
		$this->crawlers = $crawlers ?? new AiCrawlerStore();
		$this->audits = $audits ?? new AeoAuditService();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 34 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'seoistic',
			__( 'AI Search Visibility', 'seoistic' ),
			__( 'AI Search', 'seoistic' ),
			'manage_options',
			'seoistic-aeo',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'seoistic-aeo' ) ) {
			return;
		}
		wp_enqueue_script(
			'seoistic-aeo',
			SEOISTIC_URL . 'assets/js/aeo.js',
			array( 'seoistic-admin' ),
			SEOISTIC_VERSION,
			true
		);
		wp_localize_script(
			'seoistic-aeo',
			'SeoisticAeo',
			array(
				'llms' => LlmsTxt::blueprint(),
				'crawler' => $this->crawlers->dashboard(),
				'checklist' => AeoAuditService::default_checklist(),
				'i18n' => array(
					'addSection' => __( 'Add section', 'seoistic' ),
					'addLink' => __( 'Add link', 'seoistic' ),
					'audit' => __( 'Run AEO audit (10 credits)', 'seoistic' ),
					'auditing' => __( 'Auditing…', 'seoistic' ),
					'apply' => __( 'Save and apply llms.txt', 'seoistic' ),
					'applying' => __( 'Applying…', 'seoistic' ),
					'blueprint' => __( 'Save blueprint', 'seoistic' ),
					'saving' => __( 'Saving…', 'seoistic' ),
					'reset' => __( 'Reset builder', 'seoistic' ),
					'confirmReset' => __( 'Reset the visual builder? The currently applied llms.txt will remain live until you apply new content.', 'seoistic' ),
					'removeSection' => __( 'Remove section', 'seoistic' ),
					'removeLink' => __( 'Remove link', 'seoistic' ),
					'saveChecklist' => __( 'Save citation checklist', 'seoistic' ),
					'savingChecklist' => __( 'Saving checklist…', 'seoistic' ),
					'emptyUrl' => __( 'Add a link URL.', 'seoistic' ),
					'emptyPost' => __( 'Choose published content to audit.', 'seoistic' ),
					'preview' => __( 'Live llms.txt preview', 'seoistic' ),
					'crawlerNote' => __( 'Direct UA detection and same-origin beacons only. Cache services can delay or aggregate crawler traffic.', 'seoistic' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access AI Search Visibility.', 'seoistic' ) );
		}

		$stats = $this->crawlers->dashboard();
		$recent = get_posts(
			array(
				'post_status' => 'publish',
				'numberposts' => 20,
				'post_type' => $this->public_post_types(),
				'orderby' => 'modified',
				'order' => 'DESC',
				'no_found_rows' => true,
			)
		);

		View::header( 'seoistic-aeo', __( 'AI Search Visibility', 'seoistic' ), __( 'Make your content easy for AI engines to understand, crawl, quote, and verify.', 'seoistic' ) );
		$this->render_overview( $stats );
		$this->render_llms_studio();
		$this->render_crawler_analytics( $stats );
		$this->render_content_audit( $recent );
		$this->render_citation_checklist( $recent );
		View::footer();
	}

	private function public_post_types(): array {
		$types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	private function render_overview( array $stats ): void {
		echo '<div class="seoistic-cards">';
		View::card( 'superhero', (string) $stats['total'], __( 'AI crawler visits (30 days)', 'seoistic' ), $stats['total'] > 0 ? 'good' : '' );
		View::card( 'editor-ultra', (string) count( $stats['bots'] ), __( 'Active AI crawlers', 'seoistic' ), '', __( 'Directly detected UA', 'seoistic' ) );
		View::card( 'awards', (string) WpisticAiClient::CREDIT_COSTS['aeo_audit'], __( 'Credits per AEO audit', 'seoistic' ), '', __( 'Gateway metered', 'seoistic' ) );
		echo '</div>';
	}

	private function render_llms_studio(): void {
		$live = get_option( LlmsTxt::OPTION_CONTENT, '' );
		echo '<div class="seoistic-section-title">' . esc_html__( 'llms.txt studio', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-aeo-split">';
		echo '<div class="seoistic-tool-card seoistic-aeo-builder" data-seoistic-llms-builder>';
		echo '<div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-editor-kitchensink"></span></div><strong>' . esc_html__( 'Visual builder', 'seoistic' ) . '</strong></div>';
		echo '<p class="description">' . esc_html__( 'Describe each topic area, add authoritative links, and generate a clean llms.txt outline. Applying writes the final content to the existing llms.txt endpoint.', 'seoistic' ) . '</p>';
		echo '<div data-seoistic-llms-form></div>';
		echo '<div class="seoistic-hero-actions">';
		echo '<button type="button" class="seoistic-btn" data-seoistic-llms-save>' . esc_html__( 'Save blueprint', 'seoistic' ) . '</button>';
		echo '<button type="button" class="seoistic-btn" data-seoistic-llms-reset>' . esc_html__( 'Reset builder', 'seoistic' ) . '</button>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-llms-apply>' . esc_html__( 'Save and apply llms.txt', 'seoistic' ) . '</button>';
		echo '</div><div class="seoistic-tool-result" data-seoistic-llms-result hidden></div></div>';
		echo '<div class="seoistic-panel"><h3>' . esc_html__( 'Live preview', 'seoistic' ) . '</h3><pre class="seoistic-llms-preview" data-seoistic-llms-preview></pre>';
		echo '<p class="description">' . esc_html__( 'Status:', 'seoistic' ) . ' <strong>' . esc_html( '' === trim( (string) $live ) ? __( 'Default generated file', 'seoistic' ) : __( 'Custom applied file', 'seoistic' ) ) . '</strong></p></div>';
		echo '</div>';
	}

	private function render_crawler_analytics( array $stats ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'AI crawler analytics', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-tool-card"><div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-chart-line"></span></div><strong>' . esc_html__( 'Visits trend', 'seoistic' ) . '</strong></div>';
		echo '<div class="seoistic-aeo-chart" data-seoistic-crawler-chart role="img" aria-label="' . esc_attr__( 'Daily AI crawler visits for the last 30 days', 'seoistic' ) . '"></div>';
		echo '<table class="widefat striped seoistic-aeo-table"><thead><tr><th>' . esc_html__( 'Most-crawled content', 'seoistic' ) . '</th><th>' . esc_html__( 'Visits', 'seoistic' ) . '</th><th>' . esc_html__( 'Last visit', 'seoistic' ) . '</th></tr></thead><tbody data-seoistic-crawler-urls>';
		if ( array() === $stats['urls'] ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No AI crawler visits recorded yet.', 'seoistic' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and CCBot are detected from direct requests. The optional beacon records verified crawler requests that bypass PHP because of page caching.', 'seoistic' ) . '</p></div>';
	}

	private function render_content_audit( array $recent ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'AEO content audit', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-tool-card" data-seoistic-audit-card><div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-visibility"></span></div><strong>' . esc_html__( 'AI-scored answer readiness', 'seoistic' ) . '</strong></div>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-aeo-post">' . esc_html__( 'Content', 'seoistic' ) . '</label><select id="seoistic-aeo-post" data-seoistic-audit-post>';
		foreach ( $recent as $post ) {
			$score = get_post_meta( (int) $post->ID, AeoAuditService::META_SCORE, true );
			$label = get_the_title( $post ) . ( '' !== (string) $score ? ' — ' . sprintf( __( 'AEO %d/100', 'seoistic' ), (int) $score ) : '' );
			echo '<option value="' . esc_attr( (string) $post->ID ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '<p class="description">' . esc_html__( 'Each audit evaluates answer-first structure, FAQ presence, entity coverage, heading clarity, and freshness. It costs 10 AI credits.', 'seoistic' ) . '</p>';
		echo '<div class="seoistic-hero-actions"><button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-audit-run>' . esc_html__( 'Run AEO audit (10 credits)', 'seoistic' ) . '</button></div>';
		echo '<div class="seoistic-tool-progress"><div class="seoistic-tool-progress-bar"></div></div><div class="seoistic-tool-result" data-seoistic-audit-result hidden></div></div>';
	}

	private function render_citation_checklist( array $recent ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Citation checklist', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-tool-card" data-seoistic-checklist-card><div class="seoistic-tool-head"><div class="seoistic-card-icon"><span class="dashicons dashicons-list-view"></span></div><strong>' . esc_html__( 'Guided manual review', 'seoistic' ) . '</strong></div>';
		echo '<p class="description">' . esc_html__( 'Complete each step while reviewing the page and an actual AI answer. SEOistic records your decisions but never claims automatic citation discovery.', 'seoistic' ) . '</p>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label" for="seoistic-aeo-checklist-post">' . esc_html__( 'Content', 'seoistic' ) . '</label><select id="seoistic-aeo-checklist-post" data-seoistic-checklist-post>';
		foreach ( $recent as $post ) {
			echo '<option value="' . esc_attr( (string) $post->ID ) . '">' . esc_html( get_the_title( $post ) ) . '</option>';
		}
		echo '</select></div><div class="seoistic-aeo-checklist" data-seoistic-checklist-list></div>';
		echo '<div class="seoistic-hero-actions"><button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-checklist-save>' . esc_html__( 'Save citation checklist', 'seoistic' ) . '</button></div>';
		echo '<div class="seoistic-tool-result" data-seoistic-checklist-result hidden></div></div>';
	}
}
