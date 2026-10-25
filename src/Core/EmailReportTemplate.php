<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * SEOISTIC Aurora — the shared premium presentation for every email the
 * plugin sends (scheduled audits, automation runs, Core Web Vitals alerts,
 * rank reports, uptime notifications).
 *
 * Design goals:
 *  - Email-client-safe: table layout, inline styles, no external images.
 *  - Brand-faithful: the same navy / violet / sky palette as the wp-admin UI.
 *  - Alive: a <style> block adds animated aurora + entrance motion in clients
 *    that support CSS animations (Apple Mail, iOS, Thunderbird…). Every other
 *    client renders the identical static design — nothing depends on script.
 *  - White-label aware: rank reports can override the accent color and logo.
 */
final class EmailReportTemplate {

	private const NAVY   = '#0F172A';
	private const VIOLET = '#7C3AED';
	private const SKY    = '#0EA5E9';
	private const GRADIENT = 'linear-gradient(135deg,#7C3AED 0%,#0EA5E9 100%)';

	/** Brand + tone palette used by the builders below. */
	private const TONES = array(
		'ok'      => array( 'fg' => '#059669', 'bg' => '#D1FAE5', 'line' => '#10B981' ),
		'warn'    => array( 'fg' => '#B45309', 'bg' => '#FEF3C7', 'line' => '#F59E0B' ),
		'bad'     => array( 'fg' => '#B91C1C', 'bg' => '#FEE2E2', 'line' => '#EF4444' ),
		'info'    => array( 'fg' => '#1D4ED8', 'bg' => '#DBEAFE', 'line' => '#3B82F6' ),
		'neutral' => array( 'fg' => '#334155', 'bg' => '#F1F5F9', 'line' => '#94A3B8' ),
	);

