/**
 * SEOISTIC → Search Console: the per-URL indexing/coverage lookup (URL
 * Inspection API). The Search Analytics tables are server-rendered on page
 * load, so this is the only interactive piece on the dashboard.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn = document.getElementById( 'seoistic-gsc-inspect-btn' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var input = document.getElementById( 'seoistic-gsc-inspect-url' );
			var resultBox = document.getElementById( 'seoistic-gsc-inspect-result' );
			var url = input ? input.value.trim() : '';
			if ( '' === url || ! window.SeoisticGsc || ! window.SeoisticGsc.ajaxUrl ) {
				return;
			}

			btn.disabled = true;
			var body = new URLSearchParams();
			body.set( 'action', 'seoistic_gsc_inspect_url' );
			body.set( 'nonce', window.SeoisticGsc.nonce );
			body.set( 'url', url );

			fetch( window.SeoisticGsc.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( json ) {
					if ( ! resultBox ) {
						return;
					}
					resultBox.style.display = 'block';
					if ( ! json || ! json.success ) {
						resultBox.className = 'seoistic-tool-result is-error';
						resultBox.textContent = ( json && json.data && json.data.message ) || 'Inspection failed.';
						return;
					}
					resultBox.className = 'seoistic-tool-result is-success';
					resultBox.textContent = formatInspection( json.data && json.data.data );
				} )
				.catch( function () {
					if ( resultBox ) {
						resultBox.style.display = 'block';
						resultBox.className = 'seoistic-tool-result is-error';
						resultBox.textContent = 'Inspection failed.';
					}
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	} );

	function formatInspection( data ) {
		if ( ! data || ! data.indexStatusResult ) {
			return 'No inspection data returned.';
		}
		var idx = data.indexStatusResult;
		var lines = [
			'Verdict: ' + ( idx.verdict || 'unknown' ),
			'Coverage: ' + ( idx.coverageState || 'unknown' ),
		];
		if ( idx.lastCrawlTime ) {
			lines.push( 'Last crawled: ' + idx.lastCrawlTime );
		}
		if ( idx.robotsTxtState ) {
			lines.push( 'robots.txt: ' + idx.robotsTxtState );
		}
		return lines.join( '\n' );
	}
} )();
