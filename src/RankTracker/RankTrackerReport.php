<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\RankTracker;

use Wpistic\Seoistic\Core\EmailReportTemplate;

final class RankTrackerReport {

	/**
	 * @param array<int, array<string,mixed>> $rows
	 * @param array<string, mixed> $settings
	 */
	public function __construct( private array $rows, private array $settings ) {}

	public function html( bool $admin = true ): string {
		$color = (string) ( $this->settings['primary_color'] ?? '#9472ff' );
		$brand = (string) ( $this->settings['brand_name'] ?? get_bloginfo( 'name' ) );
		$logo = (string) ( $this->settings['logo_url'] ?? '' );
		$generated = gmdate( 'Y-m-d' );
		if ( ! $admin ) {
			return $this->email_html( $brand, $generated );
		}
		$rows = '';
		foreach ( $this->rows as $row ) {
			$keyword = (array) $row['keyword'];
			$current = null === $row['current'] ? '—' : number_format_i18n( (float) $row['current'], 1 );
			$change = $this->change_html( $row['change'] ?? null );
			$source = (string) ( $row['source_label'] ?? '' );
			$rows .= '<tr><td>' . esc_html( (string) $keyword['keyword'] ) . '</td><td>' . esc_html( (string) $keyword['locale'] ) . '</td><td>' . esc_html( (string) $keyword['device'] ) . '</td><td><strong>' . esc_html( (string) $current ) . '</strong></td><td>' . $change . '</td><td>' . esc_html( $source ) . '</td></tr>';
		}
		if ( '' === $rows ) {
			$rows = '<tr><td colspan="6">' . esc_html__( 'No keywords are being tracked yet.', 'seoistic' ) . '</td></tr>';
		}
		$logo_html = '' !== $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $brand ) . '">' : '';
		$button = $admin ? '<button type="button" class="seoistic-report-print" data-rank-report-print>' . esc_html__( 'Print to PDF', 'seoistic' ) . '</button>' : '';
		/* translators: %s: brand name. */
		$title = sprintf( __( '%s — SEO Rank Report', 'seoistic' ), $brand );
		/* translators: 1: report date, 2: site home URL. */
		$meta = sprintf( __( 'Generated on %1$s · Site: %2$s', 'seoistic' ), $generated, home_url( '/' ) );

		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_attr( $title ) . '</title><style>' . $this->css( $color, $admin ) . '</style></head><body><main><header><div>' . $logo_html . '<h1>' . esc_html( $brand ) . '</h1><p>' . esc_html__( 'SEO Rank Report', 'seoistic' ) . '</p></div>' . $button . '</header><p class="meta">' . esc_html( $meta ) . '</p><table><thead><tr><th>' . esc_html__( 'Keyword', 'seoistic' ) . '</th><th>' . esc_html__( 'Locale', 'seoistic' ) . '</th><th>' . esc_html__( 'Device', 'seoistic' ) . '</th><th>' . esc_html__( 'Position', 'seoistic' ) . '</th><th>' . esc_html__( 'Movement', 'seoistic' ) . '</th><th>' . esc_html__( 'Data source', 'seoistic' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>' . $this->footnote() . '</main></body></html>';
	}

