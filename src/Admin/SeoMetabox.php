<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\AI\AiSettings;
use Wpistic\Seoistic\Core\PostSeo;
use Wpistic\Seoistic\Core\SchemaValidator;
use Wpistic\Seoistic\Core\Scorer;
use WP_Post;

/**
 * The RankMath-style tabbed SEO panel on the post edit screen: General, Social,
 * Schema, Audit, AI. Replaces the old single-field Meta::render_box() metabox —
 * Core\Meta still owns front-end <head> output, this owns the editor UI + save.
 */
final class SeoMetabox {

	private const SCHEMA_TYPES = array(
		''             => 'Auto (Article / WebPage)',
		'Article'      => 'Article',
		'BlogPosting'  => 'Blog Posting',
		'Product'      => 'Product',
		'FAQPage'      => 'FAQ Page',
		'Event'        => 'Event',
		'LocalBusiness' => 'Local Business',
		'Recipe'       => 'Recipe',
		'Review'       => 'Review',
		'VideoObject'  => 'Video',
		'none'         => 'None',
	);

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function add_box(): void {
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			add_meta_box( 'seoistic_seo_panel', __( 'SEOISTIC — SEO', 'seoistic' ), array( $this, 'render' ), $post_type, 'normal', 'high' );
			add_meta_box( 'seoistic_score_side', __( 'SEOISTIC — SEO Score', 'seoistic' ), array( $this, 'render_score_side' ), $post_type, 'side', 'high' );
		}
	}

	/**
	 * A quick-glance sidebar companion to the full panel below: the score ring,
	 * a few status badges, and the top unresolved issues — each "Fix" link jumps
	 * straight to the full checklist in the Audit tab.
	 */
	public function render_score_side( WP_Post $post ): void {
		$id     = (int) $post->ID;
		$score  = PostSeo::score( $id );
		$result = $score < 0 ? Scorer::score( $post ) : array( 'score' => $score, 'checks' => PostSeo::audit_report( $id ) );

		$checks  = (array) $result['checks'];
		$issues  = array_values( array_filter( $checks, static fn( array $c ): bool => empty( $c['pass'] ) ) );
		$keyword = PostSeo::focus_keyword( $id );
		$noindex = PostSeo::is_noindex( $id );

		echo '<div class="seoistic-sidebar-score">';
		echo View::score_ring( (int) $result['score'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="seoistic-sidebar-score-label">' . esc_html__( 'SEO score', 'seoistic' ) . '</p>';

		echo '<div class="seoistic-sidebar-badges">';
		echo View::badge( $noindex ? __( 'Noindex', 'seoistic' ) : __( 'Indexable', 'seoistic' ), $noindex ? 'noindex' : 'indexable' );
		echo View::badge( '' !== $keyword ? $keyword : __( 'No keyword', 'seoistic' ), '' !== $keyword ? 'good' : 'warn' );
		echo '</div>';

		if ( array() !== $issues ) {
			echo '<div class="seoistic-sidebar-issues">';
			foreach ( array_slice( $issues, 0, 3 ) as $issue ) {
				echo '<div class="seoistic-sidebar-issue"><span class="dashicons dashicons-warning"></span> ' . esc_html( (string) ( $issue['label'] ?? '' ) ) . '</div>';
			}
			if ( count( $issues ) > 3 ) {
				echo '<div class="seoistic-sidebar-issue">' . esc_html(
					sprintf(
						/* translators: %d: number of additional issues. */
						__( '+%d more in the Audit tab', 'seoistic' ),
						count( $issues ) - 3
					)
				) . '</div>';
			}
			echo '</div>';
		}

		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" data-seoistic-open-audit-tab><span class="dashicons dashicons-chart-bar"></span> ' . esc_html__( 'View full report', 'seoistic' ) . '</button>';
		echo '</div>';
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( 'seoistic_seo_panel', 'seoistic_seo_panel_nonce' );

		$id       = (int) $post->ID;
		$title    = PostSeo::title( $id );
		$desc     = PostSeo::description( $id );
		$keyword  = PostSeo::focus_keyword( $id );
		$canonical = PostSeo::canonical( $id );
		$noindex  = PostSeo::is_noindex( $id );
		$nofollow = PostSeo::is_nofollow( $id );
		$og_title = PostSeo::og_title( $id );
		$og_desc  = PostSeo::og_description( $id );
		$og_image = PostSeo::og_image( $id );
		$schema   = PostSeo::schema_type( $id );
		$crumb    = PostSeo::breadcrumb_title( $id );
		$permalink = get_permalink( $post ) ?: home_url( '/' );
		$ai_ready = AiSettings::is_enabled() && AiSettings::is_configured();

		$score  = PostSeo::score( $id );
		$result = $score < 0 ? Scorer::score( $post ) : array( 'score' => $score, 'checks' => PostSeo::audit_report( $id ) );

		echo '<div class="seoistic-panel" id="seoistic-panel" data-post-id="' . esc_attr( (string) $id ) . '" data-ai-ready="' . ( $ai_ready ? '1' : '0' ) . '">';

		/* Workspace header: live score + analysis status. */
		echo '<div class="seoistic-workspace-head">';
		echo '<div class="seoistic-workspace-score" id="seoistic-workspace-score">';
		echo View::score_ring( (int) $result['score'], 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="seoistic-workspace-score-text">';
		echo '<span class="seoistic-workspace-score-label">' . esc_html__( 'SEO score', 'seoistic' ) . '</span>';
		echo '<span class="seoistic-workspace-score-band" id="seoistic-score-band">' . esc_html( self::band_label( (int) $result['score'] ) ) . '</span>';
		echo '</div></div>';
		echo '<div class="seoistic-live-status" id="seoistic-live-status" role="status"><span class="seoistic-live-dot" aria-hidden="true"></span><span class="seoistic-live-text">' . esc_html__( 'Live analysis', 'seoistic' ) . '</span></div>';
		echo '</div>';

		echo '<div class="seoistic-panel-tabs" role="tablist">';
		foreach ( $this->tabs() as $key => $label ) {
			echo '<button type="button" class="seoistic-panel-tab' . ( 'general' === $key ? ' is-active' : '' ) . '" data-seoistic-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';
		echo '<div class="seoistic-panel-body">';

		$this->pane_general( $title, $desc, $keyword, $canonical, $noindex, $nofollow, $post, $permalink, (array) $result['checks'] );
		$this->pane_social( $og_title, $og_desc, $og_image, $title, $desc );
		$this->pane_schema( $post, $schema, $crumb );
		$this->pane_audit( $post );
		$this->pane_ai( $ai_ready );

		echo '</div></div>';
	}

	/**
	 * @return array<string,string>
	 */
	private function tabs(): array {
		return array(
			'general' => __( 'General', 'seoistic' ),
			'social'  => __( 'Social', 'seoistic' ),
			'schema'  => __( 'Schema', 'seoistic' ),
			'audit'   => __( 'Audit', 'seoistic' ),
			'ai'      => __( 'AI', 'seoistic' ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $checks
	 */
	private function pane_general( string $title, string $desc, string $keyword, string $canonical, bool $noindex, bool $nofollow, WP_Post $post, string $permalink, array $checks = array() ): void {
		echo '<div class="seoistic-panel-pane is-active" data-seoistic-pane="general">';
		echo '<div class="seoistic-workspace-cols">';

		/* Left column: search appearance + fields. */
		echo '<div class="seoistic-workspace-fields">';
		echo '<div class="seoistic-preview-card">';
		echo '<div class="seoistic-preview-toggle" role="group" aria-label="' . esc_attr__( 'Preview device', 'seoistic' ) . '"><button type="button" class="is-active" data-seoistic-preview-device="desktop">' . esc_html__( 'Desktop', 'seoistic' ) . '</button><button type="button" data-seoistic-preview-device="mobile">' . esc_html__( 'Mobile', 'seoistic' ) . '</button></div>';
		echo '<div class="seoistic-google-preview" id="seoistic-google-preview">';
		echo '<div class="seoistic-google-url">' . esc_html( $permalink ) . '</div>';
		echo '<div class="seoistic-google-title" id="seoistic-preview-title">' . esc_html( '' !== $title ? $title : $post->post_title ) . '</div>';
		echo '<div class="seoistic-google-desc" id="seoistic-preview-desc">' . esc_html( '' !== $desc ? $desc : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' ) ) . '</div>';
		echo '</div></div>';

		$this->field_text( 'seoistic_title', __( 'SEO title', 'seoistic' ), $title, array(
			'min' => 10, 'max' => 60, 'placeholder' => $post->post_title,
			'ai'  => array( 'action' => 'generate_title', 'label' => __( 'Improve', 'seoistic' ) ),
		) );

		$this->field_textarea( 'seoistic_description', __( 'Meta description', 'seoistic' ), $desc, array(
			'min' => 140, 'max' => 160, 'rows' => 3,
			'ai'  => array( 'action' => 'generate_description', 'label' => __( 'Improve', 'seoistic' ) ),
		) );

		$this->field_text( 'seoistic_focus_keyword', __( 'Focus keyword', 'seoistic' ), $keyword, array(
			'ai' => array( 'action' => 'generate_keywords', 'label' => __( 'Suggest', 'seoistic' ) ),
		) );

		$this->field_text( 'seoistic_canonical', __( 'Canonical URL', 'seoistic' ), $canonical, array( 'type' => 'url', 'placeholder' => $permalink ) );

		echo '<div class="seoistic-field seoistic-flex">';
		echo '<label><input type="checkbox" name="seoistic_noindex" value="1" ' . checked( $noindex, true, false ) . '> ' . esc_html__( 'Noindex — hide from search engines', 'seoistic' ) . '</label>';
		echo '<label style="margin-left:20px;"><input type="checkbox" name="seoistic_nofollow" value="1" ' . checked( $nofollow, true, false ) . '> ' . esc_html__( 'Nofollow — do not pass link equity', 'seoistic' ) . '</label>';
		echo '</div>';
		echo '</div>';

		/* Right column: live priority fixes + passed checks. */
		$failed = array_values( array_filter( $checks, static fn( array $c ): bool => empty( $c['pass'] ) ) );
		$passed = array_values( array_filter( $checks, static fn( array $c ): bool => ! empty( $c['pass'] ) ) );

		echo '<div class="seoistic-workspace-side">';
		echo '<div class="seoistic-checks-card">';
		echo '<h4 class="seoistic-checks-title"><span class="dashicons dashicons-flag" aria-hidden="true"></span> ' . esc_html__( 'Priority fixes', 'seoistic' ) . '</h4>';
		echo '<ul class="seoistic-checks-list" id="seoistic-priority-fixes">';
		if ( array() === $failed ) {
			echo '<li class="seoistic-checks-empty">' . esc_html__( 'Nothing to fix — all checks pass.', 'seoistic' ) . '</li>';
		}
		foreach ( $failed as $check ) {
			echo '<li class="seoistic-check-item is-fail"><span class="dashicons dashicons-warning" aria-hidden="true"></span><span>' . esc_html( (string) ( $check['label'] ?? '' ) ) . '<span class="seoistic-check-msg">' . esc_html( (string) ( $check['message'] ?? '' ) ) . '</span></span></li>';
		}
		echo '</ul></div>';

		echo '<div class="seoistic-checks-card">';
		echo '<h4 class="seoistic-checks-title"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Passed checks', 'seoistic' ) . '</h4>';
		echo '<ul class="seoistic-checks-list" id="seoistic-passed-checks">';
		if ( array() === $passed ) {
			echo '<li class="seoistic-checks-empty">' . esc_html__( 'No checks passing yet.', 'seoistic' ) . '</li>';
		}
		foreach ( $passed as $check ) {
			echo '<li class="seoistic-check-item is-pass"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span>' . esc_html( (string) ( $check['label'] ?? '' ) ) . '</span></li>';
		}
		echo '</ul></div>';
		echo '</div>';

		echo '</div></div>';
	}

	/**
	 * Human label for a score band (mirrors View::score_tone thresholds).
	 */
	public static function band_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'Excellent', 'seoistic' );
		}
		if ( $score >= 80 ) {
			return __( 'Good', 'seoistic' );
		}
		if ( $score >= 50 ) {
			return __( 'Needs work', 'seoistic' );
		}
		return __( 'Critical', 'seoistic' );
	}

	private function pane_social( string $og_title, string $og_desc, string $og_image, string $title, string $desc ): void {
		echo '<div class="seoistic-panel-pane" data-seoistic-pane="social">';

		echo '<div class="seoistic-preview-card"><div class="seoistic-social-preview">';
		echo '<div class="seoistic-social-image" id="seoistic-social-image"' . ( '' !== $og_image ? ' style="background-image:url(' . esc_url( $og_image ) . ')"' : '' ) . '>' . ( '' === $og_image ? esc_html__( 'No share image set', 'seoistic' ) : '' ) . '</div>';
		echo '<div class="seoistic-social-body"><div class="seoistic-social-domain">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</div>';
		echo '<div class="seoistic-social-title" id="seoistic-social-title-preview">' . esc_html( '' !== $og_title ? $og_title : $title ) . '</div>';
		echo '<div class="seoistic-social-desc" id="seoistic-social-desc-preview">' . esc_html( '' !== $og_desc ? $og_desc : $desc ) . '</div>';
		echo '</div></div></div>';

		$this->field_text( 'seoistic_og_title', __( 'OpenGraph title', 'seoistic' ), $og_title, array( 'min' => 40, 'max' => 60 ) );
		$this->field_textarea( 'seoistic_og_description', __( 'OpenGraph description', 'seoistic' ), $og_desc, array( 'min' => 120, 'max' => 160, 'rows' => 2 ) );
		$this->field_text( 'seoistic_og_image', __( 'Share image URL', 'seoistic' ), $og_image, array(
			'type' => 'url',
			'ai'   => array( 'action' => 'generate_alt', 'label' => __( 'Get AI Alt Text', 'seoistic' ) ),
		) );

		echo '</div>';
	}

	private function pane_schema( WP_Post $post, string $schema, string $crumb ): void {
		echo '<div class="seoistic-panel-pane" data-seoistic-pane="schema">';
		echo '<div class="seoistic-field">';
		echo '<div class="seoistic-field-head"><label class="seoistic-field-label" for="seoistic_schema_type">' . esc_html__( 'Schema type', 'seoistic' ) . '</label>';
		$this->ai_button( 'generate_schema', __( 'Get AI Help', 'seoistic' ) );
		echo '</div>';
		echo '<select name="seoistic_schema_type" id="seoistic_schema_type">';
		foreach ( self::SCHEMA_TYPES as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $schema, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		$this->field_text( 'seoistic_breadcrumb_title', __( 'Breadcrumb title', 'seoistic' ), $crumb );
		$this->schema_validation_notice( (int) $post->ID );
		echo '</div>';
	}

	/**
	 * Local, structural warnings only — not a Google Rich Results Test call.
	 * Flags schema.org properties the chosen type requires/recommends that this
	 * post (or SEOISTIC itself) doesn't currently supply.
	 */
	private function schema_validation_notice( int $post_id ): void {
		$result = SchemaValidator::validate_for_post( $post_id );
		if ( null === $result || ( array() === $result['missing_required'] && array() === $result['missing_recommended'] ) ) {
			return;
		}

		echo '<div class="seoistic-field">';
		echo '<div class="seoistic-field-label">' . esc_html(
			sprintf(
				/* translators: %s: schema.org type, e.g. Product. */
				__( '%s schema check', 'seoistic' ),
				$result['type']
			)
		) . '</div>';

		foreach ( $result['missing_required'] as $item ) {
			echo '<div class="seoistic-sidebar-issue"><span class="dashicons dashicons-warning"></span> ' . esc_html(
				'' !== $item['note']
					? sprintf(
						/* translators: 1: schema.org property name, 2: what data is missing. */
						__( 'Missing required property: %1$s (SEOISTIC has no field yet for %2$s).', 'seoistic' ),
						$item['property'],
						$item['note']
					)
					: sprintf(
						/* translators: %s: schema.org property name. */
						__( 'Missing required property: %s.', 'seoistic' ),
						$item['property']
					)
			) . '</div>';
		}
		foreach ( $result['missing_recommended'] as $item ) {
			echo '<div class="seoistic-sidebar-issue"><span class="dashicons dashicons-info"></span> ' . esc_html(
				sprintf(
					/* translators: %s: schema.org property name. */
					__( 'Recommended property missing: %s.', 'seoistic' ),
					$item['property']
				)
			) . '</div>';
		}
		echo '</div>';
	}

	private function pane_audit( WP_Post $post ): void {
		echo '<div class="seoistic-panel-pane" data-seoistic-pane="audit">';
		$score = PostSeo::score( (int) $post->ID );
		if ( $score < 0 ) {
			$result = Scorer::score( $post );
		} else {
			$result = array( 'score' => $score, 'checks' => PostSeo::audit_report( (int) $post->ID ) );
		}

		echo '<div class="seoistic-flex" style="margin-bottom:16px;">';
		echo View::score_ring( (int) $result['score'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div><button type="button" class="seoistic-btn seoistic-btn-primary" id="seoistic-run-post-audit" data-post-id="' . esc_attr( (string) $post->ID ) . '"><span class="dashicons dashicons-update"></span> ' . esc_html__( 'Run Audit', 'seoistic' ) . '</button>';
		if ( '' !== PostSeo::last_audit( (int) $post->ID ) ) {
			echo '<p class="description" style="margin-top:8px;">' . esc_html(
				sprintf(
					/* translators: %s: last audit date/time. */
					__( 'Last audited %s', 'seoistic' ),
					PostSeo::last_audit( (int) $post->ID )
				)
			) . '</p>';
		}
		echo '</div></div>';

		echo '<div class="seoistic-audit-groups" id="seoistic-audit-checklist">';
		echo '<div class="seoistic-audit-group"><h4>' . esc_html__( 'Checklist', 'seoistic' ) . '</h4>';
		foreach ( (array) $result['checks'] as $check ) {
			$icon = ! empty( $check['pass'] ) ? 'yes-alt' : 'warning';
			$tone = ! empty( $check['pass'] ) ? 'pass' : 'warning';
			echo '<div class="seoistic-audit-item">';
			echo '<span class="seoistic-audit-icon ' . esc_attr( $tone ) . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></span>';
			echo '<div class="seoistic-audit-text">' . esc_html( (string) ( $check['label'] ?? '' ) );
			if ( ! empty( $check['message'] ) ) {
				echo '<div class="seoistic-audit-fix">' . esc_html( (string) $check['message'] ) . '</div>';
			}
			echo '</div>';
			if ( empty( $check['pass'] ) ) {
				$this->ai_button( '', __( 'Fix with AI', 'seoistic' ), array( 'size' => 'sm', 'fix' => (string) ( $check['id'] ?? '' ) ) );
			}
			echo '</div>';
		}
		echo '</div></div>';
		echo '</div>';
	}

	private function pane_ai( bool $ai_ready ): void {
		echo '<div class="seoistic-panel-pane" data-seoistic-pane="ai">';
		if ( ! $ai_ready ) {
			echo '<p class="description">' . esc_html__( 'Add an OpenRouter API key under SEOISTIC → Settings → AI to enable these actions.', 'seoistic' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=seoistic-settings&tab=ai' ) ) . '">' . esc_html__( 'Open AI settings', 'seoistic' ) . '</a></p>';
		}

		echo '<div class="seoistic-ai-action-grid">';
		$this->ai_action_card( 'admin-comments', __( 'Improve Content', 'seoistic' ), __( 'Concrete, specific suggestions to strengthen this page.', 'seoistic' ), 'optimize_content' );
		$this->ai_action_card( 'admin-links', __( 'Internal Link Suggestions', 'seoistic' ), __( 'Where to link this page to related content.', 'seoistic' ), 'internal_links' );
		$this->ai_action_card( 'superhero', __( 'Full Page Optimization', 'seoistic' ), __( 'Title, description and focus keywords in one pass.', 'seoistic' ), 'full_page_optimization', true );
		echo '</div>';

		echo '<div id="seoistic-ai-result" class="seoistic-tool-result"></div>';
		echo '</div>';
	}

	private function ai_action_card( string $icon, string $title, string $desc, string $action, bool $primary = false ): void {
		echo '<div class="seoistic-ai-action-card">';
		echo '<div class="seoistic-card-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></div>';
		echo '<strong>' . esc_html( $title ) . '</strong>';
		echo '<p>' . esc_html( $desc ) . '</p>';
		$this->ai_button( $action, $primary ? __( 'Optimize with AI', 'seoistic' ) : __( 'Get AI Help', 'seoistic' ), array( 'primary' => $primary ) );
		echo '</div>';
	}

	/**
	 * A single "Get AI Help" pill button — gold gradient, shimmer on hover, a
	 * gently pulsing icon so it reads as an AI action at a glance.
	 *
	 * @param array{primary?:bool,size?:string,fix?:string} $opts
	 */
	private function ai_button( string $action, string $label, array $opts = array() ): void {
		$class = 'seoistic-ai-btn';
		if ( ! empty( $opts['primary'] ) ) {
			$class .= ' seoistic-ai-btn-primary';
		} elseif ( 'sm' === ( $opts['size'] ?? '' ) ) {
			$class .= ' seoistic-ai-btn-sm';
		}

		// A "fix" button only ever carries data-seoistic-ai-fix — ai.js switches to
		// the AI tab and clicks the *actual* action button there. Giving it its own
		// data-seoistic-ai-action too would make both handlers fire on one click.
		if ( isset( $opts['fix'] ) ) {
			echo '<button type="button" class="' . esc_attr( $class ) . '" data-seoistic-ai-fix="' . esc_attr( $opts['fix'] ) . '"><span class="dashicons dashicons-superhero"></span> ' . esc_html( $label ) . '</button>';
			return;
		}

		echo '<button type="button" class="' . esc_attr( $class ) . '" data-seoistic-ai-action="' . esc_attr( $action ) . '"><span class="dashicons dashicons-superhero"></span> ' . esc_html( $label ) . '</button>';
	}

	/**
	 * @param array{min?:int,max?:int,type?:string,placeholder?:string,ai?:array{action:string,label:string}} $opts
	 */
	private function field_text( string $name, string $label, string $value, array $opts = array() ): void {
		$type = $opts['type'] ?? 'text';
		echo '<div class="seoistic-field">';
		echo '<div class="seoistic-field-head"><label class="seoistic-field-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		if ( isset( $opts['ai'] ) ) {
			$this->ai_button( $opts['ai']['action'], $opts['ai']['label'] );
		}
		echo '</div>';
		echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( isset( $opts['placeholder'] ) ? ' placeholder="' . esc_attr( $opts['placeholder'] ) . '"' : '' ) . ( isset( $opts['min'], $opts['max'] ) ? ' data-seoistic-counter data-min="' . (int) $opts['min'] . '" data-max="' . (int) $opts['max'] . '"' : '' ) . '>';
		if ( isset( $opts['min'], $opts['max'] ) ) {
			$this->counter_markup( $name );
		}
		echo '</div>';
	}

	/**
	 * @param array{min?:int,max?:int,rows?:int,ai?:array{action:string,label:string}} $opts
	 */
	private function field_textarea( string $name, string $label, string $value, array $opts = array() ): void {
		$rows = $opts['rows'] ?? 3;
		echo '<div class="seoistic-field">';
		echo '<div class="seoistic-field-head"><label class="seoistic-field-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		if ( isset( $opts['ai'] ) ) {
			$this->ai_button( $opts['ai']['action'], $opts['ai']['label'] );
		}
		echo '</div>';
		echo '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" rows="' . (int) $rows . '"' . ( isset( $opts['min'], $opts['max'] ) ? ' data-seoistic-counter data-min="' . (int) $opts['min'] . '" data-max="' . (int) $opts['max'] . '"' : '' ) . '>' . esc_textarea( $value ) . '</textarea>';
		if ( isset( $opts['min'], $opts['max'] ) ) {
			$this->counter_markup( $name );
		}
		echo '</div>';
	}

	private function counter_markup( string $name ): void {
		echo '<div class="seoistic-counter-row"><span class="seoistic-char-count" data-seoistic-counter-for="' . esc_attr( $name ) . '"></span></div>';
		echo '<div class="seoistic-bar"><div class="seoistic-bar-fill" data-seoistic-bar-for="' . esc_attr( $name ) . '"></div></div>';
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['seoistic_seo_panel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seoistic_seo_panel_nonce'] ) ), 'seoistic_seo_panel' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ), true ) ) {
			return;
		}

		PostSeo::save(
			$post_id,
			array(
				'_seoistic_title'            => wp_unslash( $_POST['seoistic_title'] ?? '' ),
				'_seoistic_description'      => wp_unslash( $_POST['seoistic_description'] ?? '' ),
				'_seoistic_focus_keyword'    => wp_unslash( $_POST['seoistic_focus_keyword'] ?? '' ),
				'_seoistic_canonical'        => wp_unslash( $_POST['seoistic_canonical'] ?? '' ),
				'_seoistic_noindex'          => isset( $_POST['seoistic_noindex'] ),
				'_seoistic_nofollow'         => isset( $_POST['seoistic_nofollow'] ),
				'_seoistic_og_title'         => wp_unslash( $_POST['seoistic_og_title'] ?? '' ),
				'_seoistic_og_description'   => wp_unslash( $_POST['seoistic_og_description'] ?? '' ),
				'_seoistic_og_image'         => wp_unslash( $_POST['seoistic_og_image'] ?? '' ),
				'_seoistic_schema_type'      => wp_unslash( $_POST['seoistic_schema_type'] ?? '' ),
				'_seoistic_breadcrumb_title' => wp_unslash( $_POST['seoistic_breadcrumb_title'] ?? '' ),
			)
		);
	}
}