	/**
	 * Render a branded report email with a single-column layout and a clear CTA.
	 * Content should be built from escaped values and trusted plugin markup
	 * (see the stat_cards/metric_row/progress_bar/data_table builders).
	 *
	 * @param array<string, mixed> $branding Optional: brand_label, accent_color,
	 *        logo_url (white-label), tone ('ok'|'warn'|'bad'|'info'), context
	 *        (one-line site/date context shown under the title).
	 */
	public static function render( string $title, string $preheader, string $intro, string $content, string $button_label, string $button_url, array $branding = array() ): string {
		$brand_label  = sanitize_text_field( (string) ( $branding['brand_label'] ?? 'SEOISTIC' ) );
		$accent_color = sanitize_hex_color( (string) ( $branding['accent_color'] ?? '' ) );
		$white_label  = '' !== $accent_color;
		$accent       = $white_label ? $accent_color : self::VIOLET;
		$logo_url     = esc_url( (string) ( $branding['logo_url'] ?? '' ) );
		$tone         = isset( self::TONES[ (string) ( $branding['tone'] ?? '' ) ] ) ? (string) $branding['tone'] : '';
		$context      = sanitize_text_field( (string) ( $branding['context'] ?? '' ) );

		$site_name = (string) get_bloginfo( 'name' );
		$host      = (string) wp_parse_url( (string) home_url(), PHP_URL_HOST );
		$today     = wp_date( get_option( 'date_format', 'Y-m-d' ) );

		$status_pill = '' !== $tone
			? '<td align="right" style="padding:0"><span style="display:inline-block;padding:5px 12px;border-radius:999px;background:' . esc_attr( self::TONES[ $tone ]['bg'] ) . ';color:' . esc_attr( self::TONES[ $tone ]['fg'] ) . ';font:700 11px/1.4 Arial,Helvetica,sans-serif;letter-spacing:.08em;text-transform:uppercase">' . esc_html( self::tone_label( $tone ) ) . '</span></td>'
			: '';

		$logo_html = '' !== $logo_url
			? '<img src="' . $logo_url . '" alt="' . esc_attr( $brand_label ) . '" style="display:block;max-width:160px;max-height:36px;width:auto;height:auto">'
			: '<span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:' . ( $white_label ? esc_attr( $accent_color ) : self::GRADIENT ) . '"></span> <span style="font:800 17px/1 Arial,Helvetica,sans-serif;letter-spacing:.14em;color:#F8FAFC">' . esc_html( strtoupper( $brand_label ) ) . '</span>';

		$context_line = '' !== $context
			? '<p style="margin:10px 0 0;font:600 12px/1.5 Arial,Helvetica,sans-serif;color:#94A3B8;letter-spacing:.04em">' . esc_html( $context ) . '</p>'
			: '';

		$button = '';
		if ( '' !== $button_label && '' !== $button_url ) {
			$button = '<tr><td style="padding:8px 32px 32px"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>' .
				'<td bgcolor="' . esc_attr( $accent ) . '" style="border-radius:12px;background-image:' . ( $white_label ? 'none' : self::GRADIENT ) . ';box-shadow:0 6px 18px rgba(124,58,237,.35)">' .
				'<a role="button" href="' . esc_url( $button_url ) . '" style="display:inline-block;min-height:46px;padding:0 26px;line-height:46px;color:#ffffff;text-decoration:none;font:700 15px/46px Arial,Helvetica,sans-serif;border-radius:12px">' . esc_html( $button_label ) . '</a>' .
				'</td></tr></table></td></tr>';
		}

		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . esc_html( $title ) . '</title>' .
			'<style>' .
			'@keyframes seoistic-flow{0%{background-position:0% 50%}100%{background-position:200% 50%}}' .
			'@keyframes seoistic-rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}' .
			'.seoistic-aurora{background-size:200% 100%;animation:seoistic-flow 6s linear infinite}' .
			'.seoistic-anim{animation:seoistic-rise .7s ease-out both}' .
			'.seoistic-anim-2{animation:seoistic-rise .7s ease-out .15s both}' .
			'@media (prefers-reduced-motion:reduce){.seoistic-aurora,.seoistic-anim,.seoistic-anim-2{animation:none}}' .
			'</style></head>' .
			'<body style="margin:0;padding:28px 12px;background:#F1F5F9;color:#0F172A;font:15px/1.6 Arial,Helvetica,sans-serif">' .
			'<span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden">' . esc_html( $preheader ) . '</span>' .
			'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F1F5F9"><tr><td align="center">' .
			'<table role="presentation" class="seoistic-anim" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #E2E8F0;border-radius:20px;overflow:hidden;box-shadow:0 14px 40px rgba(15,23,42,.10);text-align:left">' .

			/* Header */
			'<tr><td style="padding:26px 32px 22px;background:' . self::NAVY . '">' .
			'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>' .
			'<td style="padding:0">' . $logo_html . '</td>' . $status_pill .
			'</tr></table>' .
			'<p style="margin:12px 0 0;font:600 12px/1.5 Arial,Helvetica,sans-serif;color:#94A3B8;letter-spacing:.06em">' . esc_html( $site_name . ( '' !== $host ? ' · ' . $host : '' ) ) . '</p>' .
			'</td></tr>' .

			/* Animated aurora bar (static gradient fallback) */
			'<tr><td class="seoistic-aurora" style="height:6px;line-height:6px;font-size:0;background:' . ( $white_label ? esc_attr( $accent_color ) : 'linear-gradient(90deg,#7C3AED 0%,#0EA5E9 50%,#7C3AED 100%)' ) . '">&nbsp;</td></tr>' .

			/* Title + context + intro */
			'<tr><td class="seoistic-anim-2" style="padding:26px 32px 6px"><h1 style="margin:0;font:800 24px/1.3 Arial,Helvetica,sans-serif;color:#0F172A">' . esc_html( $title ) . '</h1>' . $context_line . '</td></tr>' .
			'<tr><td style="padding:12px 32px 4px;color:#334155">' . esc_html( $intro ) . '</td></tr>' .

			/* Content */
			'<tr><td style="padding:16px 32px 8px;color:#0F172A">' . wp_kses_post( $content ) . '</td></tr>' .

			$button .

			/* Footer */
			'<tr><td style="padding:18px 32px 26px;border-top:1px solid #E2E8F0">' .
			'<p style="margin:0 0 4px;font:700 12px/1.5 Arial,Helvetica,sans-serif;color:#64748B">' . esc_html( sprintf( /* translators: %s: site name. */ __( 'Sent by SEOISTIC for %s', 'seoistic' ), $site_name ) ) . '</p>' .
			'<p style="margin:0;font:400 11px/1.6 Arial,Helvetica,sans-serif;color:#94A3B8">' .
			esc_html( sprintf( /* translators: %s: report date. */ __( 'Automated SEO insight · %s', 'seoistic' ), $today ) ) .
			' · <a href="' . esc_url( admin_url( 'admin.php?page=seoistic' ) ) . '" style="color:#0EA5E9;text-decoration:none">' . esc_html__( 'Open the SEOISTIC dashboard', 'seoistic' ) . '</a>' .
			( $white_label ? '' : ' · <a href="https://seoistic.wpistic.com/" style="color:#0EA5E9;text-decoration:none">seoistic.wpistic.com</a>' ) .
			'</p></td></tr>' .

			'</table></td></tr></table></body></html>';
	}

