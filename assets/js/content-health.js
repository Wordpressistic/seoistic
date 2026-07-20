/**
 * SEOISTIC → Content Health: the orphan-page scan button. Content decay is
 * computed live server-side on page load (a cheap meta query), so it needs
 * no JS — only the orphan scan (a heavier content-regex pass) is triggered
 * on demand via AJAX.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.getElementById( 'seoistic-scan-orphans' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			if ( ! window.SeoisticContentHealth || ! window.SeoisticContentHealth.ajaxUrl ) {
				return;
			}
			var resultBox = document.getElementById( 'seoistic-orphans-result' );
			btn.disabled = true;

			var body = new URLSearchParams();
			body.set( 'action', 'seoistic_scan_orphans' );
			body.set( 'nonce', window.SeoisticContentHealth.nonce );

			fetch( window.SeoisticContentHealth.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( json ) {
					if ( resultBox ) {
						resultBox.style.display = 'block';
						resultBox.className = 'seoistic-tool-result ' + ( json && json.success ? 'is-success' : 'is-error' );
						resultBox.textContent = ( json && json.data && json.data.message ) || 'Scan failed.';
					}
					if ( json && json.success ) {
						window.location.reload();
					}
				} )
				.catch( function () {
					if ( resultBox ) {
						resultBox.style.display = 'block';
						resultBox.className = 'seoistic-tool-result is-error';
						resultBox.textContent = 'Scan failed.';
					}
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	} );
} )();