	private function email_html( string $brand, string $generated ): string {
		$table_rows = array();
		$ranked = 0;
		$improved = 0;
		$declined = 0;
		$position_total = 0.0;
		foreach ( $this->rows as $row ) {
			$keyword = (array) $row['keyword'];
			$current = null === $row['current'] ? '—' : number_format_i18n( (float) $row['current'], 1 );
			$change  = (float) ( $row['change'] ?? 0 );
			if ( is_numeric( $row['current'] ) ) {
				++$ranked;
				$position_total += (float) $row['current'];
			}
			$movement_tone = 'neutral';
			$movement_text = '—';
			if ( is_numeric( $row['change'] ?? null ) && $change > 0 ) {
				++$improved;
				$movement_tone = 'ok';
				$movement_text = '▲ ' . number_format_i18n( $change, 1 );
			} elseif ( is_numeric( $row['change'] ?? null ) && $change < 0 ) {
				++$declined;
				$movement_tone = 'bad';
				$movement_text = '▼ ' . number_format_i18n( abs( $change ), 1 );
			}
			$movement_color = 'ok' === $movement_tone ? '#059669' : ( 'bad' === $movement_tone ? '#B91C1C' : '#64748B' );
			$table_rows[] = array(
				esc_html( (string) $keyword['keyword'] ),
				esc_html( (string) $keyword['locale'] ),
				esc_html( (string) $keyword['device'] ),
				'<strong style="color:#0F172A">' . esc_html( (string) $current ) . '</strong>',
				'<strong style="color:' . $movement_color . '">' . $movement_text . '</strong>',
				esc_html( (string) ( $row['source_label'] ?? '' ) ),
			);
		}

		$average_position = $ranked > 0 ? number_format_i18n( $position_total / $ranked, 1 ) : '—';
		$table = EmailReportTemplate::data_table(
			array( __( 'Keyword', 'seoistic' ), __( 'Locale', 'seoistic' ), __( 'Device', 'seoistic' ), __( 'Position', 'seoistic' ), __( 'Movement', 'seoistic' ), __( 'Data source', 'seoistic' ) ),
			$table_rows,
			25
		);
		if ( '' !== $this->footnote() ) {
			$table .= '<p style="color:#64748b;font-size:12px">' . wp_strip_all_tags( $this->footnote() ) . '</p>';
		}

		$stats = EmailReportTemplate::stat_cards( array(
			array( 'label' => __( 'Tracked', 'seoistic' ), 'value' => (string) count( $this->rows ), 'tone' => 'info' ),
			array( 'label' => __( 'Ranked', 'seoistic' ), 'value' => (string) $ranked, 'tone' => 'neutral' ),
			array( 'label' => __( 'Avg. position', 'seoistic' ), 'value' => (string) $average_position, 'tone' => 'neutral' ),
			array( 'label' => __( 'Improved', 'seoistic' ), 'value' => (string) $improved, 'tone' => 'ok' ),
		) );

		/* translators: %s: brand name. */
		$email_title = sprintf( __( '%s — SEO Rank Report', 'seoistic' ), $brand );
		/* translators: %s: report date. */
		$preheader = sprintf( __( 'Your SEO rank report for %s.', 'seoistic' ), $generated );
		/* translators: 1: report date, 2: site home URL. */
		$email_intro = sprintf( __( 'Generated on %1$s for %2$s', 'seoistic' ), $generated, home_url( '/' ) );
		return EmailReportTemplate::render(
			$email_title,
			$preheader,
			$email_intro,
			$stats . $table,
			__( 'Open Rank Tracker', 'seoistic' ),
			admin_url( 'admin.php?page=seoistic-rank-tracker' ),
			array(
				'brand_label'  => $brand,
				'accent_color' => (string) ( $this->settings['primary_color'] ?? '#00C857' ),
				'logo_url'     => (string) ( $this->settings['logo_url'] ?? '' ),
			)
		);
	}

	private function footnote(): string {
		$delayed = false;
		foreach ( $this->rows as $row ) {
			if ( str_contains( (string) ( $row['source_label'] ?? '' ), 'Search Console' ) ) {
				$delayed = true;
				break;
			}
		}
		return $delayed ? '<footer>' . esc_html__( 'Rows labeled Search Console data (delayed) use average positions from Google Search Console and may lag live results.', 'seoistic' ) . '</footer>' : '';
	}

	private function change_html( mixed $change ): string {
		if ( ! is_numeric( $change ) || 0.0 === (float) $change ) {
			return '<span class="change neutral">—</span>';
		}
		$value = (float) $change;
		if ( $value > 0 ) {
			return '<span class="change up">▲ ' . esc_html( number_format_i18n( $value, 1 ) ) . '</span>';
		}
		return '<span class="change down">▼ ' . esc_html( number_format_i18n( abs( $value ), 1 ) ) . '</span>';
	}

	private function css( string $color, bool $admin ): string {
		$button = $admin ? '.seoistic-report-print{cursor:pointer;border:0;border-radius:999px;padding:10px 18px;font:650 14px system-ui,sans-serif;color:#fff;background:' . esc_attr( $color ) . '}@media print{.seoistic-report-print{display:none}}' : '';
		return '*,*::before,*::after{box-sizing:border-box}body{margin:0;background:#f3f5f9;color:#0a0f1f;font:14px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}main{max-width:960px;margin:32px auto;background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 30px rgba(10,15,31,.08)}header{display:flex;justify-content:space-between;align-items:center;gap:24px;border-bottom:3px solid ' . esc_attr( $color ) . ';padding-bottom:20px}header>div{display:flex;align-items:center;gap:16px}img{max-height:52px;width:auto}h1{margin:0;font-size:22px}header p{margin:0;color:#667085}table{width:100%;border-collapse:collapse;margin-top:28px}th,td{padding:12px 10px;border-bottom:1px solid #e8ebf1;text-align:left}th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#667085}.change{font-weight:700}.up{color:#12876f}.down{color:#d14343}.neutral{color:#667085}footer{margin-top:24px;color:#667085;font-size:12px}body{background:#f3f5f9}@media print{body{background:#fff}main{box-shadow:none;margin:0;max-width:none;padding:0}}' . $button;
	}
}