	/** Standard HTML header used by all SEOistic email notifications. */
	public static function headers(): array {
		return array( 'Content-Type: text/html; charset=UTF-8' );
	}

	/* ------------------------------------------------------------------ */
	/* Content builders — trusted markup, escaped values.                  */
	/* ------------------------------------------------------------------ */

	/**
	 * A row of stat tiles (2–4). Each card: label, value, tone, optional sub.
	 *
	 * @param array<int, array{label:string, value:string, tone?:string, sub?:string}> $cards
	 */
	public static function stat_cards( array $cards ): string {
		$cards = array_slice( array_values( $cards ), 0, 4 );
		if ( array() === $cards ) {
			return '';
		}
		$width = count( $cards ) > 2 ? '33%' : '50%';
		$cells = '';
		foreach ( $cards as $card ) {
			$tone  = isset( self::TONES[ (string) ( $card['tone'] ?? '' )] ) ? (string) $card['tone'] : 'neutral';
			$color = self::TONES[ $tone ]['fg'];
			$sub   = '' !== (string) ( $card['sub'] ?? '' ) ? '<div style="margin-top:4px;font:400 11px/1.4 Arial,Helvetica,sans-serif;color:#64748B">' . esc_html( (string) $card['sub'] ) . '</div>' : '';
			$cells .= '<td width="' . esc_attr( $width ) . '" valign="top" style="padding:10px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px">' .
				'<div style="font:700 10px/1.4 Arial,Helvetica,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#64748B">' . esc_html( (string) $card['label'] ) . '</div>' .
				'<div style="margin-top:6px;font:800 24px/1.1 Arial,Helvetica,sans-serif;color:' . esc_attr( $color ) . '">' . esc_html( (string) $card['value'] ) . '</div>' .
				$sub . '</td>';
		}
		return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate;border-spacing:8px 0;margin:0 -8px"><tr>' . $cells . '</tr></table>';
	}

	/**
	 * A labelled metric line with value + status chip.
	 */
	public static function metric_row( string $label, string $value, string $status = '', string $tone = 'neutral' ): string {
		$tone  = isset( self::TONES[ $tone ] ) ? $tone : 'neutral';
		$chip  = '' !== $status
			? '<span style="display:inline-block;padding:3px 10px;border-radius:999px;background:' . self::TONES[ $tone ]['bg'] . ';color:' . self::TONES[ $tone ]['fg'] . ';font:700 11px/1.5 Arial,Helvetica,sans-serif">' . esc_html( $status ) . '</span>'
			: '';
		return '<tr>' .
			'<td style="padding:10px 0;border-bottom:1px solid #EEF2F7;font:600 13px/1.5 Arial,Helvetica,sans-serif;color:#334155">' . esc_html( $label ) . '</td>' .
			'<td align="right" style="padding:10px 0;border-bottom:1px solid #EEF2F7;white-space:nowrap"><strong style="font:800 15px/1.4 Arial,Helvetica,sans-serif;color:#0F172A">' . esc_html( $value ) . '</strong>' . ( '' !== $chip ? ' &nbsp;' . $chip : '' ) . '</td>' .
			'</tr>';
	}

