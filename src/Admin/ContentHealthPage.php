<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Core\ContentDecay;
use Wpistic\Seoistic\Core\LinkGraph;

/**
 * SEOISTIC → Content Health. Two advisory reports that need a human decision,
 * not an auto-apply: orphan pages (published, but nothing else on the site
 * links to them) and content decay (score dropped since the last audit on a
 * page nobody has touched in a while). Both link out to the post editor
 * rather than acting directly — placing an internal link or refreshing copy
 * needs a person, same reasoning as the existing AI internal-link report.
 */
final class ContentHealthPage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 21 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_seoistic_scan_orphans', array( $this, 'ajax_scan_orphans' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Content Health', 'seoistic' ), __( 'Content Health', 'seoistic' ), 'manage_options', 'seoistic-content-health', array( $this, 'render' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( (string) $hook, 'seoistic-content-health' ) ) {
			return;
		}
		wp_enqueue_script( 'seoistic-content-health', SEOISTIC_URL . 'assets/js/content-health.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
		wp_localize_script(
			'seoistic-content-health',
			'SeoisticContentHealth',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'seoistic_scan_orphans' ),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::header( 'seoistic-content-health', __( 'Content Health', 'seoistic' ), __( 'Orphan pages nothing links to, and pages whose score has dropped since they were last touched — both advisory, both link straight to the editor to fix.', 'seoistic' ) );

		$orphans = LinkGraph::cached_orphans();
		$decay   = ContentDecay::flagged();

		echo '<div class="seoistic-cards">';
		View::card( 'editor-unlink', null === $orphans ? '—' : (string) count( $orphans ), __( 'Orphan Pages', 'seoistic' ), null === $orphans ? 'neutral' : ( array() === $orphans ? 'good' : 'warn' ) );
		View::card( 'chart-line', (string) count( $decay ), __( 'Decaying Pages', 'seoistic' ), array() === $decay ? 'good' : 'warn' );
		echo '</div>';

		$this->render_orphans( $orphans );
		$this->render_decay( $decay );

		View::footer();
	}

	/**
	 * @param array<int, array{id:int, title:string, edit_url:string}>|null $orphans
	 */
	private function render_orphans( ?array $orphans ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Orphan pages', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<p class="description">' . esc_html__( 'Published pages that no other published page links to in its content. A page with zero inbound internal links is harder for both visitors and search engines to find.', 'seoistic' ) . '</p>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" id="seoistic-scan-orphans"><span class="dashicons dashicons-search"></span> ' . ( null === $orphans ? esc_html__( 'Scan for orphan pages', 'seoistic' ) : esc_html__( 'Rescan', 'seoistic' ) ) . '</button>';
		echo '<div class="seoistic-tool-result" id="seoistic-orphans-result"></div>';

		if ( null !== $orphans && array() !== $orphans ) {
			echo '<table class="widefat striped" style="margin-top:14px;"><thead><tr><th>' . esc_html__( 'Page', 'seoistic' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $orphans as $page ) {
				echo '<tr><td>' . esc_html( $page['title'] ) . '</td><td><a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( $page['edit_url'] ) . '">' . esc_html__( 'Edit', 'seoistic' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		} elseif ( null !== $orphans ) {
			echo '<p class="description" style="margin-top:14px;">' . esc_html__( 'No orphan pages found — every published page has at least one inbound internal link.', 'seoistic' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * @param array<int, array{id:int, title:string, score:int, previous_score:int, post_modified:string, edit_url:string}> $decay
	 */
	private function render_decay( array $decay ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Content decay', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<p class="description">' . esc_html__( 'Published pages, untouched for 12+ months, whose SEO score has dropped since their last audit — worth a refresh.', 'seoistic' ) . '</p>';

		if ( array() === $decay ) {
			echo '<p class="description">' . esc_html__( 'No decaying pages found.', 'seoistic' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'seoistic' ) . '</th><th>' . esc_html__( 'Score', 'seoistic' ) . '</th><th>' . esc_html__( 'Last modified', 'seoistic' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $decay as $page ) {
				echo '<tr><td>' . esc_html( $page['title'] ) . '</td>';
				echo '<td>' . (int) $page['previous_score'] . ' &rarr; ' . (int) $page['score'] . '</td>';
				echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $page['post_modified'] ) ) . '</td>';
				echo '<td><a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( $page['edit_url'] ) . '">' . esc_html__( 'Refresh with AI', 'seoistic' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	public function ajax_scan_orphans(): void {
		check_ajax_referer( 'seoistic_scan_orphans', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seoistic' ) ), 403 );
		}

		$orphans = LinkGraph::scan();
		wp_send_json_success(
			array(
				'count'   => count( $orphans ),
				/* translators: %d: number of orphan pages found. */
				'message' => sprintf( _n( '%d orphan page found.', '%d orphan pages found.', count( $orphans ), 'seoistic' ), count( $orphans ) ),
			)
		);
	}
}