	/**
	 * A labelled horizontal progress bar (0–100) with an optional right-hand note.
	 */
	public static function progress_bar( string $label, int $percent, string $tone = 'info', string $note = '' ): string {
		$tone   = isset( self::TONES[ $tone ] ) ? $tone : 'info';
		$percent = max( 0, min( 100, $percent ) );
		$fill   = 'background:' . self::TONES[ $tone ]['line'];
		$fill   = 'info' === $tone ? 'background:' . self::GRADIENT : $fill;
		return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:10px 0"><tr>' .
			'<td style="padding:0 0 6px;font:600 12px/1.5 Arial,Helvetica,sans-serif;color:#334155">' . esc_html( $label ) . ( '' !== $note ? ' <span style="font:700 12px/1.5 Arial,Helvetica,sans-serif;color:#0F172A">· ' . esc_html( $note ) . '</span>' : '' ) . '</td>' .
			'</tr><tr><td style="padding:0;background:#F1F5F9;border-radius:999px;height:10px;line-height:10px;font-size:0">' .
			'<div style="width:' . $percent . '%;height:10px;border-radius:999px;' . $fill . '">&nbsp;</div>' .
			'</td></tr></table>';
	}

	/**
	 * A compact data table (headers + rows of already-escaped-safe cell values).
	 *
	 * @param array<int, string> $headers
	 * @param array<int, array<int, string>> $rows Rows of plain strings (escaped here).
	 */
	public static function data_table( array $headers, array $rows, int $max = 10 ): string {
		$head = '';
		foreach ( $headers as $header ) {
			$head .= '<th align="left" style="padding:8px 10px;border-bottom:2px solid #E2E8F0;font:700 10px/1.4 Arial,Helvetica,sans-serif;letter-spacing:.07em;text-transform:uppercase;color:#64748B">' . esc_html( (string) $header ) . '</th>';
		}
		$body = '';
		foreach ( array_slice( $rows, 0, $max ) as $row ) {
			$body .= '<tr>';
			foreach ( $row as $cell_index => $cell ) {
				$is_first = 0 === $cell_index;
				$body .= '<td style="padding:9px 10px;border-bottom:1px solid #EEF2F7;font:' . ( $is_first ? '700' : '400' ) . ' 13px/1.5 Arial,Helvetica,sans-serif;color:' . ( $is_first ? '#0F172A' : '#334155' ) . '">' . wp_kses_post( (string) $cell ) . '</td>';
			}
			$body .= '</tr>';
		}
		if ( '' === $body ) {
			$body = '<tr><td colspan="' . count( $headers ) . '" style="padding:12px 10px;font:400 13px/1.5 Arial,Helvetica,sans-serif;color:#64748B">' . esc_html__( 'Nothing to report yet.', 'seoistic' ) . '</td></tr>';
		}
		return '<div style="overflow-x:auto"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-size:13px"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
	}

	/**
	 * Colored 0–100 score chip, matching the wp-admin score bands.
	 */
	public static function score_chip( int $score ): string {
		$tone = $score >= 80 ? 'ok' : ( $score >= 50 ? 'warn' : 'bad' );
		return '<span style="display:inline-block;min-width:34px;padding:3px 8px;border-radius:8px;text-align:center;background:' . self::TONES[ $tone ]['bg'] . ';color:' . self::TONES[ $tone ]['fg'] . ';font:800 12px/1.5 Arial,Helvetica,sans-serif">' . $score . '</span>';
	}

	/** Small section heading with a gradient dash. */
	public static function section( string $title ): string {
		return '<p style="margin:22px 0 6px;font:800 13px/1.5 Arial,Helvetica,sans-serif;letter-spacing:.02em;color:#0F172A"><span style="display:inline-block;width:14px;height:4px;border-radius:2px;margin-right:8px;vertical-align:2px;background:' . self::GRADIENT . '"></span>' . esc_html( $title ) . '</p>';
	}

	/** Human label for a header status pill. */
	private static function tone_label( string $tone ): string {
		return match ( $tone ) {
			'ok' => __( 'All good', 'seoistic' ),
			'warn' => __( 'Needs review', 'seoistic' ),
			'bad' => __( 'Action needed', 'seoistic' ),
			'info' => __( 'Insight', 'seoistic' ),
			default => '',
		};
	}
}
